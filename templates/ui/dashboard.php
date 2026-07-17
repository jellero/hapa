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
                        <svg class="icon"><use href="/assets/icons.svg#<?= $e($stream['icon']) ?>"></use></svg>
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
                <div><strong>Anagrafica cliente</strong><small>Aggregato, repository e query paginata</small></div>
            </li>
            <li>
                <span>02</span>
                <div><strong>Sicurezza operativa</strong><small>Autenticazione, ruoli, CSRF e audit</small></div>
            </li>
            <li>
                <span>03</span>
                <div><strong>Prima integrazione</strong><small>SellRapido/marketplace → HAPA → Space</small></div>
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
        <div class="workstream-list">
            <article class="workstream-item"><span class="workstream-item__icon"><svg class="icon"><use href="/assets/icons.svg#inbox"></use></svg></span><div class="workstream-item__copy"><strong>Inbox fallite</strong><span>Lag più vecchio: <?= $e((string) ($runtime['lag_seconds']['inbox_failed_oldest'] ?? 0)) ?> secondi</span></div><span class="status-badge status-badge--<?= ($runtime['inbox']['failed'] ?? 0) > 0 ? 'danger' : 'success' ?>"><?= $e((string) ($runtime['inbox']['failed'] ?? 0)) ?></span></article>
            <article class="workstream-item"><span class="workstream-item__icon"><svg class="icon"><use href="/assets/icons.svg#automation"></use></svg></span><div class="workstream-item__copy"><strong>Outbox pendenti / dead</strong><span>Lag pubblicazione: <?= $e((string) ($runtime['lag_seconds']['outbox_due_oldest'] ?? 0)) ?> secondi</span></div><span class="status-badge status-badge--<?= ($runtime['outbox']['dead'] ?? 0) > 0 ? 'danger' : 'success' ?>"><?= $e((string) (($runtime['outbox']['pending'] ?? 0) + ($runtime['outbox']['retry'] ?? 0))) ?> / <?= $e((string) ($runtime['outbox']['dead'] ?? 0)) ?></span></article>
            <article class="workstream-item"><span class="workstream-item__icon"><svg class="icon"><use href="/assets/icons.svg#audit"></use></svg></span><div class="workstream-item__copy"><strong>Audit ultime 24 ore</strong><span>Accessi e modifiche operative registrate</span></div><span class="status-badge status-badge--info"><?= $e((string) ($runtime['audit_last_24h'] ?? 0)) ?></span></article>
        </div>
    </section>
</div>
