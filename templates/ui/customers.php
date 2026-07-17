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
?>
<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
</header>

<section class="panel data-panel" aria-labelledby="customers-results-title">
    <div class="panel__header">
        <div><p class="eyebrow">Profili canonici</p><h2 id="customers-results-title">Anagrafica clienti</h2></div>
        <span class="section-heading__meta"><?= $e((string) count($customers ?? [])) ?> risultati visualizzati</span>
    </div>
    <form class="data-toolbar" method="get" action="/ui/customers" role="search">
        <label class="search-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg>
            <span class="sr-only">Cerca cliente</span>
            <input type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Codice, nome, email o identità esterna">
        </label>
        <div class="toolbar-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#filter"></use></svg>
            <label class="sr-only" for="customer-status">Filtra per stato</label>
            <select id="customer-status" name="status">
                <option value="">Tutti gli stati</option>
                <?php foreach (['active' => 'Attivi', 'inactive' => 'Inattivi', 'archived' => 'Archiviati'] as $value => $label): ?>
                    <option value="<?= $e($value) ?>"<?= ($selectedStatus ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button--secondary" type="submit">Applica</button>
        <?php if (($query ?? '') !== '' || ($selectedStatus ?? '') !== ''): ?><a class="button button--ghost" href="/ui/customers">Azzera</a><?php endif; ?>
    </form>

    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Codice</th><th>Cliente</th><th>Contatti</th><th>Origini</th><th>Ordini</th><th>Ultimo ordine</th><th>Stato</th><th>Azioni</th></tr></thead>
            <tbody>
            <?php if (($customers ?? []) === []): ?>
                <tr><td colspan="8"><div class="empty-state"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#customer"></use></svg></span><h3>Nessun cliente trovato</h3><p>I profili vengono creati dagli ordini importati o dai futuri casi d’uso amministrativi.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                    <?php $tone = $customer['status'] === 'active' ? 'success' : ($customer['status'] === 'inactive' ? 'warning' : 'neutral'); ?>
                    <tr>
                        <td><strong><?= $e($customer['customer_code']) ?></strong><small>v<?= $e((string) $customer['version']) ?></small></td>
                        <td><?= $e($customer['display_name']) ?><small><?= $e($customer['customer_type']) ?></small></td>
                        <td><?= $e($customer['email'] ?? '—') ?><small><?= $e($customer['phone'] ?? '—') ?></small></td>
                        <td><?= $e($customer['identity_sources'] ?? '—') ?><small><?= $e((string) $customer['identity_count']) ?> identità</small></td>
                        <td><?= $e((string) $customer['order_count']) ?></td>
                        <td><?= $e($date($customer['last_order_at'])) ?></td>
                        <td><span class="status-badge status-badge--<?= $e($tone) ?>"><?= $e($customer['status']) ?></span></td>
                        <td><a class="button button--ghost" href="/ui/customers/<?= $e(rawurlencode($customer['customer_code'])) ?>">Apri scheda</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
