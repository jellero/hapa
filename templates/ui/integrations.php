<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <button class="button button--primary" type="button" disabled title="Disponibile dopo l’introduzione delle configurazioni tipizzate">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#plus"></use></svg>
            Aggiungi account
        </button>
    </div>
</header>

<div class="inline-notice inline-notice--warning" role="note">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#alert"></use></svg>
    <div>
        <strong>Confini di integrazione espliciti</strong>
        <span>Per ogni account-canale resta attivo un solo percorso per ordini e offerte; Space alimenta prezzo e stock base, mentre HAPA pubblica ricarichi e quantità vendibili senza sovrascrivere la sorgente.</span>
    </div>
</div>

<section aria-labelledby="integrations-grid-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Portafoglio</p>
            <h2 id="integrations-grid-title">Canali, servizi e corrieri</h2>
        </div>
        <span class="section-heading__meta"><?= $e(count($integrations)) ?> integrazioni censite</span>
    </div>

    <div class="integration-grid">
        <?php foreach ($integrations as $integration): ?>
            <article class="integration-card">
                <div class="integration-card__top">
                    <span class="integration-logo integration-logo--<?= $e($integration['code']) ?>" aria-hidden="true">
                        <?= $e(strtoupper(substr($integration['name'], 0, 2))) ?>
                    </span>
                    <span class="status-badge status-badge--<?= $e($integration['tone']) ?>"><?= $e($integration['status']) ?></span>
                </div>
                <div class="integration-card__copy">
                    <h3><?= $e($integration['name']) ?></h3>
                    <p><?= $e($integration['kind']) ?></p>
                </div>
                <div class="integration-card__meta">
                    <span><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#integration"></use></svg> 0 account configurati</span>
                </div>
                <button class="button button--ghost button--wide" type="button" disabled>Configura</button>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel discovery-panel" aria-labelledby="discovery-title">
    <div class="panel__header">
        <div>
            <p class="eyebrow">Discovery</p>
            <h2 id="discovery-title">Gate prima dell’attivazione</h2>
        </div>
        <span class="section-heading__meta">Riferimenti: MARKETPLACES.md e CARRIERS.md</span>
    </div>
    <ol class="gate-grid">
        <li><span>01</span><strong>Specifiche e account test</strong><small>Contratto tecnico, ambiente prova e permessi reali</small></li>
        <li><span>02</span><strong>Capacità e limiti</strong><small>Operazioni, quote, paginazione e dati personali</small></li>
        <li><span>03</span><strong>Conformità adapter</strong><small>Fake, errori tipizzati e test condivisi</small></li>
        <li><span>04</span><strong>Pilot controllato</strong><small>Un account-canale, metriche e arresto rapido</small></li>
    </ol>
</section>
