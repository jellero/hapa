<?php

declare(strict_types=1);

namespace Hapa\Modules\Procurement\Application;

use Hapa\Core\Outbox\OutboxRepository;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Modules\Procurement\Contract\AutomaticPurchaseGenerator;
use PDO;
use RuntimeException;

final readonly class AutomaticSpacePurchaseGenerator implements AutomaticPurchaseGenerator
{
    public function __construct(
        private PDO $pdo,
        private OutboxRepository $outbox,
        private ProviderCommandFactory $commands,
    ) {
    }

    public function generate(int $orderId, string $correlationId): void
    {
        $order = $this->order($orderId);
        $supplierId = $this->spaceSupplierId();
        $existing = $this->existing($orderId, $supplierId);

        if ($order['status'] === 'cancelled') {
            if ($existing !== null && !in_array($existing['status'], ['completed', 'cancelled', 'rejected'], true)) {
                $this->markManualReview((int) $existing['id'], 'La vendita marketplace è stata annullata dopo la generazione dell\'acquisto.');
            }

            return;
        }

        if ($existing !== null && $existing['status'] !== 'manual_review') {
            return;
        }

        $account = $this->spaceAccount();
        $resolution = $this->resolveLines($orderId, $supplierId);
        $reason = $resolution['reason'];
        if ($account === null) {
            $reason = 'Nessun account Space operativo, sincronizzato e abilitato alla scrittura acquisti.';
        }

        if ($reason !== null) {
            $purchaseId = $this->persist(
                $existing,
                $order,
                $supplierId,
                null,
                $resolution['lines'],
                'manual_review',
                $reason,
            );

            return;
        }

        if ($account === null) {
            throw new RuntimeException('Account Space non risolto dopo la validazione.');
        }

        $purchaseId = $this->persist(
            $existing,
            $order,
            $supplierId,
            (int) $account['id'],
            $resolution['lines'],
            'requested',
            null,
        );
        $purchase = $this->purchase($purchaseId);
        $idempotencyKey = sprintf('space-purchase:%d:v%d:submit', $purchaseId, (int) $purchase['version']);
        $payload = [
            'integration_account_code' => (string) $account['code'],
            'configuration_version' => (int) $account['configuration_version'],
            'purchase_order_id' => (string) $purchaseId,
            'purchase_order_number' => (string) $purchase['purchase_number'],
            'purchase_order_version' => (int) $purchase['version'],
            'sales_order_id' => (string) $orderId,
            'sales_order_number' => (string) $order['order_number'],
            'currency' => (string) $purchase['currency'],
            'lines' => array_map(static fn (array $line): array => [
                'purchase_order_line_id' => (string) $line['purchase_order_line_id'],
                'sales_order_line_id' => (string) $line['order_line_id'],
                'supplier_catalog_item_id' => (string) $line['supplier_catalog_item_id'],
                'supplier_item_id' => $line['external_item_id'],
                'sku' => $line['supplier_sku'],
                'quantity' => $line['quantity'],
                'expected_unit_cost_minor' => $line['unit_cost_minor'],
                'currency' => $line['currency'],
            ], $this->persistedLines($purchaseId)),
            'idempotency_key' => $idempotencyKey,
        ];
        $queued = $this->outbox->append($this->commands->create(
            'space.purchase_order.submit.requested',
            'supplier_purchase_order',
            (string) $purchaseId,
            $payload,
            $correlationId,
        ));

        if (!$queued) {
            throw new RuntimeException('Comando acquisto Space duplicato senza un acquisto già richiesto.');
        }
    }

    /** @return array<string, mixed> */
    private function order(int $orderId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, order_number, status, currency, shipping_address
FROM orders WHERE id = :id FOR UPDATE
SQL);
        $statement->execute(['id' => $orderId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Ordine vendita non trovato per la generazione acquisto Space.');
        }
        if ($row['shipping_address'] === null) {
            throw new RuntimeException('Indirizzo di spedizione assente sull\'ordine vendita.');
        }

        return $row;
    }

    private function spaceSupplierId(): int
    {
        $statement = $this->pdo->query("SELECT id FROM suppliers WHERE code = 'space' AND active FOR SHARE");
        $id = $statement === false ? false : $statement->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Fornitore Space non configurato o disabilitato.');
        }

        return (int) $id;
    }

    /** @return array<string, mixed>|null */
    private function existing(int $orderId, int $supplierId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT * FROM supplier_purchase_orders
WHERE order_id = :order_id AND supplier_id = :supplier_id AND auto_generated
FOR UPDATE
SQL);
        $statement->execute(['order_id' => $orderId, 'supplier_id' => $supplierId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function spaceAccount(): ?array
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT account.id, account.code, account.configuration_version
FROM integration_accounts account
JOIN integration_account_capabilities capability
  ON capability.integration_account_id = account.id
 AND capability.capability = 'purchase_orders.write'
 AND capability.enabled
WHERE account.provider_code = 'space'
  AND account.desired_status IN ('pilot', 'active')
  AND account.secret_status = 'configured'
  AND account.connection_test_status = 'passed'
  AND account.automation_configuration_version = account.configuration_version
ORDER BY CASE account.desired_status WHEN 'active' THEN 0 ELSE 1 END, account.id
LIMIT 1
FOR SHARE OF account
SQL);
        $row = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{lines:list<array<string, mixed>>,reason:?string}
     */
    private function resolveLines(int $orderId, int $supplierId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, line_number, catalog_item_id, sku, ean, description_snapshot, quantity_ordered
FROM order_lines WHERE order_id = :order_id ORDER BY line_number FOR UPDATE
SQL);
        $statement->execute(['order_id' => $orderId]);
        $salesLines = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($salesLines === []) {
            return ['lines' => [], 'reason' => 'L\'ordine vendita non contiene righe acquistabili.'];
        }

        $resolved = [];
        $required = [];
        foreach ($salesLines as $salesLine) {
            $candidates = $this->candidates($supplierId, $salesLine);
            if ($candidates === []) {
                return ['lines' => [], 'reason' => sprintf(
                    'Riga %d (%s) non associata a un articolo Space attivo.',
                    (int) $salesLine['line_number'],
                    (string) $salesLine['sku'],
                )];
            }
            $bestScore = (int) $candidates[0]['match_score'];
            $best = array_values(array_filter(
                $candidates,
                static fn (array $candidate): bool => (int) $candidate['match_score'] === $bestScore,
            ));
            if (count($best) !== 1) {
                return ['lines' => [], 'reason' => sprintf(
                    'Riga %d (%s) associabile a più articoli Space con la stessa priorità.',
                    (int) $salesLine['line_number'],
                    (string) $salesLine['sku'],
                )];
            }
            $offer = $best[0];
            if ($offer['external_item_id'] === null || !ctype_digit((string) $offer['external_item_id'])
                || (int) $offer['external_item_id'] < 1) {
                return ['lines' => [], 'reason' => sprintf(
                    'ID articolo Space assente o non numerico per %s.',
                    (string) $offer['supplier_sku'],
                )];
            }
            $quantity = (int) $salesLine['quantity_ordered'];
            $offerId = (int) $offer['supplier_catalog_item_id'];
            $required[$offerId] = ($required[$offerId] ?? 0) + $quantity;
            if ($required[$offerId] > (int) $offer['available_quantity']) {
                return ['lines' => [], 'reason' => sprintf(
                    'Disponibilità Space insufficiente per %s: richiesti %d, osservati %d.',
                    (string) $offer['supplier_sku'],
                    $required[$offerId],
                    (int) $offer['available_quantity'],
                )];
            }
            if ($offer['purchase_cost_minor'] === null) {
                return ['lines' => [], 'reason' => sprintf(
                    'Costo di acquisto Space assente per %s.',
                    (string) $offer['supplier_sku'],
                )];
            }
            $resolved[] = [
                'line_number' => (int) $salesLine['line_number'],
                'order_line_id' => (int) $salesLine['id'],
                'catalog_item_id' => (int) $offer['catalog_item_id'],
                'supplier_catalog_item_id' => $offerId,
                'external_item_id' => (string) $offer['external_item_id'],
                'supplier_sku' => (string) $offer['supplier_sku'],
                'description_snapshot' => $salesLine['description_snapshot'] === null ? null : (string) $salesLine['description_snapshot'],
                'quantity' => $quantity,
                'unit_cost_minor' => (int) $offer['purchase_cost_minor'],
                'currency' => (string) $offer['currency'],
            ];
        }

        $currencies = array_values(array_unique(array_column($resolved, 'currency')));
        if (count($currencies) !== 1) {
            return ['lines' => [], 'reason' => 'Le righe Space risolte usano valute differenti.'];
        }

        return ['lines' => $resolved, 'reason' => null];
    }

    /**
     * @param array<string, mixed> $salesLine
     * @return list<array<string, mixed>>
     */
    private function candidates(int $supplierId, array $salesLine): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT offer.id AS supplier_catalog_item_id, offer.catalog_item_id,
       offer.external_item_id, offer.supplier_sku, offer.purchase_cost_minor,
       offer.currency, offer.available_quantity,
       CASE
           WHEN CAST(:catalog_item_id AS BIGINT) IS NOT NULL
                AND item.id = CAST(:catalog_item_id AS BIGINT) THEN 4
           WHEN lower(offer.supplier_sku) = lower(:sku) THEN 3
           WHEN lower(item.sku) = lower(:sku) THEN 2
           WHEN CAST(:ean AS VARCHAR) IS NOT NULL AND item.ean = CAST(:ean AS VARCHAR) THEN 1
           ELSE 0
       END AS match_score
FROM supplier_catalog_items offer
JOIN catalog_items item ON item.id = offer.catalog_item_id
WHERE offer.supplier_id = :supplier_id
  AND offer.active
  AND offer.supplier_sku IS NOT NULL
  AND (
      (CAST(:catalog_item_id AS BIGINT) IS NOT NULL AND item.id = CAST(:catalog_item_id AS BIGINT))
      OR lower(offer.supplier_sku) = lower(:sku)
      OR lower(item.sku) = lower(:sku)
      OR (CAST(:ean AS VARCHAR) IS NOT NULL AND item.ean = CAST(:ean AS VARCHAR))
  )
ORDER BY match_score DESC, offer.id
FOR SHARE OF offer, item
SQL);
        $statement->execute([
            'catalog_item_id' => $salesLine['catalog_item_id'],
            'sku' => (string) $salesLine['sku'],
            'ean' => $salesLine['ean'],
            'supplier_id' => $supplierId,
        ]);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $order
     * @param list<array<string, mixed>> $lines
     */
    private function persist(
        ?array $existing,
        array $order,
        int $supplierId,
        ?int $integrationAccountId,
        array $lines,
        string $status,
        ?string $lastError,
    ): int {
        $subtotal = $lines === [] ? null : array_sum(array_map(
            static fn (array $line): int => (int) $line['unit_cost_minor'] * (int) $line['quantity'],
            $lines,
        ));
        $currency = $lines === [] ? (string) $order['currency'] : (string) $lines[0]['currency'];
        if ($existing === null) {
            $number = 'SPA-' . strtoupper(substr(hash('sha256', (string) $order['order_number']), 0, 28));
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO supplier_purchase_orders (
    order_id, supplier_id, integration_account_id, purchase_number,
    status, currency, subtotal_minor, tax_total_minor, grand_total_minor,
    version, submitted_at, auto_generated, last_error, created_at, updated_at
) VALUES (
    :order_id, :supplier_id, :integration_account_id, :purchase_number,
    :status, :currency, :subtotal_minor, NULL, :grand_total_minor,
    1, CASE WHEN :submitted THEN NOW() ELSE NULL END, TRUE, :last_error, NOW(), NOW()
)
RETURNING id
SQL);
            $statement->bindValue('order_id', (int) $order['id'], PDO::PARAM_INT);
            $statement->bindValue('supplier_id', $supplierId, PDO::PARAM_INT);
            $statement->bindValue('integration_account_id', $integrationAccountId, $integrationAccountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('purchase_number', $number);
            $statement->bindValue('status', $status);
            $statement->bindValue('currency', $currency);
            $statement->bindValue('subtotal_minor', $subtotal, $subtotal === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('grand_total_minor', $subtotal, $subtotal === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('submitted', $status === 'requested', PDO::PARAM_BOOL);
            $statement->bindValue('last_error', $lastError, $lastError === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->execute();
            $id = $statement->fetchColumn();
            if ($id === false) {
                throw new RuntimeException('Creazione automatica acquisto Space fallita.');
            }
            $purchaseId = (int) $id;
        } else {
            $purchaseId = (int) $existing['id'];
            $statement = $this->pdo->prepare(<<<'SQL'
UPDATE supplier_purchase_orders
SET integration_account_id = :integration_account_id, status = :status,
    currency = :currency, subtotal_minor = :subtotal_minor,
    tax_total_minor = NULL, grand_total_minor = :grand_total_minor,
    version = version + 1,
    submitted_at = CASE WHEN :submitted THEN NOW() ELSE submitted_at END,
    last_error = :last_error, updated_at = NOW()
WHERE id = :id AND status = 'manual_review'
SQL);
            $statement->bindValue('integration_account_id', $integrationAccountId, $integrationAccountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('status', $status);
            $statement->bindValue('currency', $currency);
            $statement->bindValue('subtotal_minor', $subtotal, $subtotal === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('grand_total_minor', $subtotal, $subtotal === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('submitted', $status === 'requested', PDO::PARAM_BOOL);
            $statement->bindValue('last_error', $lastError, $lastError === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('id', $purchaseId, PDO::PARAM_INT);
            $statement->execute();
        }

        $this->replaceLines($purchaseId, $lines);

        return $purchaseId;
    }

    /** @param list<array<string, mixed>> $lines */
    private function replaceLines(int $purchaseId, array $lines): void
    {
        $delete = $this->pdo->prepare('DELETE FROM supplier_purchase_order_lines WHERE purchase_order_id = :id');
        $delete->execute(['id' => $purchaseId]);
        $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO supplier_purchase_order_lines (
    purchase_order_id, order_line_id, supplier_catalog_item_id, line_number,
    supplier_sku, description_snapshot, quantity, unit_cost_minor, currency,
    created_at, updated_at
) VALUES (
    :purchase_order_id, :order_line_id, :supplier_catalog_item_id, :line_number,
    :supplier_sku, :description_snapshot, :quantity, :unit_cost_minor, :currency,
    NOW(), NOW()
)
RETURNING id
SQL);
        $link = $this->pdo->prepare('UPDATE order_lines SET catalog_item_id = :catalog_item_id, updated_at = NOW() WHERE id = :id');
        foreach ($lines as $line) {
            $insert->execute([
                'purchase_order_id' => $purchaseId,
                'order_line_id' => $line['order_line_id'],
                'supplier_catalog_item_id' => $line['supplier_catalog_item_id'],
                'line_number' => $line['line_number'],
                'supplier_sku' => $line['supplier_sku'],
                'description_snapshot' => $line['description_snapshot'],
                'quantity' => $line['quantity'],
                'unit_cost_minor' => $line['unit_cost_minor'],
                'currency' => $line['currency'],
            ]);
            $link->execute(['catalog_item_id' => $line['catalog_item_id'], 'id' => $line['order_line_id']]);
        }
    }

    /** @return array<string, mixed> */
    private function purchase(int $purchaseId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM supplier_purchase_orders WHERE id = :id');
        $statement->execute(['id' => $purchaseId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Acquisto Space appena generato non recuperabile.');
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function persistedLines(int $purchaseId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT line.id AS purchase_order_line_id, line.order_line_id,
       line.supplier_catalog_item_id, offer.external_item_id,
       line.supplier_sku, line.quantity, line.unit_cost_minor, line.currency
FROM supplier_purchase_order_lines line
JOIN supplier_catalog_items offer ON offer.id = line.supplier_catalog_item_id
WHERE line.purchase_order_id = :id
ORDER BY line.line_number
SQL);
        $statement->execute(['id' => $purchaseId]);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function markManualReview(int $purchaseId, string $reason): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE supplier_purchase_orders
SET status = 'manual_review', last_error = :reason, version = version + 1, updated_at = NOW()
WHERE id = :id AND status NOT IN ('completed', 'cancelled', 'rejected')
SQL);
        $statement->execute(['id' => $purchaseId, 'reason' => $reason]);
    }

}
