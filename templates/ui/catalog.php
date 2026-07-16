<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <button class="button button--primary" type="button" disabled title="Richiede autenticazione, autorizzazione e caso d’uso di gestione ricarichi">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#plus"></use></svg>
            Nuova regola di ricarico
        </button>
    </div>
</header>

<div class="inline-notice inline-notice--info" role="note">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#info"></use></svg>
    <div>
        <strong>Anagrafica prodotti HAPA</strong>
        <span>Space sincronizza prezzo e stock del prodotto; HAPA conserva il dato e permette agli operatori di gestire dall’interfaccia le regole di ricarico e il prezzo finale.</span>
    </div>
</div>

<section class="metric-grid" aria-label="Stato anagrafica prodotti">
    <article class="metric-card metric-card--info">
        <div class="metric-card__top"><span>Prezzo Space</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Dato sorgente</strong>
        <p>Importo in unità minori, valuta, versione e data di sincronizzazione.</p>
    </article>
    <article class="metric-card metric-card--info">
        <div class="metric-card__top"><span>Stock Space</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Dato sorgente</strong>
        <p>Quantità disponibile associata al prodotto e aggiornata tramite eventi versionati.</p>
    </article>
    <article class="metric-card metric-card--success">
        <div class="metric-card__top"><span>Regole di ricarico</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Gestione HAPA</strong>
        <p>Percentuale, importo o prezzo fisso con ambito, priorità e soglie.</p>
    </article>
    <article class="metric-card metric-card--warning">
        <div class="metric-card__top"><span>Pubblicazione</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Servizio esterno</strong>
        <p>hapa-automation pubblicherà le offerte tramite RabbitMQ e adapter provider.</p>
    </article>
</section>

<div class="dashboard-grid">
    <section class="panel panel--span-2" aria-labelledby="catalog-flow-title">
        <div class="panel__header">
            <div>
                <p class="eyebrow">Flusso del prodotto</p>
                <h2 id="catalog-flow-title">Da Space all’offerta marketplace</h2>
            </div>
            <span class="status-badge status-badge--info">Consistenza eventuale</span>
        </div>
        <div class="workstream-list">
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#box"></use></svg></span>
                <div class="workstream-item__copy"><strong>1. Aggiorna il prodotto</strong><span>hapa-automation acquisisce da Space e HAPA applica SKU, prezzo, stock e versione sorgente</span></div>
                <span class="status-badge status-badge--neutral">Da collegare</span>
            </article>
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#settings"></use></svg></span>
                <div class="workstream-item__copy"><strong>2. Gestisci il ricarico</strong><span>L’operatore configura in HAPA la regola commerciale e verifica il prezzo finale</span></div>
                <span class="status-badge status-badge--success">Motore pronto</span>
            </article>
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#integration"></use></svg></span>
                <div class="workstream-item__copy"><strong>3. Pubblica e riconcilia</strong><span>HAPA produce l’intenzione; hapa-automation esegue la chiamata e restituisce l’esito</span></div>
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
            <p class="eyebrow">Anagrafica prodotti</p>
            <h2 id="catalog-items-title">Prodotti, prezzo e stock sincronizzati</h2>
        </div>
        <span class="status-badge status-badge--neutral">Read model da collegare</span>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th scope="col">SKU</th><th scope="col">Prodotto</th><th scope="col">Prezzo Space</th><th scope="col">Stock Space</th><th scope="col">Regola ricarico</th><th scope="col">Prezzo finale</th><th scope="col">Sincronizzato</th><th scope="col">Offerte</th></tr></thead>
            <tbody><tr><td colspan="8"><div class="empty-state"><span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#box"></use></svg></span><h3>Nessun prodotto sincronizzato</h3><p>I prodotti compariranno dopo il collegamento del consumer RabbitMQ, del repository e del read model autorizzato.</p></div></td></tr></tbody>
        </table>
    </div>
</section>
