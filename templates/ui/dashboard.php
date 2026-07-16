<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="button button--secondary" href="/health/live" target="_blank" rel="noopener">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#pulse"></use></svg>
            Verifica liveness
        </a>
        <button class="button button--primary" type="button" disabled title="Disponibile dopo l’attivazione del repository ordini">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#plus"></use></svg>
            Nuovo ordine
        </button>
    </div>
</header>

<section class="metric-grid" aria-label="Indicatori principali">
    <?php foreach ($metrics as $metric): ?>
        <article class="metric-card metric-card--<?= $e($metric['tone']) ?>">
            <div class="metric-card__top">
                <span><?= $e($metric['label']) ?></span>
                <span class="metric-card__signal" aria-hidden="true"></span>
            </div>
            <strong><?= $e($metric['value']) ?></strong>
            <p><?= $e($metric['detail']) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid">
    <section class="panel panel--span-2" aria-labelledby="workstreams-title">
        <div class="panel__header">
            <div>
                <p class="eyebrow">Flusso end-to-end</p>
                <h2 id="workstreams-title">Stato delle capacità</h2>
            </div>
            <a class="text-link" href="/ui/integrations">Tutte le integrazioni <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-right"></use></svg></a>
        </div>

        <div class="workstream-list">
            <?php foreach ($workstreams as $stream): ?>
                <article class="workstream-item">
                    <span class="workstream-item__icon" aria-hidden="true">
                        <svg class="icon"><use href="/assets/icons.svg#integration"></use></svg>
                    </span>
                    <div class="workstream-item__copy">
                        <strong><?= $e($stream['label']) ?></strong>
                        <span><?= $e($stream['detail']) ?></span>
                    </div>
                    <span class="status-badge status-badge--<?= $e($stream['tone']) ?>"><?= $e($stream['status']) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="panel" aria-labelledby="priorities-title">
        <div class="panel__header">
            <div>
                <p class="eyebrow">Roadmap</p>
                <h2 id="priorities-title">Prossimi gate</h2>
            </div>
        </div>
        <ol class="priority-list">
            <li>
                <span>01</span>
                <div><strong>Composition root</strong><small>Container DI e configurazioni tipizzate</small></div>
            </li>
            <li>
                <span>02</span>
                <div><strong>Dominio ordine</strong><small>Aggregato, transizioni e repository</small></div>
            </li>
            <li>
                <span>03</span>
                <div><strong>Prima integrazione</strong><small>Discovery e vertical slice verso Space</small></div>
            </li>
        </ol>
        <a class="button button--ghost button--wide" href="/ui/integrations">Esplora il piano integrazioni</a>
    </aside>

    <section class="panel panel--span-3" aria-labelledby="exceptions-title">
        <div class="panel__header">
            <div>
                <p class="eyebrow">Attenzione operativa</p>
                <h2 id="exceptions-title">Eccezioni e attività recenti</h2>
            </div>
            <a class="text-link" href="/ui/audit">Apri audit <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-right"></use></svg></a>
        </div>
        <div class="empty-state empty-state--compact">
            <span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#inbox"></use></svg></span>
            <div>
                <h3>Nessuna attività da mostrare</h3>
                <p>Il feed si popolerà con eventi di dominio, retry, riconciliazioni e azioni degli operatori.</p>
            </div>
        </div>
    </section>
</div>
