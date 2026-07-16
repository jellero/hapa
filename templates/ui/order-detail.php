<a class="back-link" href="/ui/orders">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-left"></use></svg>
    Torna agli ordini
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
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#refresh"></use></svg>
            Riconcilia
        </button>
        <button class="button button--primary" type="button" disabled>Azioni ordine</button>
    </div>
</header>

<section class="summary-grid" aria-label="Riepilogo ordine">
    <article><span>Canale</span><strong>—</strong><small>Connettore non disponibile</small></article>
    <article><span>Totale righe</span><strong>—</strong><small>Repository non collegato</small></article>
    <article><span>Ultimo aggiornamento</span><strong>—</strong><small>Nessun evento caricato</small></article>
    <article><span>Versione</span><strong>—</strong><small>Optimistic locking pianificato</small></article>
</section>

<nav class="tabs" aria-label="Sezioni dettaglio ordine">
    <a class="is-active" href="#overview" aria-current="page">Panoramica</a>
    <a href="#lines">Righe</a>
    <a href="#deliveries">Delivery</a>
    <a href="#audit">Audit</a>
</nav>

<div class="detail-grid" id="overview">
    <div class="detail-grid__main">
        <section class="panel" id="lines" aria-labelledby="order-lines-title">
            <div class="panel__header">
                <div>
                    <p class="eyebrow">Contenuto</p>
                    <h2 id="order-lines-title">Righe ordine</h2>
                </div>
            </div>
            <div class="table-scroll">
                <table class="data-table data-table--compact">
                    <thead><tr><th scope="col">SKU / EAN</th><th scope="col">Ordinato</th><th scope="col">Disponibile</th><th scope="col">Da spedire</th><th scope="col">Stato</th></tr></thead>
                    <tbody><tr><td colspan="5"><div class="empty-state empty-state--compact"><span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#box"></use></svg></span><div><h3>Nessuna riga caricata</h3><p>L’ordine “<?= $e($orderId) ?>” non è collegato a un record del dominio.</p></div></div></td></tr></tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="deliveries" aria-labelledby="deliveries-title">
            <div class="panel__header">
                <div><p class="eyebrow">Integrazioni</p><h2 id="deliveries-title">Delivery esterne</h2></div>
            </div>
            <div class="empty-state empty-state--compact">
                <span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#automation"></use></svg></span>
                <div><h3>Nessun tentativo registrato</h3><p>Richieste, risposte e retry appariranno qui con i dati sensibili redatti.</p></div>
            </div>
        </section>
    </div>

    <aside class="detail-grid__aside">
        <section class="panel" aria-labelledby="address-title">
            <div class="panel__header"><div><p class="eyebrow">Destinazione</p><h2 id="address-title">Indirizzo</h2></div></div>
            <div class="placeholder-lines" aria-label="Indirizzo non disponibile"><span></span><span></span><span></span><span></span></div>
            <p class="muted-copy">I dati personali saranno visibili solo agli operatori autorizzati e per il tempo necessario.</p>
        </section>

        <section class="panel" id="audit" aria-labelledby="timeline-title">
            <div class="panel__header"><div><p class="eyebrow">Cronologia</p><h2 id="timeline-title">Eventi ordine</h2></div></div>
            <div class="timeline-empty">
                <span aria-hidden="true"></span>
                <div><strong>Nessun evento</strong><p>Le transizioni di dominio saranno ordinate cronologicamente.</p></div>
            </div>
        </section>
    </aside>
</div>
