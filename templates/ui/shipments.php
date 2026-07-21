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
$tone = static fn (string $status): string => match ($status) {
    'shipped' => 'success', 'label_available', 'created' => 'info',
    'error' => 'danger', 'pending' => 'warning', default => 'neutral',
};
$weight = static fn (array $shipment): string => match (true) {
    $shipment['package_weight_grams'] > 0 => number_format($shipment['package_weight_grams'] / 1000, 3, ',', '.') . ' kg',
    $shipment['weight_kg'] === null => '—',
    default => (string) $shipment['weight_kg'] . ' kg',
};
?>
<header class="page-header"><div><p class="eyebrow"><?= $e($eyebrow) ?></p><h1><?= $e($title) ?></h1><p class="page-header__description"><?= $e($description) ?></p></div></header>

<section class="panel data-panel" aria-labelledby="shipments-results-title">
    <div class="panel__header"><div><p class="eyebrow">Registro logistico</p><h2 id="shipments-results-title">Spedizioni HAPA</h2></div><span class="section-heading__meta"><?= $e((string) count($shipments ?? [])) ?> risultati visualizzati</span></div>
    <form class="data-toolbar" method="get" action="/ui/shipments" role="search">
        <label class="search-field"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg><span class="sr-only">Cerca spedizione</span><input type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Ordine, cliente, spedizione o tracking"></label>
        <div class="toolbar-field"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#filter"></use></svg><label class="sr-only" for="shipment-status">Filtra per stato</label><select id="shipment-status" name="status"><option value="">Tutti gli stati</option><?php foreach (['pending' => 'Da creare', 'created' => 'Creata', 'label_available' => 'Etichetta pronta', 'shipped' => 'Spedita', 'error' => 'Con errore', 'cancelled' => 'Annullata'] as $value => $label): ?><option value="<?= $e($value) ?>"<?= ($selectedStatus ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
        <button class="button button--secondary" type="submit">Applica</button><?php if (($query ?? '') !== '' || ($selectedStatus ?? '') !== ''): ?><a class="button button--ghost" href="/ui/shipments">Azzera</a><?php endif; ?>
    </form>
    <div class="table-scroll"><table class="data-table"><thead><tr><th scope="col">Spedizione</th><th scope="col">Ordine</th><th scope="col">Cliente</th><th scope="col">Corriere</th><th scope="col">Colli</th><th scope="col">Peso</th><th scope="col">Etichette</th><th scope="col">Stato</th><th scope="col">Aggiornata</th></tr></thead><tbody>
    <?php if (($shipments ?? []) === []): ?><tr><td colspan="9"><div class="empty-state"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#truck"></use></svg></span><h3>Nessuna spedizione trovata</h3><p>Le richieste GLS e BRT appariranno qui dopo la decisione logistica di HAPA.</p></div></td></tr><?php else: ?>
    <?php foreach ($shipments as $shipment): ?><tr>
        <td><strong><?= $e($shipment['external_shipment_id'] ?? 'HAPA-' . (string) $shipment['id']) ?></strong><small><?= $e($shipment['tracking_number'] ?? 'Tracking non assegnato') ?></small></td>
        <td><a href="/ui/orders/<?= $e(rawurlencode($shipment['order_number'])) ?>"><?= $e($shipment['order_number']) ?></a><small><?= $e($shipment['order_status']) ?></small></td>
        <td><?= $e($shipment['customer_name'] ?? '—') ?><small><?= $e($shipment['customer_code'] ?? '—') ?></small></td>
        <td><?= $e($shipment['provider']) ?></td>
        <td><?= $e((string) $shipment['package_detail_count']) ?> / <?= $e((string) $shipment['packages']) ?><small>registrati / dichiarati</small></td>
        <td><?= $e($weight($shipment)) ?></td>
        <td><?= $e((string) $shipment['label_count']) ?><small><?= $e($shipment['latest_label_format'] ?? '—') ?> · <?= $e($date($shipment['latest_label_generated_at'])) ?></small></td>
        <td><span class="status-badge status-badge--<?= $e($tone($shipment['status'])) ?>"><?= $e($shipment['status']) ?></span></td><td><?= $e($date($shipment['updated_at'])) ?></td>
    </tr><?php endforeach; ?><?php endif; ?>
    </tbody></table></div>
</section>

<div class="inline-notice inline-notice--info" role="note"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg><span>I riferimenti privati delle etichette non sono esposti. Stampa e ristampa saranno abilitate soltanto tramite un endpoint autorizzato che verifica checksum e permessi.</span></div>
