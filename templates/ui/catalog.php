<?php
$formatMoney = static fn (?int $minor, ?string $currency): string => $minor === null
    ? '—'
    : number_format($minor / 100, 2, ',', '.') . ' ' . ($currency ?? 'EUR');
$formatAge = static function (?int $seconds): string {
    return match (true) {
        $seconds === null => 'Mai sincronizzato',
        $seconds < 60 => 'Meno di un minuto fa',
        $seconds < 3600 => sprintf('%d min fa', intdiv($seconds, 60)),
        $seconds < 86400 => sprintf('%d h fa', intdiv($seconds, 3600)),
        default => sprintf('%d g fa', intdiv($seconds, 86400)),
    };
};
$selected = static fn (bool $condition): string => $condition ? ' selected' : '';
$ruleTone = static fn (array $rule): string => match (true) { $rule['retired_at'] !== null => 'neutral', $rule['enabled'] => 'success', default => 'warning' };
$ruleStatus = static fn (array $rule): string => match (true) { $rule['retired_at'] !== null => 'ritirata', $rule['enabled'] => 'attiva', default => 'disabilitata' };
$onboardingTone = static fn (string $status): string => match ($status) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' };
?>
<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <?php if (($currentUser?->role ?? '') === 'administrator'): ?>
        <a class="button button--primary" href="#new-pricing-rule">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#plus"></use></svg>
            Nuova regola di ricarico
        </a>
        <?php endif; ?>
    </div>
</header>

<?php if (($pricingSaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Regola salvata</strong><span>La nuova versione è auditata; non abilita automaticamente alcun provider.</span></div></output><?php endif; ?>
<?php if (($pricingError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Regola non salvata</strong><span><?= $e($pricingError) ?></span></div></div><?php endif; ?>
<?php if (($reviewSaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Revisione registrata</strong><span>La decisione sul prodotto Space è versionata e presente nell’audit.</span></div></output><?php endif; ?>
<?php if (($reviewError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Revisione non registrata</strong><span><?= $e($reviewError) ?></span></div></div><?php endif; ?>
<?php if (($availabilitySaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Disponibilità ricalcolata</strong><span>HAPA ha aggiornato la quantità vendibile e tutte le offerte marketplace.</span></div></output><?php endif; ?>
<?php if (($availabilityError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Disponibilità non aggiornata</strong><span><?= $e($availabilityError) ?></span></div></div><?php endif; ?>

<?php if (($currentUser?->role ?? '') === 'administrator'): ?>
<section class="panel" id="new-pricing-rule" aria-labelledby="new-pricing-rule-title">
    <div class="panel__header"><div><p class="eyebrow">Politica commerciale</p><h2 id="new-pricing-rule-title">Nuova regola di ricarico</h2></div><span class="status-badge status-badge--info">Versionata e auditata</span></div>
    <form class="auth-form" action="/ui/catalog/pricing-rules" method="post">
        <input type="hidden" name="_csrf_token" value="<?= $e($createPricingCsrfToken ?? '') ?>">
        <div class="field"><label for="pricing-code">Codice</label><input id="pricing-code" name="code" required maxlength="96" placeholder="ibs-default"></div>
        <div class="field"><label for="pricing-name">Nome</label><input id="pricing-name" name="name" required maxlength="160" placeholder="Ricarico IBS predefinito"></div>
        <div class="field"><label for="pricing-scope">Ambito</label><select id="pricing-scope" name="scope"><option value="global">Globale</option><option value="marketplace">Marketplace</option><option value="sku">SKU</option><option value="marketplace_sku">Marketplace + SKU</option></select></div>
        <div class="field"><label for="pricing-marketplace">Marketplace</label><select id="pricing-marketplace" name="marketplace_id"><option value="">Nessuno</option><?php foreach (($marketplaces ?? []) as $marketplace): ?><option value="<?= $e((string) $marketplace['id']) ?>"><?= $e($marketplace['name']) ?> (<?= $e($marketplace['code']) ?>)</option><?php endforeach; ?></select></div>
        <div class="field"><label for="pricing-sku">SKU</label><input id="pricing-sku" name="sku" maxlength="160"></div>
        <div class="field"><label for="pricing-type">Tipo</label><select id="pricing-type" name="adjustment_type"><option value="percentage">Percentuale (basis point)</option><option value="fixed_amount">Importo fisso (centesimi)</option><option value="fixed_price">Prezzo fisso (centesimi)</option></select></div>
        <div class="field"><label for="pricing-value">Valore</label><input id="pricing-value" type="number" name="adjustment_value" min="0" required><small>1000 basis point = 10%; gli importi monetari sono espressi in centesimi.</small></div>
        <div class="field"><label for="pricing-currency">Valuta</label><input id="pricing-currency" name="currency" value="EUR" pattern="[A-Z]{3}" maxlength="3" required></div>
        <div class="field"><label for="pricing-minimum">Prezzo minimo (centesimi)</label><input id="pricing-minimum" type="number" name="minimum_price_minor" min="0"></div>
        <div class="field"><label for="pricing-maximum">Prezzo massimo (centesimi)</label><input id="pricing-maximum" type="number" name="maximum_price_minor" min="0"></div>
        <div class="field"><label for="pricing-priority">Priorità</label><input id="pricing-priority" type="number" name="priority" min="0" max="100000" value="100" required></div>
        <div class="field"><label for="pricing-valid-from">Valida dal</label><input id="pricing-valid-from" type="datetime-local" name="valid_from"></div>
        <div class="field"><label for="pricing-valid-until">Valida fino al</label><input id="pricing-valid-until" type="datetime-local" name="valid_until"></div>
        <label><input type="checkbox" name="enabled" value="1"> Abilita la regola nel motore prezzi</label>
        <button class="button button--primary" type="submit">Crea regola</button>
    </form>
</section>
<?php endif; ?>

<section class="panel data-panel" aria-labelledby="pricing-rules-title">
    <div class="panel__header"><div><p class="eyebrow">Precedenza commerciale</p><h2 id="pricing-rules-title">Regole di ricarico configurate</h2></div><span class="section-heading__meta"><?= $e((string) count($pricingRules ?? [])) ?> regole</span></div>
    <?php if (($pricingRules ?? []) === []): ?>
        <div class="empty-state empty-state--compact"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#settings"></use></svg></span><div><h3>Nessuna regola configurata</h3><p>Il prezzo Space resta invariato finché non viene creata una regola applicabile.</p></div></div>
    <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Regola</th><th>Ambito</th><th>Destinazione</th><th>Ricarico</th><th>Limiti</th><th>Priorità</th><th>Versione</th><th>Stato</th></tr></thead><tbody>
    <?php foreach ($pricingRules as $rule): ?>
        <tr><td><strong><?= $e($rule['name']) ?></strong><small><?= $e($rule['code']) ?></small></td><td><?= $e($rule['scope']) ?></td><td><?= $e($rule['marketplace_code'] ?? '—') ?><small><?= $e($rule['sku'] ?? '—') ?></small></td><td><?= $e($rule['adjustment_type']) ?><small><?= $e((string) $rule['adjustment_value']) ?> <?= $e($rule['currency']) ?></small></td><td><?= $e($formatMoney($rule['minimum_price_minor'], $rule['currency'])) ?> / <?= $e($formatMoney($rule['maximum_price_minor'], $rule['currency'])) ?></td><td><?= $e((string) $rule['priority']) ?></td><td><?= $e((string) $rule['version']) ?></td><td><span class="status-badge status-badge--<?= $e($ruleTone($rule)) ?>"><?= $e($ruleStatus($rule)) ?></span></td></tr>
        <?php if (($currentUser?->role ?? '') === 'administrator' && $rule['retired_at'] === null): ?>
        <tr><td colspan="8"><details><summary>Modifica versione <?= $e((string) $rule['version']) ?></summary>
            <?php $pricingFieldPrefix = 'pricing-rule-' . (string) $rule['id']; ?>
            <form class="auth-form" action="/ui/catalog/pricing-rules/<?= $e((string) $rule['id']) ?>" method="post">
                <input type="hidden" name="_csrf_token" value="<?= $e($rule['update_csrf_token']) ?>"><input type="hidden" name="version" value="<?= $e((string) $rule['version']) ?>">
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-code">Codice</label><input id="<?= $e($pricingFieldPrefix) ?>-code" name="code" value="<?= $e($rule['code']) ?>" required maxlength="96"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-name">Nome</label><input id="<?= $e($pricingFieldPrefix) ?>-name" name="name" value="<?= $e($rule['name']) ?>" required maxlength="160"></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-scope">Ambito</label><select id="<?= $e($pricingFieldPrefix) ?>-scope" name="scope"><?php foreach (['global','marketplace','sku','marketplace_sku'] as $scope): ?><option value="<?= $e($scope) ?>"<?= $selected($rule['scope'] === $scope) ?>><?= $e($scope) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-marketplace">Marketplace</label><select id="<?= $e($pricingFieldPrefix) ?>-marketplace" name="marketplace_id"><option value="">Nessuno</option><?php foreach (($marketplaces ?? []) as $marketplace): ?><option value="<?= $e((string) $marketplace['id']) ?>"<?= $selected($rule['marketplace_id'] === $marketplace['id']) ?>><?= $e($marketplace['name']) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-sku">SKU</label><input id="<?= $e($pricingFieldPrefix) ?>-sku" name="sku" value="<?= $e($rule['sku'] ?? '') ?>" maxlength="160"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-type">Tipo</label><select id="<?= $e($pricingFieldPrefix) ?>-type" name="adjustment_type"><?php foreach (['percentage','fixed_amount','fixed_price'] as $type): ?><option value="<?= $e($type) ?>"<?= $selected($rule['adjustment_type'] === $type) ?>><?= $e($type) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-value">Valore</label><input id="<?= $e($pricingFieldPrefix) ?>-value" type="number" name="adjustment_value" min="0" value="<?= $e((string) $rule['adjustment_value']) ?>" required></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-currency">Valuta</label><input id="<?= $e($pricingFieldPrefix) ?>-currency" name="currency" value="<?= $e($rule['currency']) ?>" pattern="[A-Z]{3}" maxlength="3" required></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-minimum">Prezzo minimo</label><input id="<?= $e($pricingFieldPrefix) ?>-minimum" type="number" name="minimum_price_minor" min="0" value="<?= $e($rule['minimum_price_minor'] === null ? '' : (string) $rule['minimum_price_minor']) ?>"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-maximum">Prezzo massimo</label><input id="<?= $e($pricingFieldPrefix) ?>-maximum" type="number" name="maximum_price_minor" min="0" value="<?= $e($rule['maximum_price_minor'] === null ? '' : (string) $rule['maximum_price_minor']) ?>"></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-priority">Priorità</label><input id="<?= $e($pricingFieldPrefix) ?>-priority" type="number" name="priority" min="0" max="100000" value="<?= $e((string) $rule['priority']) ?>" required></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-valid-from">Valida dal</label><input id="<?= $e($pricingFieldPrefix) ?>-valid-from" type="datetime-local" name="valid_from" value="<?= $e($rule['valid_from'] === null ? '' : substr(str_replace(' ', 'T', $rule['valid_from']), 0, 16)) ?>"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-valid-until">Valida fino al</label><input id="<?= $e($pricingFieldPrefix) ?>-valid-until" type="datetime-local" name="valid_until" value="<?= $e($rule['valid_until'] === null ? '' : substr(str_replace(' ', 'T', $rule['valid_until']), 0, 16)) ?>"></div>
                <label><input type="checkbox" name="enabled" value="1"<?= $rule['enabled'] ? ' checked' : '' ?>> Regola abilitata</label><button class="button button--secondary" type="submit">Salva nuova versione</button>
            </form>
            <form action="/ui/catalog/pricing-rules/<?= $e((string) $rule['id']) ?>/retire" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($rule['retire_csrf_token']) ?>"><input type="hidden" name="version" value="<?= $e((string) $rule['version']) ?>"><button class="button button--ghost" type="submit">Ritira regola</button></form>
        </details></td></tr>
        <?php endif; ?>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>

<div class="inline-notice inline-notice--info" role="note">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#info"></use></svg>
    <div>
        <strong>Anagrafica prodotti HAPA</strong>
        <span>Space sincronizza prezzo e stock del prodotto; HAPA conserva il dato e permette agli operatori di gestire dall’interfaccia le regole di ricarico e il prezzo finale.</span>
    </div>
</div>
<div class="inline-notice inline-notice--warning" role="note"><div><strong>Perimetro dell’anteprima</strong><span>Il prezzo calcolato applica il ricarico al costo Space. Commissioni del canale, regime IVA e arrotondamenti imposti dal marketplace saranno inclusi solo dopo aver validato i rispettivi contratti ufficiali.</span></div></div>

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
        <strong><?= $e((string) count(array_filter($pricingRules ?? [], static fn (array $rule): bool => $rule['retired_at'] === null))) ?></strong>
        <p>Regole commerciali disponibili nel motore prezzi.</p>
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
                <span class="status-badge status-badge--info">Ricezione pronta</span>
            </article>
            <article class="workstream-item">
                <span class="workstream-item__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#settings"></use></svg></span>
                <div class="workstream-item__copy"><strong>2. Gestisci il ricarico</strong><span>L’operatore configura in HAPA la regola commerciale e verifica il prezzo finale</span></div>
                <span class="status-badge status-badge--success">Anteprima pronta</span>
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
        <label class="search-field" for="catalog-query">
            <span class="sr-only">Cerca nel catalogo</span>
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg>
            <input id="catalog-query" type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Cerca SKU, EAN o nome prodotto">
        </label>
        <button class="button button--secondary" type="submit">Cerca</button>
    </form>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th scope="col">SKU</th><th scope="col">Prodotto</th><th scope="col">Costo Space</th><th scope="col">Stock Space</th><th scope="col">Vendibile HAPA</th><th scope="col">Versione</th><th scope="col">Revisione</th><th scope="col">Età dato</th><th scope="col">Offerte HAPA</th><th scope="col">Offerte</th><th scope="col">Azioni</th></tr></thead>
            <tbody>
            <?php if (($catalogItems ?? []) === []): ?>
                <tr><td colspan="11"><div class="empty-state"><span class="empty-state__icon" aria-hidden="true"><svg class="icon"><use href="/assets/icons.svg#box"></use></svg></span><h3>Nessun prodotto trovato</h3><p>Il catalogo si popola con le osservazioni versionate ricevute da Space tramite hapa-automation.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($catalogItems as $item): ?>
                    <?php $statusTone = $onboardingTone($item['onboarding_status']); ?>
                    <tr>
                        <td><strong><?= $e($item['sku']) ?></strong><?php if ($item['ean'] !== null): ?><small><?= $e($item['ean']) ?></small><?php endif; ?></td>
                        <td><?= $e($item['name'] ?? 'Senza nome') ?></td>
                        <td><?= $e($formatMoney($item['purchase_cost_minor'], $item['currency'])) ?></td>
                        <td><?= $e($item['available_quantity'] === null ? '—' : (string) $item['available_quantity']) ?></td>
                        <td><strong><?= $e((string) $item['sellable_quantity']) ?></strong><small>Stock − riserva <?= $e((string) $item['safety_stock']) ?></small><?php if (($currentUser?->role ?? '') === 'administrator'): ?><form action="/ui/catalog/items/<?= $e((string) $item['id']) ?>/availability" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($item['availability_csrf_token']) ?>"><input type="hidden" name="version" value="<?= $e((string) $item['version']) ?>"><label><small>Scorta sicurezza</small><input type="number" name="safety_stock" min="0" step="1" value="<?= $e((string) $item['safety_stock']) ?>" required></label><button class="button button--ghost" type="submit">Ricalcola</button></form><?php endif; ?></td>
                        <td><code><?= $e($item['source_version'] ?? '—') ?></code></td>
                        <td><span class="status-badge status-badge--<?= $e($statusTone) ?>"><?= $e($item['onboarding_status']) ?></span></td>
                        <td><?= $e($formatAge($item['age_seconds'])) ?></td>
                        <td><?php if (($item['price_previews'] ?? []) === []): ?>—<?php else: ?><details><summary><?= $e((string) count($item['price_previews'])) ?> canali</summary><?php foreach ($item['price_previews'] as $preview): ?><div class="workstream-item__copy"><strong><?= $e($preview['marketplace_name']) ?>: <?= $e($formatMoney($preview['selling_price_minor'], $preview['currency'])) ?> · qty <?= $e((string) $preview['sellable_quantity']) ?></strong><small>Costo <?= $e($formatMoney($preview['base_price_minor'], $preview['currency'])) ?> · ricarico <?= $e($formatMoney($preview['markup_minor'], $preview['currency'])) ?> · regola <?= $e($preview['applied_rule_code'] ?? 'nessuna') ?> · versione offerta <?= $e($preview['offer_version'] === null ? '—' : (string) $preview['offer_version']) ?></small><span class="status-badge status-badge--<?= $preview['publishable'] ? 'success' : 'warning' ?>"><?= $e($preview['publishable'] ? 'pubblicabile' : implode(', ', $preview['blockers'])) ?></span><?php if ($preview['error'] !== null): ?><small><?= $e($preview['error']) ?></small><?php endif; ?></div><?php endforeach; ?></details><?php endif; ?></td>
                        <td><?= $e((string) $item['marketplace_offer_count']) ?></td>
                        <td><?php if (($currentUser?->role ?? '') === 'administrator' && $item['onboarding_status'] === 'pending_review'): ?><form action="/ui/catalog/items/<?= $e((string) $item['id']) ?>/review" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($item['review_csrf_token']) ?>"><input type="hidden" name="version" value="<?= $e((string) $item['version']) ?>"><button class="button button--secondary" name="decision" value="approved" type="submit">Approva</button><button class="button button--ghost" name="decision" value="rejected" type="submit">Rifiuta</button></form><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
