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
    'fulfilment_completed', 'completed_partial', 'tracking_sent' => 'success',
    'manual_review', 'cancelled' => 'danger',
    'waiting_address', 'waiting_goods', 'partial_available' => 'warning',
    'goods_available', 'picking', 'partial_confirmed', 'ready_for_carrier', 'label_available' => 'info',
    default => 'neutral',
};
?>
<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
</header>

<section class="panel data-panel" aria-labelledby="orders-results-title">
    <div class="panel__header">
        <div><p class="eyebrow">Registro commerciale</p><h2 id="orders-results-title">Ordini HAPA</h2></div>
        <span class="section-heading__meta"><?= $e((string) count($orders ?? [])) ?> risultati visualizzati</span>
    </div>
    <form class="data-toolbar" method="get" action="/ui/orders" role="search">
        <label class="search-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg>
            <span class="sr-only">Cerca ordine</span>
            <input type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Ordine, cliente, SKU, EAN o tracking">
        </label>
        <div class="toolbar-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#filter"></use></svg>
            <label class="sr-only" for="order-status">Filtra per stato</label>
            <select id="order-status" name="status">
                <option value="">Tutti gli stati</option>
                <?php foreach (['to_process' => 'Da lavorare', 'waiting_goods' => 'In attesa merce', 'picking' => 'Picking e spedizione', 'manual_review' => 'Revisione manuale', 'completed' => 'Completati', 'cancelled' => 'Annullati'] as $value => $label): ?>
                    <option value="<?= $e($value) ?>"<?= ($selectedStatus ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button--secondary" type="submit">Applica</button>
        <?php if (($query ?? '') !== '' || ($selectedStatus ?? '') !== ''): ?><a class="button button--ghost" href="/ui/orders">Azzera</a><?php endif; ?>
    </form>

    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Ordine</th><th>Cliente</th><th>Origine</th><th>Stato</th><th>Righe</th><th>Totale</th><th>Aggiornato</th><th>Azioni</th></tr></thead>
            <tbody>
            <?php if (($orders ?? []) === []): ?>
                <tr><td colspan="8"><div class="empty-state"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#orders"></use></svg></span><h3>Nessun ordine trovato</h3><p>Gli ordini importati dai marketplace e quelli B2C compariranno qui mantenendo separati canale, account e connettore.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?= $e($order['order_number']) ?></strong><small>Esterno: <?= $e($order['external_order_id']) ?> · v<?= $e((string) $order['version']) ?></small></td>
                        <td><?= $e($order['customer_name'] ?? 'Cliente non collegato') ?><small><?= $e($order['customer_code'] ?? '—') ?></small></td>
                        <td><?= $e($order['marketplace_code'] ?? $order['origin_reference'] ?? $order['origin']) ?><small><?= $e($order['marketplace_account_code'] ?? $order['origin']) ?></small></td>
                        <td><span class="status-badge status-badge--<?= $e($tone($order['status'])) ?>"><?= $e($order['status']) ?></span><?php if ($order['tracking_numbers'] !== null): ?><small><?= $e($order['tracking_numbers']) ?></small><?php endif; ?></td>
                        <td><?= $e((string) $order['line_count']) ?><small><?= $e((string) $order['shipment_count']) ?> spedizioni</small></td>
                        <td><?= $e($money($order['grand_total_minor'], $order['currency'])) ?></td>
                        <td><?= $e($date($order['updated_at'])) ?><small>Ordine: <?= $e($date($order['ordered_at'])) ?></small></td>
                        <td><a class="button button--ghost" href="/ui/orders/<?= $e(rawurlencode($order['order_number'])) ?>">Apri ordine</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
