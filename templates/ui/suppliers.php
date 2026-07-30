<?php
$selected = static fn (string $actual, string $expected): string => $actual === $expected ? ' selected' : '';
$display = static fn (mixed $value): string => is_string($value) && trim($value) !== '' ? $value : '—';
?>

<header class="page-header">
    <div>
        <p class="eyebrow">Anagrafica fornitori</p>
        <h1>Fornitori Space</h1>
        <p class="page-header__description">Dati ufficiali ricevuti dall’API Space. Questa anagrafica è consultabile ma non modificabile da HAPA.</p>
    </div>
    <div class="page-header__actions"><a class="button button--secondary" href="/ui/products">Prodotti Space</a></div>
</header>

<section class="metric-grid" aria-label="Stato fornitori Space">
    <article class="metric-card metric-card--info"><div class="metric-card__top"><span>Fornitori</span></div><strong><?= $e((string) ($supplierMetrics['total'] ?? 0)) ?></strong><p>Anagrafiche ricevute da Space.</p></article>
    <article class="metric-card metric-card--success"><div class="metric-card__top"><span>Attivi</span></div><strong><?= $e((string) ($supplierMetrics['active'] ?? 0)) ?></strong><p>Fornitori utilizzabili nel feed.</p></article>
    <article class="metric-card metric-card--warning"><div class="metric-card__top"><span>Disattivati</span></div><strong><?= $e((string) ($supplierMetrics['inactive'] ?? 0)) ?></strong><p>Eliminati o disabilitati nella sorgente.</p></article>
    <article class="metric-card"><div class="metric-card__top"><span>Paesi</span></div><strong><?= $e((string) ($supplierMetrics['countries'] ?? 0)) ?></strong><p>Paesi distinti censiti.</p></article>
</section>

<section class="panel">
    <div class="panel__header"><div><p class="eyebrow">Ricerca</p><h2>Consulta l’anagrafica</h2></div><span class="status-badge status-badge--neutral">Sola lettura</span></div>
    <form class="integration-create__form" method="get" action="/ui/suppliers">
        <div class="integration-create__grid">
            <div class="field integration-create__wide"><label for="supplier-query">ID, ragione sociale, codice, città o paese</label><input id="supplier-query" type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Cerca un fornitore Space"></div>
            <div class="field"><label for="supplier-status">Stato</label><select id="supplier-status" name="status"><option value="">Tutti</option><option value="active"<?= $selected((string) ($selectedStatus ?? ''), 'active') ?>>Attivi</option><option value="inactive"<?= $selected((string) ($selectedStatus ?? ''), 'inactive') ?>>Disattivati</option></select></div>
        </div>
        <div class="integration-create__footer"><span><?= $e((string) count($suppliers ?? [])) ?> fornitori visualizzati.</span><div><a class="button button--ghost" href="/ui/suppliers">Azzera filtri</a> <button class="button button--primary" type="submit">Cerca</button></div></div>
    </form>
</section>

<section class="panel">
    <div class="panel__header"><div><p class="eyebrow">Risultati</p><h2>Elenco fornitori</h2></div></div>
    <?php if (($suppliers ?? []) === []): ?>
        <div class="empty-state"><h3>Nessun fornitore disponibile</h3><p>Avvia la sincronizzazione Space oppure modifica i filtri.</p></div>
    <?php else: ?>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>ID Space</th><th>Fornitore</th><th>Sede</th><th>Valuta</th><th>Consegna</th><th>Precisione</th><th>Chiusura ordini</th><th>Stato</th><th>Ultimo dato</th></tr></thead>
            <tbody><?php foreach ($suppliers as $supplier): ?><tr>
                <td><strong>#<?= $e($supplier['space_supplier_id']) ?></strong></td>
                <td><strong><?= $e($display($supplier['legal_name'])) ?></strong><br><small><?= $e($display($supplier['code'])) ?></small></td>
                <td><?= $e(trim($display($supplier['city']) . ' ' . $display($supplier['province']), ' —')) ?><br><small><?= $e($display($supplier['country'])) ?></small></td>
                <td><?= $e($display($supplier['currency'])) ?></td>
                <td><?= $supplier['delivery_days'] === null ? '—' : $e((string) $supplier['delivery_days']) . ' gg' ?></td>
                <td><?= $e($supplier['precision_score'] === null ? '—' : (string) $supplier['precision_score']) ?></td>
                <td><?= $e($display($supplier['closing_order'])) ?></td>
                <td><span class="status-badge status-badge--<?= $supplier['active'] ? 'success' : 'warning' ?>"><?= $supplier['active'] ? 'Attivo' : 'Disattivato' ?></span></td>
                <td><?= $e($display($supplier['source_observed_at'])) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
