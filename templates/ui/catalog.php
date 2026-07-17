<?php
$formatMoney = static fn (?int $minor, ?string $currency): string => $minor === null
    ? '—'
    : number_format($minor / 100, 2, ',', '.') . ' ' . ($currency ?? 'EUR');
$formatAge = static function (?int $seconds): string {
    if ($seconds === null) {
        return 'Mai sincronizzato';
    }
    if ($seconds < 60) {
        return 'Meno di un minuto fa';
    }
    if ($seconds < 3600) {
        return sprintf('%d min fa', intdiv($seconds, 60));
    }
    if ($seconds < 86400) {
        return sprintf('%d h fa', intdiv($seconds, 3600));
    }

    return sprintf('%d g fa', intdiv($seconds, 86400));
};
?>
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
        <strong><?= $e((string) ($catalogMetrics['total'] ?? 0)) ?></strong>
        <p>Prodotti censiti nell’anagrafica HAPA.</p>
    </article>
    <article class="metric-card metric-card--info">
        <div class="metric-card__top"><span>Stock Space</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong><?= $e((string) ($catalogMetrics['active'] ?? 0)) ?></strong>
        <p>Prodotti attivi con disponibilità Space consultabile.</p>
    </article>
    <article class="metric-card metric-card--success">
        <div class="metric-card__top"><span>Regole di ricarico</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong><?= $e((string) ($catalogMetrics['pending_review'] ?? 0)) ?></strong>
        <p>Nuovi prodotti in attesa di revisione commerciale.</p>
    </article>
    <article class="metric-card metric-card--warning">
        <div class="metric-card__top"><span>Pubblicazione</span><span class="metric-card__signal" aria-hidden="true"></span></div>
        <strong><?= $e((string) ($catalogMetrics['stale'] ?? 0)) ?></strong>
        <p>Dati mai osservati o più vecchi di 24 ore.</p>
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
        <span class="status-badge status-badge--success">Read model collegato</span>
    </div>
    <form class="data-toolbar" method="get" action="/ui/catalog">
        <label class="search-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg>
            <input type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Cerca SKU, EAN o nome prodotto">
        </label>
        <button class="button button--secondary" type="submit">Cerca</button>
    </form>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th scope="col">SKU</th><th scope="col">Prodotto</th><th scope="col">Costo Space</th><th scope="col">Disponibilità</th><th scope="col">Versione</th><th scope="col">Revisione</th><th scope="col">Età dato</th><th scope="col">Offerte</th></tr></thead>
            <tbody>
            <?php if (($catalogItems ?? []) === []): ?>
                <tr><td colspan="8"><div class="empty-state"><span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#box"></use></svg></span><h3>Nessun prodotto trovato</h3><p>Il catalogo si popola con le osservazioni versionate ricevute da Space tramite hapa-automation.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($catalogItems as $item): ?>
                    <?php $statusTone = $item['onboarding_status'] === 'approved' ? 'success' : ($item['onboarding_status'] === 'rejected' ? 'danger' : 'warning'); ?>
                    <tr>
                        <td><strong><?= $e($item['sku']) ?></strong><?php if ($item['ean'] !== null): ?><small><?= $e($item['ean']) ?></small><?php endif; ?></td>
                        <td><?= $e($item['name'] ?? 'Senza nome') ?></td>
                        <td><?= $e($formatMoney($item['purchase_cost_minor'], $item['currency'])) ?></td>
                        <td><?= $e($item['available_quantity'] === null ? '—' : (string) $item['available_quantity']) ?></td>
                        <td><code><?= $e($item['source_version'] ?? '—') ?></code></td>
                        <td><span class="status-badge status-badge--<?= $e($statusTone) ?>"><?= $e($item['onboarding_status']) ?></span></td>
                        <td><?= $e($formatAge($item['age_seconds'])) ?></td>
                        <td><?= $e((string) $item['marketplace_offer_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
