<a class="back-link" href="/ui/customers">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-left"></use></svg>
    Torna ai clienti
</a>

<header class="page-header page-header--detail">
    <div>
        <div class="detail-title-row">
            <p class="eyebrow"><?= $e($eyebrow) ?></p>
            <span class="status-badge status-badge--neutral">Non disponibile</span>
        </div>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <button class="button button--secondary" type="button" disabled>
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#edit"></use></svg>
            Modifica
        </button>
        <button class="button button--primary" type="button" disabled>Azioni cliente</button>
    </div>
</header>

<section class="summary-grid" aria-label="Riepilogo cliente">
    <article><span>Codice richiesto</span><strong><?= $e($customerId) ?></strong><small>Record non ancora caricato</small></article>
    <article><span>Stato</span><strong>—</strong><small>Repository non collegato</small></article>
    <article><span>Ordini collegati</span><strong>—</strong><small>Query applicativa non disponibile</small></article>
    <article><span>Ultimo ordine</span><strong>—</strong><small>Nessuna cronologia caricata</small></article>
</section>

<nav class="tabs" aria-label="Sezioni scheda cliente">
    <a class="is-active" href="#profile" aria-current="page">Profilo</a>
    <a href="#identities">Identità esterne</a>
    <a href="#addresses">Indirizzi</a>
    <a href="#customer-orders">Ordini</a>
</nav>

<div class="detail-grid" id="profile">
    <div class="detail-grid__main">
        <section class="panel" aria-labelledby="customer-profile-title">
            <div class="panel__header">
                <div>
                    <p class="eyebrow">Anagrafica canonica</p>
                    <h2 id="customer-profile-title">Dati cliente</h2>
                    <p>I dati condivisi dai canali confluiscono in un profilo controllato, senza fusioni automatiche basate sulla sola email.</p>
                </div>
            </div>
            <dl class="settings-list">
                <div><dt>Tipo cliente</dt><dd>Non disponibile</dd></div>
                <div><dt>Nome visualizzato</dt><dd>Non disponibile</dd></div>
                <div><dt>Nome e cognome</dt><dd>Non disponibile</dd></div>
                <div><dt>Ragione sociale</dt><dd>Non disponibile</dd></div>
                <div><dt>Identificativi fiscali</dt><dd>Non disponibili</dd></div>
                <div><dt>Lingua</dt><dd>Non disponibile</dd></div>
            </dl>
        </section>

        <section class="panel" id="identities" aria-labelledby="customer-identities-title">
            <div class="panel__header">
                <div>
                    <p class="eyebrow">Riconciliazione</p>
                    <h2 id="customer-identities-title">Identità esterne</h2>
                    <p>Amazon, eMAG, Temu, IBS e futuro account B2C sono distinti per account e identificativo sorgente.</p>
                </div>
            </div>
            <div class="empty-state empty-state--compact">
                <span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#integration"></use></svg></span>
                <div><h3>Nessuna identità caricata</h3><p>Le associazioni appariranno dopo il collegamento dei repository e delle policy di riconciliazione.</p></div>
            </div>
        </section>

        <section class="panel" id="customer-orders" aria-labelledby="customer-orders-title">
            <div class="panel__header">
                <div><p class="eyebrow">Storico</p><h2 id="customer-orders-title">Ordini collegati</h2></div>
            </div>
            <div class="table-scroll">
                <table class="data-table data-table--compact">
                    <thead><tr><th scope="col">Ordine</th><th scope="col">Origine</th><th scope="col">Data</th><th scope="col">Stato</th></tr></thead>
                    <tbody><tr><td colspan="4"><div class="empty-state empty-state--compact"><span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#orders"></use></svg></span><div><h3>Nessun ordine caricato</h3><p>Lo storico sarà esposto tramite una query paginata e autorizzata.</p></div></div></td></tr></tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="detail-grid__aside" aria-label="Dati complementari cliente">
        <section class="panel" aria-labelledby="customer-contact-title">
            <div class="panel__header"><div><p class="eyebrow">Recapiti</p><h2 id="customer-contact-title">Contatti</h2></div></div>
            <dl class="settings-list">
                <div><dt>Email</dt><dd>Non disponibile</dd></div>
                <div><dt>Telefono</dt><dd>Non disponibile</dd></div>
            </dl>
        </section>

        <section class="panel" id="addresses" aria-labelledby="customer-addresses-title">
            <div class="panel__header"><div><p class="eyebrow">Destinazioni</p><h2 id="customer-addresses-title">Indirizzi</h2></div></div>
            <div class="empty-state empty-state--compact">
                <span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#customer"></use></svg></span>
                <div><h3>Nessun indirizzo caricato</h3><p>Spedizione e fatturazione potranno avere indirizzi predefiniti distinti.</p></div>
            </div>
        </section>

        <div class="inline-notice">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg>
            <span>Dati personali, storico e identificativi fiscali saranno visibili soltanto per ruolo, finalità e periodo di conservazione autorizzati.</span>
        </div>
    </aside>
</div>
