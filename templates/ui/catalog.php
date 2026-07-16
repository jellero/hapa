<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <button class="button button--primary" type="button" disabled title="Richiede autenticazione, autorizzazione e caso d’uso di gestione prezzi">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#plus"></use></svg>
            Nuova regola di ricarico
        </button>
    </div>
</header>

<div class="inline-notice inline-notice--info" role="note">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#info"></use></svg>
    <div>
        <strong>Ownership dei dati</strong>
        <span>Space fornisce prezzo base e disponibilità fisica; HAPA applica scorta di sicurezza e ricarico; il marketplace riceve soltanto prezzo finale e quantità vendibile versionati.</span>
    </div>
</div>

<section class="metric-grid" aria-label="Stato sincronizzazione catalogo">
    <article class="metric-card metric-card--info">
        <div class="metric-card__top"><span>Space API</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Contratto pronto</strong>
        <p>Batch incrementali con cursore, versione sorgente, prezzo e disponibilità.</p>
    </article>
    <article class="metric-card metric-card--success">
        <div class="metric-card__top"><span>Motore ricarichi</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Deterministico</strong>
        <p>Percentuale, importo o prezzo fisso con priorità, minimo e massimo.</p>
    </article>
    <article class="metric-card metric-card--success">
        <div class="metric-card__top"><span>Stock vendibile</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Protetto</strong>
        <p>Disponibilità Space meno scorta di sicurezza, mai sotto zero.</p>
    </article>
    <article class="metric-card metric-card--warning">
        <div class="metric-card__top"><span>Marketplace API</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Adapter spenti</strong>
        <p>La pubblicazione richiede specifiche, credenziali e test sandbox verificati.</p>
    </article>
</section>

<div class="dashboard-grid">
    <section class="panel panel--span-2" aria-labelledby="catalog-flow-title">
        <div class="panel__header">
            <div>
                <p class="eyebrow">Flusso autorevole</p>
                <h2 id="catalog-flow-title">Da Space all’offerta marketplace</h2>
            </div>
            <span class="status-badge status-badge--info">Consistenza eventuale</span>
        </div>
        <div class="workstream-list">
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#automation"></use></svg></span>
                <div class="workstream-item__copy"><strong>1. Acquisisci il catalogo Space</strong><span>Upsert idempotente di SKU, prezzo base, quantità e versione esterna</span></div>
                <span class="status-badge status-badge--neutral">Da collegare</span>
            </article>
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#settings"></use></svg></span>
                <div class="workstream-item__copy"><strong>2. Calcola l’offerta HAPA</strong><span>Regola più specifica, soglie prezzo e sottrazione della scorta di sicurezza</span></div>
                <span class="status-badge status-badge--success">Motore pronto</span>
            </article>
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#integration"></use></svg></span>
                <div class="workstream-item__copy"><strong>3. Pubblica e riconcilia</strong><span>Un’offerta per account-canale, idempotency key e versione remota</span></div>
                <span class="status-badge status-badge--neutral">Da collegare</span>
            </article>
        </div>
    </section>

    <aside class="panel" aria-labelledby="pricing-precedence-title">
        <div class="panel__header"><div><p class="eyebrow">Precedenza</p><h2 id="pricing-precedence-title">Quale ricarico vince</h2></div></div>
        <ol class="priority-list">
            <li><span>01</span><div><strong>Marketplace + SKU</strong><small>Eccezione più specifica</small></div></li>
            <li><span>02</span><div><strong>SKU</strong><small>Regola prodotto trasversale</small></div></li>
            <li><span>03</span><div><strong>Marketplace</strong><small>Policy del singolo canale</small></div></li>
            <li><span>04</span><div><strong>Globale</strong><small>Fallback generale</small></div></li>
        </ol>
    </aside>
</div>

<section class="panel data-panel" aria-labelledby="catalog-items-title">
    <div class="panel__header">
        <div>
            <p class="eyebrow">Catalogo sincronizzato</p>
            <h2 id="catalog-items-title">Prezzi e disponibilità per SKU</h2>
        </div>
        <span class="status-badge status-badge--neutral">Read model da collegare</span>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th scope="col">SKU</th><th scope="col">Prezzo Space</th><th scope="col">Ricarico</th><th scope="col">Prezzo finale</th><th scope="col">Disponibilità Space</th><th scope="col">Vendibile</th><th scope="col">Pubblicazione</th></tr></thead>
            <tbody><tr><td colspan="7"><div class="empty-state"><span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#box"></use></svg></span><h3>Nessun articolo sincronizzato</h3><p>Schema, invarianti e contratti sono pronti. Gli articoli compariranno dopo il collegamento dell’API Space e del read model autorizzato.</p></div></td></tr></tbody>
        </table>
    </div>
</section>
