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
?>
<a class="back-link" href="/ui/customers"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-left"></use></svg>Torna ai clienti</a>

<?php if (($customer ?? null) === null): ?>
<header class="page-header page-header--detail"><div><p class="eyebrow"><?= $e($eyebrow) ?></p><h1><?= $e($title) ?></h1><p class="page-header__description">Il cliente richiesto non è presente nell’anagrafica HAPA.</p></div></header>
<div class="empty-state"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#customer"></use></svg></span><h2>Cliente non trovato</h2><p>Codice richiesto: <?= $e($customerId) ?></p></div>
<?php else: ?>
<?php $statusTone = $customer['status'] === 'active' ? 'success' : ($customer['status'] === 'inactive' ? 'warning' : 'neutral'); ?>
<header class="page-header page-header--detail">
    <div><div class="detail-title-row"><p class="eyebrow"><?= $e($eyebrow) ?></p><span class="status-badge status-badge--<?= $e($statusTone) ?>"><?= $e($customer['status']) ?></span></div><h1><?= $e($title) ?></h1><p class="page-header__description"><?= $e($description) ?></p></div>
</header>

<section class="summary-grid" aria-label="Riepilogo cliente">
    <article><span>Codice cliente</span><strong><?= $e($customer['customer_code']) ?></strong><small>Versione <?= $e((string) $customer['version']) ?></small></article>
    <article><span>Tipo</span><strong><?= $e($customer['customer_type']) ?></strong><small><?= $e($customer['locale']) ?></small></article>
    <article><span>Ordini collegati</span><strong><?= $e((string) $customer['order_count']) ?></strong><small>Storico persistito</small></article>
    <article><span>Ultimo ordine</span><strong><?= $e($date($customer['last_order_at'])) ?></strong><small>Aggiornato <?= $e($date($customer['updated_at'])) ?></small></article>
</section>

<nav class="tabs" aria-label="Sezioni scheda cliente"><a class="is-active" href="#profile">Profilo</a><a href="#identities">Identità esterne</a><a href="#addresses">Indirizzi</a><a href="#customer-orders">Ordini</a><a href="#customer-history">Storico</a></nav>

<div class="detail-grid" id="profile">
    <div class="detail-grid__main">
        <section class="panel" aria-labelledby="customer-profile-title"><div class="panel__header"><div><p class="eyebrow">Anagrafica canonica</p><h2 id="customer-profile-title">Dati cliente</h2></div></div><dl class="settings-list">
            <div><dt>Nome visualizzato</dt><dd><?= $e($customer['display_name']) ?></dd></div>
            <div><dt>Nome e cognome</dt><dd><?= $e(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '—') ?></dd></div>
            <div><dt>Ragione sociale</dt><dd><?= $e($customer['company_name'] ?? '—') ?></dd></div>
            <div><dt>Codice fiscale</dt><dd><?= $e($customer['tax_identifier'] ?? '—') ?></dd></div>
            <div><dt>Partita IVA</dt><dd><?= $e($customer['vat_number'] ?? '—') ?></dd></div>
        </dl></section>

        <section class="panel" id="identities" aria-labelledby="customer-identities-title"><div class="panel__header"><div><p class="eyebrow">Riconciliazione</p><h2 id="customer-identities-title">Identità esterne</h2></div></div>
            <?php if ($customer['identities'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessuna identità collegata</h3><p>Il profilo non è ancora associato a un account marketplace.</p></div></div><?php else: ?>
            <div class="table-scroll"><table class="data-table data-table--compact"><thead><tr><th>Sorgente</th><th>Account</th><th>ID esterno</th><th>Aggiornata</th></tr></thead><tbody><?php foreach ($customer['identities'] as $identity): ?><tr><td><?= $e($identity['source']) ?></td><td><?= $e($identity['account_reference']) ?></td><td><code><?= $e($identity['external_customer_id']) ?></code></td><td><?= $e($date($identity['updated_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>

        <section class="panel" id="customer-orders" aria-labelledby="customer-orders-title"><div class="panel__header"><div><p class="eyebrow">Storico commerciale</p><h2 id="customer-orders-title">Ordini collegati</h2></div></div>
            <?php if ($customer['orders'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessun ordine collegato</h3><p>Gli ordini importati compariranno qui senza perdere lo storico del cliente.</p></div></div><?php else: ?>
            <div class="table-scroll"><table class="data-table data-table--compact"><thead><tr><th>Ordine</th><th>Origine</th><th>Data</th><th>Stato</th><th>Totale</th></tr></thead><tbody><?php foreach ($customer['orders'] as $order): ?><tr><td><strong><?= $e($order['order_number']) ?></strong></td><td><?= $e($order['marketplace_code'] ?? $order['origin']) ?></td><td><?= $e($date($order['ordered_at'])) ?></td><td><span class="status-badge status-badge--neutral"><?= $e($order['status']) ?></span></td><td><?= $e($money($order['grand_total_minor'], $order['currency'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>

        <section class="panel" id="customer-history" aria-labelledby="customer-history-title"><div class="panel__header"><div><p class="eyebrow">Versioni append-only</p><h2 id="customer-history-title">Storico anagrafico</h2></div></div>
            <?php if ($customer['history'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessuna versione storica</h3><p>Le future creazioni, rettifiche e riconciliazioni saranno mostrate qui.</p></div></div><?php else: ?>
            <div class="table-scroll"><table class="data-table data-table--compact"><thead><tr><th>Versione</th><th>Modifica</th><th>Data</th><th>Attore</th><th>Dettaglio</th></tr></thead><tbody><?php foreach ($customer['history'] as $entry): ?><tr><td><?= $e((string) $entry['version']) ?></td><td><code><?= $e($entry['change_type']) ?></code></td><td><?= $e($date($entry['occurred_at'])) ?></td><td><?= $e($entry['actor_id'] ?? 'sistema') ?></td><td><details><summary>Mostra snapshot</summary><pre><?= $e((string) json_encode($entry['snapshot'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
    </div>

    <aside class="detail-grid__aside" aria-label="Dati complementari cliente">
        <section class="panel" aria-labelledby="customer-contact-title"><div class="panel__header"><div><p class="eyebrow">Recapiti</p><h2 id="customer-contact-title">Contatti</h2></div></div><dl class="settings-list"><div><dt>Email</dt><dd><?= $e($customer['email'] ?? '—') ?></dd></div><div><dt>Telefono</dt><dd><?= $e($customer['phone'] ?? '—') ?></dd></div></dl></section>
        <section class="panel" id="addresses" aria-labelledby="customer-addresses-title"><div class="panel__header"><div><p class="eyebrow">Destinazioni</p><h2 id="customer-addresses-title">Indirizzi</h2></div></div>
            <?php if ($customer['addresses'] === []): ?><div class="empty-state empty-state--compact"><div><h3>Nessun indirizzo</h3><p>Non sono presenti destinazioni persistite.</p></div></div><?php else: ?><?php foreach ($customer['addresses'] as $address): ?><article class="workstream-item"><div class="workstream-item__copy"><strong><?= $e($address['label']) ?><?php if ($address['is_default_shipping']): ?> · spedizione<?php endif; ?><?php if ($address['is_default_billing']): ?> · fatturazione<?php endif; ?></strong><span><?= $e($address['recipient']) ?><br><?= $e($address['address_line1']) ?><?= $address['address_line2'] === null ? '' : ' · ' . $e($address['address_line2']) ?><br><?= $e($address['postal_code']) ?> <?= $e($address['city']) ?> <?= $e($address['province'] ?? '') ?> · <?= $e($address['country_code']) ?></span></div></article><?php endforeach; ?><?php endif; ?>
        </section>
        <div class="inline-notice"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg><span>Dati personali visibili soltanto agli utenti autorizzati alla consultazione clienti.</span></div>
    </aside>
</div>
<?php endif; ?>
