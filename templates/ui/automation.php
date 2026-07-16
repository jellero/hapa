<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="button button--secondary" href="/health/ready" target="_blank" rel="noopener">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#pulse"></use></svg>
            Verifica runtime
        </a>
        <button class="button button--primary" type="button" disabled title="L’attivazione richiede credenziali e adapter del provider">
            Attiva job
        </button>
    </div>
</header>

<section class="metric-grid" aria-label="Stato runtime automazioni">
    <article class="metric-card metric-card--success">
        <div class="metric-card__top"><span>Transactional outbox</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Pronta</strong>
        <p>Scrittura atomica con l’anagrafica ordine e idempotency key.</p>
    </article>
    <article class="metric-card metric-card--success">
        <div class="metric-card__top"><span>Worker concorrente</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Pronto</strong>
        <p>Claim SKIP LOCKED, worker identity, retry, backoff e dead letter.</p>
    </article>
    <article class="metric-card metric-card--info">
        <div class="metric-card__top"><span>Frequenza flusso</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>10 min</strong>
        <p>I sette job sono registrati e restano spenti fino al collegamento degli adapter.</p>
    </article>
    <article class="metric-card metric-card--warning">
        <div class="metric-card__top"><span>Decisioni parziali</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong>Manuali</strong>
        <p>Il worker non decide automaticamente quantità da spedire o annullare.</p>
    </article>
</section>

<section class="panel data-panel" aria-labelledby="automation-plan-title">
    <div class="panel__header">
        <div>
            <p class="eyebrow">Piano operativo</p>
            <h2 id="automation-plan-title">Automazioni ordini e spedizioni</h2>
        </div>
        <span class="status-badge status-badge--info">7 job censiti</span>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Automazione</th>
                    <th scope="col">Flusso</th>
                    <th scope="col">Frequenza</th>
                    <th scope="col">Controllo</th>
                    <th scope="col">Stato</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($automations as $automation): ?>
                    <tr>
                        <td><strong><?= $e($automation->name) ?></strong></td>
                        <td><?= $e($automation->flow) ?></td>
                        <td><?= $e($automation->frequency) ?></td>
                        <td><?= $e($automation->control) ?></td>
                        <td><span class="status-badge status-badge--<?= $e($automation->tone) ?>"><?= $e($automation->status) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="dashboard-grid">
    <section class="panel panel--span-2" aria-labelledby="runtime-flow-title">
        <div class="panel__header">
            <div>
                <p class="eyebrow">Runtime reale</p>
                <h2 id="runtime-flow-title">Cosa esegue oggi HAPA</h2>
            </div>
        </div>
        <div class="workstream-list">
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#orders"></use></svg></span>
                <div class="workstream-item__copy"><strong>Salva ordine e intenzione</strong><span>Ordine PostgreSQL e messaggi outbox nello stesso commit</span></div>
                <span class="status-badge status-badge--success">Atomico</span>
            </article>
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#automation"></use></svg></span>
                <div class="workstream-item__copy"><strong>Elabora il batch</strong><span>Comando bin/console automation:run, sicuro per cron o orchestratore</span></div>
                <span class="status-badge status-badge--success">Eseguibile</span>
            </article>
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#audit"></use></svg></span>
                <div class="workstream-item__copy"><strong>Registra audit ordine</strong><span>Handler interno idempotente per creazione e variazioni dell’ordine</span></div>
                <span class="status-badge status-badge--success">Collegato</span>
            </article>
        </div>
    </section>

    <aside class="panel" aria-labelledby="activation-title">
        <div class="panel__header"><div><p class="eyebrow">Attivazione controllata</p><h2 id="activation-title">Perché i provider sono spenti</h2></div></div>
        <ol class="priority-list">
            <li><span>01</span><div><strong>Credenziali reali</strong><small>SellRapido, Space, GLS e BRT</small></div></li>
            <li><span>02</span><div><strong>Contratti verificati</strong><small>Payload, idempotenza e limiti provider</small></div></li>
            <li><span>03</span><div><strong>Test in sandbox</strong><small>Riconciliazione prima del traffico reale</small></div></li>
            <li><span>04</span><div><strong>Etichetta e tracking</strong><small>Handler event-driven GLS/BRT dopo il picking</small></div></li>
        </ol>
    </aside>
</div>
