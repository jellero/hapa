<?php
$date = static function (?string $value): string {
    if ($value === null) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return '—';
    }
};
$money = static fn (?int $minor, string $currency): string => $minor === null
    ? '—'
    : number_format($minor / 100, 2, ',', '.') . ' ' . $currency;
$tone = static fn (string $status): string => match ($status) {
    'fulfilment_completed', 'completed_partial', 'tracking_sent', 'completed', 'success' => 'success',
    'manual_review', 'cancelled', 'error', 'permanent_error', 'rejected' => 'danger',
    'waiting_address', 'waiting_goods', 'partial_available', 'temporary_error' => 'warning',
    'goods_available', 'picking', 'partial_confirmed', 'ready_for_carrier', 'label_available', 'created', 'shipped' => 'info',
    default => 'neutral',
};
$address = static function (?array $value): string {
    if ($value === null) {
        return '—';
    }
    $parts = array_filter([
        $value['recipient'] ?? null,
        $value['address_line1'] ?? null,
        $value['address_line2'] ?? null,
        trim((string) ($value['postal_code'] ?? '') . ' ' . (string) ($value['city'] ?? '') . ' ' . (string) ($value['province'] ?? '')),
        $value['country_code'] ?? null,
        $value['phone'] ?? null,
    ], static fn (mixed $part): bool => is_string($part) && trim($part) !== '');

    return $parts === [] ? '—' : implode("\n", $parts);
};
?>
<a class="back-link" href="/ui/orders"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-left"></use></svg>Torna agli ordini</a>

<?php if (($order ?? null) === null): ?>
<header class="page-header page-header--detail"><div><p class="eyebrow"><?= $e($eyebrow) ?></p><h1><?= $e($title) ?></h1><p class="page-header__description">L’ordine richiesto non è presente nel registro HAPA.</p></div></header>
<div class="empty-state"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#orders"></use></svg></span><h2>Ordine non trovato</h2><p>Numero richiesto: <?= $e($orderId) ?></p></div>
<?php else: ?>
<header class="page-header page-header--detail">
    <div>
        <div class="detail-title-row"><p class="eyebrow"><?= $e($eyebrow) ?></p><span class="status-badge status-badge--<?= $e($tone($order['status'])) ?>"><?= $e($order['status']) ?></span></div>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
</header>

<section class="summary-grid" aria-label="Riepilogo ordine">
    <article><span>Cliente</span><strong><?= $e($order['customer_name'] ?? 'Non collegato') ?></strong><small><?= $e($order['customer_code'] ?? 'Nessun codice cliente') ?></small></article>
    <article><span>Origine</span><strong><?= $e($order['marketplace_name'] ?? $order['origin_reference'] ?? $order['origin']) ?></strong><small><?= $e($order['marketplace_account_name'] ?? $order['marketplace_account_code'] ?? $order['origin']) ?></small></article>
    <article><span>Data ordine</span><strong><?= $e($date($order['ordered_at'])) ?></strong><small>Aggiornato <?= $e($date($order['updated_at'])) ?></small></article>
    <article><span>Totale</span><strong><?= $e($money($order['grand_total_minor'], $order['currency'])) ?></strong><small>Versione <?= $e((string) $order['version']) ?></small></article>
</section>

<nav class="tabs" aria-label="Sezioni dettaglio ordine"><a class="is-active" href="#overview">Panoramica</a><a href="#lines">Righe</a><a href="#purchases">Acquisti Space</a><a href="#shipments">Spedizioni</a><a href="#addresses">Indirizzi</a><a href="#history">Cronologia</a></nav>

<div class="detail-grid" id="overview">
    <div class="detail-grid__main">
        <section class="panel" aria-labelledby="order-master-data-title">
            <div class="panel__header"><div><p class="eyebrow">Anagrafica ordine</p><h2 id="order-master-data-title">Cliente e provenienza</h2></div></div>
            <dl class="settings-list">
                <div><dt>Numero interno</dt><dd><?= $e($order['order_number']) ?></dd></div>
                <div><dt>Riferimento esterno</dt><dd><?= $e($order['external_order_id']) ?></dd></div>
                <div><dt>Origine</dt><dd><?= $e($order['origin']) ?></dd></div>
                <div><dt>Connettore</dt><dd><?= $e($order['connector_code'] ?? '—') ?></dd></div>
                <div><dt>Email cliente</dt><dd><?= $e($order['customer_email'] ?? '—') ?></dd></div>
                <div><dt>Telefono cliente</dt><dd><?= $e($order['customer_phone'] ?? '—') ?></dd></div>
            </dl>
        </section>

        <section class="panel" id="lines" aria-labelledby="order-lines-title">
            <div class="panel__header"><div><p class="eyebrow">Contenuto</p><h2 id="order-lines-title">Righe ordine</h2></div></div>
            <?php if ($order['lines'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessuna riga caricata</h3><p>L’ordine non contiene righe persistite.</p></div></div><?php else: ?>
            <div class="table-scroll"><table class="data-table data-table--compact"><thead><tr><th>Riga</th><th>SKU / EAN</th><th>Descrizione</th><th>Quantità</th><th>Esito</th><th>Importo</th></tr></thead><tbody>
            <?php foreach ($order['lines'] as $line): ?><tr>
                <td><?= $e((string) $line['line_number']) ?><small><?= $e($line['external_line_id'] ?? '—') ?></small></td>
                <td><strong><?= $e($line['sku']) ?></strong><small><?= $e($line['ean'] ?? '—') ?></small></td>
                <td><?= $e($line['description_snapshot'] ?? $line['catalog_item_name'] ?? '—') ?></td>
                <td><?= $e((string) $line['quantity_ordered']) ?> ordinate<small><?= $e((string) $line['quantity_available']) ?> disponibili · <?= $e((string) $line['quantity_to_ship']) ?> da spedire</small></td>
                <td><?= $e((string) $line['quantity_to_cancel']) ?> annullate<small><?= $e($line['partial_reason'] ?? '—') ?></small></td>
                <td><?= $e($money($line['line_total_minor'], $order['currency'])) ?><small>Unitario <?= $e($money($line['unit_price_minor'], $order['currency'])) ?></small></td>
            </tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </section>

        <section class="panel" id="purchases" aria-labelledby="purchases-title">
            <div class="panel__header"><div><p class="eyebrow">Approvvigionamento</p><h2 id="purchases-title">Acquisti verso Space</h2></div></div>
            <?php if ($order['purchases'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessun acquisto collegato</h3><p>La richiesta di acquisto a Space non è ancora stata creata.</p></div></div><?php else: ?>
            <div class="table-scroll"><table class="data-table data-table--compact"><thead><tr><th>Acquisto</th><th>Fornitore</th><th>Stato</th><th>Righe</th><th>Totale</th><th>Aggiornato</th></tr></thead><tbody><?php foreach ($order['purchases'] as $purchase): ?><tr>
                <td><strong><?= $e($purchase['purchase_number']) ?></strong><small><?= $e($purchase['external_purchase_id'] ?? 'Non inviato') ?> · v<?= $e((string) $purchase['version']) ?><?= $purchase['auto_generated'] ? ' · automatico' : '' ?></small></td>
                <td><?= $e($purchase['supplier_name']) ?><small><?= $e($purchase['supplier_code']) ?></small></td>
                <td><span class="status-badge status-badge--<?= $e($tone($purchase['status'])) ?>"><?= $e($purchase['status']) ?></span><?php if ($purchase['last_error'] !== null): ?><small><?= $e($purchase['last_error']) ?></small><?php elseif ($purchase['integration_account_code'] !== null): ?><small><?= $e($purchase['integration_account_code']) ?></small><?php endif; ?></td>
                <td><?= $e((string) $purchase['line_count']) ?></td><td><?= $e($money($purchase['grand_total_minor'], $purchase['currency'])) ?></td><td><?= $e($date($purchase['updated_at'])) ?></td>
            </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>

        <section class="panel" id="shipments" aria-labelledby="shipments-title">
            <div class="panel__header"><div><p class="eyebrow">Logistica</p><h2 id="shipments-title">Spedizioni ed etichette</h2></div></div>
            <?php if ($order['shipments'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessuna spedizione</h3><p>Non risultano richieste GLS o BRT per questo ordine.</p></div></div><?php else: ?>
            <?php foreach ($order['shipments'] as $shipment): ?><article class="workstream-item"><div class="workstream-item__copy">
                <strong><?= $e($shipment['provider']) ?> · <?= $e($shipment['tracking_number'] ?? 'tracking non assegnato') ?></strong>
                <span><?= $e((string) $shipment['packages']) ?> colli · <?= $e($shipment['weight_kg'] === null ? 'peso non disponibile' : (string) $shipment['weight_kg'] . ' kg') ?> · <span class="status-badge status-badge--<?= $e($tone($shipment['status'])) ?>"><?= $e($shipment['status']) ?></span></span>
                <?php if ($shipment['packages_detail'] !== []): ?><small><?php foreach ($shipment['packages_detail'] as $package): ?>Collo <?= $e((string) $package['package_number']) ?>: <?= $e(number_format($package['weight_grams'] / 1000, 3, ',', '.')) ?> kg<?php if ($package !== $shipment['packages_detail'][array_key_last($shipment['packages_detail'])]): ?> · <?php endif; ?><?php endforeach; ?></small><?php endif; ?>
                <?php if ($shipment['labels'] !== []): ?><small>Etichette: <?php foreach ($shipment['labels'] as $label): ?><code><?= $e($label['format']) ?></code> generata <?= $e($date($label['generated_at'])) ?><?php if ($label !== $shipment['labels'][array_key_last($shipment['labels'])]): ?> · <?php endif; ?><?php endforeach; ?></small><?php endif; ?>
            </div></article><?php endforeach; ?><?php endif; ?>
        </section>

        <section class="panel" id="legacy-deliveries" aria-labelledby="legacy-deliveries-title">
            <div class="panel__header"><div><p class="eyebrow">Sola consultazione</p><h2 id="legacy-deliveries-title">Tentativi legacy</h2><p>I nuovi tentativi provider sono gestiti nel database separato di hapa-automation.</p></div></div>
            <?php if ($order['legacy_deliveries'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessun tentativo legacy</h3><p>Non sono presenti operazioni del runtime precedente.</p></div></div><?php else: ?>
            <div class="table-scroll"><table class="data-table data-table--compact"><thead><tr><th>Provider</th><th>Operazione</th><th>Stato</th><th>Tentativo</th><th>HTTP</th><th>Data</th></tr></thead><tbody><?php foreach ($order['legacy_deliveries'] as $delivery): ?><tr><td><?= $e($delivery['provider']) ?></td><td><?= $e($delivery['operation']) ?></td><td><span class="status-badge status-badge--<?= $e($tone($delivery['status'])) ?>"><?= $e($delivery['status']) ?></span><small><?= $e($delivery['error_code'] ?? '—') ?></small></td><td><?= $e((string) $delivery['attempt']) ?></td><td><?= $e($delivery['http_status'] === null ? '—' : (string) $delivery['http_status']) ?></td><td><?= $e($date($delivery['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
    </div>

    <aside class="detail-grid__aside" aria-label="Dati complementari ordine">
        <section class="panel" aria-labelledby="financials-title"><div class="panel__header"><div><p class="eyebrow">Importi</p><h2 id="financials-title">Riepilogo economico</h2></div></div><dl class="settings-list">
            <div><dt>Subtotale</dt><dd><?= $e($money($order['subtotal_minor'], $order['currency'])) ?></dd></div><div><dt>Spedizione</dt><dd><?= $e($money($order['shipping_total_minor'], $order['currency'])) ?></dd></div><div><dt>Sconti</dt><dd><?= $e($money($order['discount_total_minor'], $order['currency'])) ?></dd></div><div><dt>Imposte</dt><dd><?= $e($money($order['tax_total_minor'], $order['currency'])) ?></dd></div><div><dt>Totale</dt><dd><strong><?= $e($money($order['grand_total_minor'], $order['currency'])) ?></strong></dd></div>
        </dl></section>
        <section class="panel" id="addresses" aria-labelledby="shipping-address-title"><div class="panel__header"><div><p class="eyebrow">Snapshot storico</p><h2 id="shipping-address-title">Spedizione</h2></div></div><p><?= nl2br($e($address($order['shipping_address']))) ?></p><p class="muted-copy">Lo snapshot resta immutato quando cambia l’indirizzo del cliente.</p></section>
        <section class="panel" aria-labelledby="billing-address-title"><div class="panel__header"><div><p class="eyebrow">Snapshot storico</p><h2 id="billing-address-title">Fatturazione</h2></div></div><p><?= nl2br($e($address($order['billing_address']))) ?></p></section>
        <section class="panel" id="history" aria-labelledby="timeline-title"><div class="panel__header"><div><p class="eyebrow">Cronologia dominio</p><h2 id="timeline-title">Transizioni ordine</h2></div></div>
            <?php if ($order['transitions'] === []): ?><div class="timeline-empty"><span aria-hidden="true"></span><div><strong>Nessuna transizione</strong><p>L’ordine è ancora alla versione iniziale o proviene da dati precedenti alla cronologia.</p></div></div><?php else: ?>
            <?php foreach ($order['transitions'] as $transition): ?><article class="workstream-item"><div class="workstream-item__copy"><strong><?= $e($transition['from_status']) ?> → <?= $e($transition['to_status']) ?></strong><span><?= $e($date($transition['occurred_at'])) ?> · versione <?= $e((string) $transition['version']) ?></span><?php if ($transition['reason'] !== null): ?><small><?= $e($transition['reason']) ?></small><?php endif; ?></div></article><?php endforeach; ?><?php endif; ?>
        </section>
        <div class="inline-notice"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg><span>I payload tecnici e i riferimenti di storage delle etichette non sono esposti in questa vista.</span></div>
    </aside>
</div>
<?php endif; ?>
