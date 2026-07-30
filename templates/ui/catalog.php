<?php
$formatMoney = static fn (?int $minor, ?string $currency): string => $minor === null
    ? '—'
    : number_format($minor / 100, 2, ',', '.') . ' ' . ($currency ?? 'EUR');
$selected = static fn (bool $condition): string => $condition ? ' selected' : '';
$ruleTone = static fn (array $rule): string => match (true) { $rule['retired_at'] !== null => 'neutral', $rule['enabled'] => 'success', default => 'warning' };
$ruleStatus = static fn (array $rule): string => match (true) { $rule['retired_at'] !== null => 'ritirata', $rule['enabled'] => 'attiva', default => 'disabilitata' };
$fieldLabels = [
    'sku' => 'SKU completo', 'supplier_id' => 'Fornitore Space', 'branch_suffix' => 'Filiale',
    'artist' => 'Artista', 'title' => 'Titolo', 'format' => 'Formato', 'label' => 'Etichetta',
    'category' => 'Categoria', 'family' => 'Famiglia', 'group' => 'Gruppo',
    'delivery_time_days' => 'Tempo di consegna', 'available_quantity' => 'Quantità disponibile',
];
$operatorLabels = [
    'contains' => 'contiene', 'equals' => 'è uguale a', 'starts_with' => 'inizia con',
    'ends_with' => 'finisce con', 'minimum' => 'è almeno', 'maximum' => 'è al massimo',
];
$formatAdjustment = static fn (array $rule): string => match ($rule['adjustment_type']) {
    'percentage' => number_format(((int) $rule['adjustment_value']) / 100, 2, ',', '.') . '%',
    'fixed_amount' => '+' . number_format(((int) $rule['adjustment_value']) / 100, 2, ',', '.') . ' ' . $rule['currency'],
    'fixed_price' => number_format(((int) $rule['adjustment_value']) / 100, 2, ',', '.') . ' ' . $rule['currency'] . ' prezzo finale',
    default => (string) $rule['adjustment_value'],
};
$adjustmentLabels = [
    'percentage' => 'Percentuale sul costo Space',
    'fixed_amount' => 'Importo aggiunto al costo Space',
    'fixed_price' => 'Prezzo finale fisso',
];
$scopeLabels = [
    'global' => 'Tutto il catalogo',
    'marketplace' => 'Marketplace',
    'sku' => 'Singolo SKU',
    'marketplace_sku' => 'Marketplace + SKU',
];
$catalogStatusLabel = static fn (array $catalog): string => match ($catalog['status'] ?? 'draft') {
    'active' => 'Attivo', 'ready' => 'Pronto da attivare', default => 'Bozza',
};
$catalogStatusTone = static fn (array $catalog): string => match ($catalog['status'] ?? 'draft') {
    'active' => 'success', 'ready' => 'info', default => 'warning',
};
?>
<?php if (($selectedCatalog ?? null) === null): ?>
<header class="page-header">
    <div>
        <p class="eyebrow">Cataloghi commerciali</p>
        <h1>Cataloghi marketplace</h1>
        <p class="page-header__description">Ogni catalogo definisce a quali marketplace è destinato, come viene calcolato il prezzo e quali prodotti possono essere pubblicati.</p>
    </div>
    <div class="page-header__actions"><a class="button button--primary" href="#new-commercial-catalog">Nuovo catalogo</a></div>
</header>

<?php if (($catalogError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Catalogo non creato</strong><span><?= $e($catalogError) ?></span></div></div><?php endif; ?>
<?php if (($catalogDeleted ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Catalogo cancellato</strong><span>Regole, filtri e associazioni marketplace sono stati rimossi; le offerte sono state ricalcolate.</span></div></output><?php endif; ?>

<section class="metric-grid" aria-label="Sintesi cataloghi commerciali">
    <article class="metric-card metric-card--info"><div class="metric-card__top"><span>Cataloghi</span></div><strong><?= $e((string) count($commercialCatalogs ?? [])) ?></strong><p>Cataloghi commerciali configurati.</p></article>
    <article class="metric-card metric-card--success"><div class="metric-card__top"><span>Pronti</span></div><strong><?= $e((string) count(array_filter($commercialCatalogs ?? [], static fn (array $catalog): bool => $catalog['ready']))) ?></strong><p>Con prezzo e almeno un’inclusione.</p></article>
    <article class="metric-card metric-card--info"><div class="metric-card__top"><span>Prodotti sorgente</span></div><strong><?= $e((string) ($catalogMetrics['total'] ?? 0)) ?></strong><p>Prodotti ricevuti da Space.</p></article>
    <article class="metric-card metric-card--warning"><div class="metric-card__top"><span>Da completare</span></div><strong><?= $e((string) count(array_filter($commercialCatalogs ?? [], static fn (array $catalog): bool => !$catalog['ready']))) ?></strong><p>Cataloghi che non possono ancora pubblicare.</p></article>
</section>

<section class="panel data-panel" aria-labelledby="commercial-catalog-list">
    <div class="panel__header"><div><p class="eyebrow">Perimetri di vendita</p><h2 id="commercial-catalog-list">Cataloghi configurati</h2></div></div>
    <?php if (($commercialCatalogs ?? []) === []): ?>
        <div class="empty-state"><div><h3>Nessun catalogo commerciale</h3><p>Crea il primo catalogo, assegna almeno un marketplace, una regola prezzo e una regola di inclusione.</p></div></div>
    <?php else: ?>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>Catalogo</th><th>Marketplace</th><th>Precedenza</th><th>Regole prezzo</th><th>Inclusioni</th><th>Esclusioni</th><th>Prodotti passati</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
        <?php foreach ($commercialCatalogs as $commercialCatalog): ?>
            <tr>
                <td><strong><?= $e($commercialCatalog['name']) ?></strong><small><?= $e($commercialCatalog['code']) ?></small></td>
                <td><?= $e($commercialCatalog['marketplace_names']) ?></td>
                <td><?= $e((string) $commercialCatalog['priority']) ?></td>
                <td><?= $e((string) $commercialCatalog['pricing_rule_count']) ?></td>
                <td><?= $e((string) $commercialCatalog['include_rule_count']) ?></td>
                <td><?= $e((string) $commercialCatalog['exclude_rule_count']) ?></td>
                <td><strong><?= $e((string) $commercialCatalog['eligible_product_count']) ?></strong></td>
                <td><span class="status-badge status-badge--<?= $e($catalogStatusTone($commercialCatalog)) ?>"><?= $e($catalogStatusLabel($commercialCatalog)) ?></span></td>
                <td><a class="button button--secondary" href="/ui/catalog?catalog=<?= $e((string) $commercialCatalog['id']) ?>">Apri catalogo</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<details class="panel" id="new-commercial-catalog">
    <summary><strong>Crea un nuovo catalogo</strong></summary>
    <form class="integration-create__form" action="/ui/catalog/catalogs" method="post">
        <input type="hidden" name="_csrf_token" value="<?= $e($createCommercialCatalogCsrfToken ?? '') ?>">
        <div class="integration-create__grid">
            <div class="field"><label for="catalog-code">Codice</label><input id="catalog-code" name="code" required maxlength="100" placeholder="temu-italia"></div>
            <div class="field"><label for="catalog-name">Nome</label><input id="catalog-name" name="name" required maxlength="180" placeholder="Catalogo Temu Italia"></div>
            <div class="field integration-create__wide"><label>Marketplace destinatari</label>
                <?php foreach ($marketplaces as $marketplace): ?><label><input type="checkbox" name="marketplace_ids[]" value="<?= $e((string) $marketplace['id']) ?>"> <?= $e($marketplace['name']) ?></label><?php endforeach; ?>
                <small>Seleziona almeno un marketplace.</small>
            </div>
            <div class="field"><label for="catalog-priority">Precedenza</label><input id="catalog-priority" name="priority" type="number" min="0" max="100000" value="100"><small>Se un prodotto rientra in più cataloghi sullo stesso marketplace, vince il numero più basso con un prezzo applicabile.</small></div>
            <div class="field integration-create__wide"><label for="catalog-description">Descrizione</label><textarea id="catalog-description" name="description" maxlength="1000" rows="3"></textarea></div>
        </div>
        <div class="integration-create__footer"><span>Il catalogo nascerà in bozza: potrai configurarlo e verificarlo senza pubblicare prodotti.</span><button class="button button--primary" type="submit">Crea catalogo in bozza</button></div>
    </form>
</details>
<?php return; endif; ?>

<header class="page-header">
    <div>
        <p class="eyebrow"><a href="/ui/catalog">Cataloghi</a> / Dettaglio</p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description">Decidi quali prodotti includere, verifica il prezzo finale e attiva il catalogo soltanto dopo aver controllato l’anteprima.</p>
    </div>
    <div class="page-header__actions">
        <span class="status-badge status-badge--<?= $e($catalogStatusTone($selectedCatalog)) ?>"><?= $e($catalogStatusLabel($selectedCatalog)) ?></span>
        <?php if (($currentUser?->role ?? '') === 'administrator'): ?>
        <a class="button button--ghost" href="#delete-commercial-catalog">Cancella catalogo</a>
        <?php endif; ?>
    </div>
</header>

<section class="metric-grid" aria-label="Stato catalogo commerciale">
    <article class="metric-card metric-card--info"><div class="metric-card__top"><span>Marketplace</span></div><strong><?= $e((string) count($selectedCatalog['marketplace_ids'])) ?></strong><p><?= $e($selectedCatalog['marketplace_names']) ?></p></article>
    <article class="metric-card metric-card--<?= $selectedCatalog['pricing_rule_count'] > 0 ? 'success' : 'warning' ?>"><div class="metric-card__top"><span>Regole prezzo</span></div><strong><?= $e((string) $selectedCatalog['pricing_rule_count']) ?></strong><p>Minimo una regola attiva.</p></article>
    <article class="metric-card metric-card--<?= $selectedCatalog['include_rule_count'] > 0 ? 'success' : 'warning' ?>"><div class="metric-card__top"><span>Inclusioni</span></div><strong><?= $e((string) $selectedCatalog['include_rule_count']) ?></strong><p>Senza inclusioni passano zero prodotti.</p></article>
    <article class="metric-card metric-card--<?= $selectedCatalog['eligible_product_count'] > 0 ? 'success' : 'warning' ?>"><div class="metric-card__top"><span>Prodotti selezionati</span></div><strong><?= $e((string) $selectedCatalog['eligible_product_count']) ?></strong><p>Anteprima dopo inclusioni ed esclusioni.</p></article>
</section>

<section class="panel catalog-activation" aria-label="Flusso di attivazione catalogo">
    <div class="catalog-activation__steps">
        <div class="<?= $selectedCatalog['include_rule_count'] > 0 ? 'is-complete' : '' ?>"><span>1</span><strong>Seleziona prodotti</strong><small>Almeno un’inclusione</small></div>
        <div class="<?= $selectedCatalog['pricing_rule_count'] > 0 ? 'is-complete' : '' ?>"><span>2</span><strong>Definisci il prezzo</strong><small>Almeno una regola</small></div>
        <div class="<?= ($catalogPreviewRequested ?? false) ? 'is-complete' : '' ?>"><span>3</span><strong>Controlla l’anteprima</strong><small>Prodotti e prezzi finali</small></div>
        <div class="<?= $selectedCatalog['enabled'] ? 'is-complete' : '' ?>"><span>4</span><strong>Attiva</strong><small>Solo dopo il controllo</small></div>
    </div>
    <div class="catalog-activation__actions">
        <a class="button button--secondary" href="/ui/catalog?catalog=<?= $e((string) $selectedCatalog['id']) ?>&amp;preview=1#catalog-preview">Aggiorna anteprima</a>
        <?php if (($currentUser?->role ?? '') === 'administrator'): ?>
        <form action="/ui/catalog/catalogs/<?= $e((string) $selectedCatalog['id']) ?>/status" method="post">
            <input type="hidden" name="_csrf_token" value="<?= $e($statusCommercialCatalogCsrfToken ?? '') ?>">
            <input type="hidden" name="status" value="<?= $selectedCatalog['enabled'] ? 'draft' : 'active' ?>">
            <button class="button button--<?= $selectedCatalog['enabled'] ? 'ghost' : 'primary' ?>" type="submit"<?= !$selectedCatalog['enabled'] && (!$selectedCatalog['ready'] || $selectedCatalog['eligible_product_count'] < 1 || !($catalogPreviewRequested ?? false)) ? ' disabled' : '' ?>>
                <?= $selectedCatalog['enabled'] ? 'Disattiva catalogo' : 'Attiva catalogo' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php if (!($selectedCatalog['configured'] ?? false)): ?><div class="inline-notice inline-notice--warning" role="status"><div><strong>Catalogo ancora in preparazione</strong><span>Aggiungi almeno una regola prezzo attiva e una regola di inclusione. Finché resta in bozza non viene pubblicato nulla.</span></div></div>
<?php elseif ($selectedCatalog['eligible_product_count'] < 1): ?><div class="inline-notice inline-notice--warning" role="status"><div><strong>La selezione non contiene prodotti</strong><span>Modifica inclusioni ed esclusioni, quindi ricalcola l’anteprima. Un catalogo vuoto non può essere attivato.</span></div></div><?php endif; ?>

<?php if (($pricingSaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Regola salvata</strong><span>La nuova versione è auditata; non abilita automaticamente alcun provider.</span></div></output><?php endif; ?>
<?php if (($pricingError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Regola non salvata</strong><span><?= $e($pricingError) ?></span></div></div><?php endif; ?>
<?php if (($reviewSaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Revisione registrata</strong><span>La decisione sul prodotto Space è versionata e presente nell’audit.</span></div></output><?php endif; ?>
<?php if (($reviewError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Revisione non registrata</strong><span><?= $e($reviewError) ?></span></div></div><?php endif; ?>
<?php if (($availabilitySaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Disponibilità ricalcolata</strong><span>HAPA ha aggiornato la quantità vendibile e tutte le offerte marketplace.</span></div></output><?php endif; ?>
<?php if (($availabilityError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Disponibilità non aggiornata</strong><span><?= $e($availabilityError) ?></span></div></div><?php endif; ?>
<?php if (($publicationRuleSaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Filtro catalogo salvato</strong><span>La regola verrà applicata al calcolo delle offerte marketplace.</span></div></output><?php endif; ?>
<?php if (($publicationRuleError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Filtro non salvato</strong><span><?= $e($publicationRuleError) ?></span></div></div><?php endif; ?>
<?php if (($catalogDeleteError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Catalogo non cancellato</strong><span><?= $e($catalogDeleteError) ?></span></div></div><?php endif; ?>
<?php if (($catalogStatusSaved ?? false) === true): ?><output class="inline-notice inline-notice--info"><div><strong>Stato catalogo aggiornato</strong><span><?= $selectedCatalog['enabled'] ? 'Il catalogo è attivo.' : 'Il catalogo è tornato in bozza e non genera pubblicazioni.' ?></span></div></output><?php endif; ?>
<?php if (($catalogStatusError ?? '') !== ''): ?><div class="inline-notice inline-notice--warning" role="alert"><div><strong>Stato non modificato</strong><span><?= $e($catalogStatusError) ?></span></div></div><?php endif; ?>

<section class="panel catalog-builder" id="publication-rules" aria-labelledby="publication-rules-title">
    <div class="panel__header">
        <div>
            <p class="eyebrow">Passaggio 1</p>
            <h2 id="publication-rules-title">Decidi quali prodotti passano</h2>
            <p>Parti da un’inclusione, poi aggiungi eventuali esclusioni. Senza inclusioni il catalogo resta vuoto.</p>
        </div>
        <span class="section-heading__meta"><?= $e((string) $selectedCatalog['eligible_product_count']) ?> prodotti selezionati</span>
    </div>
    <?php if (($currentUser?->role ?? '') === 'administrator'): ?>
    <form class="integration-create__form catalog-filter-builder" action="/ui/catalog/publication-rules" method="post">
        <input type="hidden" name="_csrf_token" value="<?= $e($createPublicationRuleCsrfToken ?? '') ?>">
        <input type="hidden" name="commercial_catalog_id" value="<?= $e((string) $selectedCatalog['id']) ?>">
        <div class="catalog-filter-builder__sentence">
            <div class="field">
                <label for="publication-action">Voglio</label>
                <select id="publication-action" name="action"><option value="include">Includere</option><option value="exclude">Escludere</option></select>
            </div>
            <div class="field">
                <label for="publication-field">i prodotti in cui</label>
                <select id="publication-field" name="field"><?php foreach ($fieldLabels as $value => $label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field">
                <label for="publication-operator">condizione</label>
                <select id="publication-operator" name="operator"><?php foreach ($operatorLabels as $value => $label): ?><option value="<?= $e($value) ?>"><?= $e(ucfirst($label)) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field catalog-filter-builder__value">
                <label for="publication-value">valore</label>
                <input id="publication-value" name="match_value" required maxlength="500" placeholder="es. A25, CD, artista o quantità">
            </div>
            <button class="button button--primary" type="submit">Aggiungi alla selezione</button>
        </div>
        <details class="catalog-filter-builder__advanced">
            <summary>Opzioni avanzate</summary>
            <div class="integration-create__grid">
                <div class="field"><label for="publication-marketplace">Marketplace</label><select id="publication-marketplace" name="marketplace_id"><option value="">Tutti quelli del catalogo</option><?php foreach (($marketplaces ?? []) as $marketplace): ?><?php if (in_array($marketplace['id'], $selectedCatalog['marketplace_ids'], true)): ?><option value="<?= $e((string) $marketplace['id']) ?>"><?= $e($marketplace['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                <div class="field"><label for="publication-priority">Priorità</label><input id="publication-priority" type="number" name="priority" value="100" min="0" required><small>Il numero più basso viene valutato prima; a parità vince l’esclusione.</small></div>
                <div class="field"><label for="publication-name">Nome descrittivo (facoltativo)</label><input id="publication-name" name="name" maxlength="180" placeholder="es. Escludi filiale A25"></div>
                <div class="field"><label for="publication-code">Codice tecnico (facoltativo)</label><input id="publication-code" name="code" maxlength="100" placeholder="generato automaticamente"></div>
            </div>
        </details>
    </form>
    <?php endif; ?>
    <div class="catalog-rule-list">
        <?php if (($publicationRules ?? []) === []): ?>
            <div class="empty-state empty-state--compact"><div><h3>Nessun criterio di selezione</h3><p>Aggiungi la prima inclusione per vedere quali prodotti entrano nel catalogo.</p></div></div>
        <?php else: ?>
            <?php foreach ($publicationRules as $rule): ?>
            <article class="catalog-rule">
                <span class="status-badge status-badge--<?= $rule['action'] === 'include' ? 'success' : 'warning' ?>"><?= $rule['action'] === 'include' ? 'INCLUDI' : 'ESCLUDI' ?></span>
                <div><strong><?= $e($fieldLabels[$rule['field']] ?? $rule['field']) ?> <?= $e($operatorLabels[$rule['operator']] ?? $rule['operator']) ?> “<?= $e($rule['match_value']) ?>”</strong><small><?= $e($rule['marketplace_code'] ?? 'Tutti i marketplace') ?> · priorità <?= $e((string) $rule['priority']) ?> · <?= $e($rule['name']) ?></small></div>
                <?php if (($currentUser?->role ?? '') === 'administrator'): ?><form action="/ui/catalog/publication-rules/<?= $e((string) $rule['id']) ?>/retire" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($rule['retire_csrf_token']) ?>"><input type="hidden" name="commercial_catalog_id" value="<?= $e((string) $selectedCatalog['id']) ?>"><button class="button button--ghost" type="submit">Rimuovi</button></form><?php endif; ?>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<div class="workflow-section-heading">
    <p class="eyebrow">Passaggio 2</p>
    <h2>Definisci il prezzo di vendita</h2>
    <p>Il prezzo viene calcolato sul costo Space e resta solo in anteprima finché il catalogo è in bozza.</p>
</div>

<?php if (($currentUser?->role ?? '') === 'administrator'): ?>
<details class="panel" id="new-pricing-rule">
    <summary><strong>Aggiungi una regola prezzo</strong> <span class="status-badge status-badge--info">Versionata e auditata</span></summary>
    <form class="auth-form" action="/ui/catalog/pricing-rules" method="post" data-pricing-form>
        <input type="hidden" name="_csrf_token" value="<?= $e($createPricingCsrfToken ?? '') ?>">
        <input type="hidden" name="commercial_catalog_id" value="<?= $e((string) $selectedCatalog['id']) ?>">
        <div class="field"><label for="pricing-code">Codice</label><input id="pricing-code" name="code" required maxlength="96" placeholder="ibs-default"></div>
        <div class="field"><label for="pricing-name">Nome</label><input id="pricing-name" name="name" required maxlength="160" placeholder="Ricarico IBS predefinito"></div>
        <div class="field"><label for="pricing-scope">Ambito</label><select id="pricing-scope" name="scope"><option value="global">Globale</option><option value="marketplace">Marketplace</option><option value="sku">SKU</option><option value="marketplace_sku">Marketplace + SKU</option></select></div>
        <div class="field"><label for="pricing-marketplace">Marketplace</label><select id="pricing-marketplace" name="marketplace_id"><option value="">Tutti quelli del catalogo</option><?php foreach (($marketplaces ?? []) as $marketplace): ?><?php if (in_array($marketplace['id'], $selectedCatalog['marketplace_ids'], true)): ?><option value="<?= $e((string) $marketplace['id']) ?>"><?= $e($marketplace['name']) ?> (<?= $e($marketplace['code']) ?>)</option><?php endif; ?><?php endforeach; ?></select></div>
        <div class="field"><label for="pricing-sku">SKU</label><input id="pricing-sku" name="sku" maxlength="160"></div>
        <div class="field"><label for="pricing-type">Come calcolare il prezzo</label><select id="pricing-type" name="adjustment_type" data-pricing-type><option value="percentage">Aumenta di una percentuale</option><option value="fixed_amount">Aggiungi un importo fisso</option><option value="fixed_price">Imposta un prezzo finale</option></select></div>
        <div class="field" data-pricing-percentage><label for="pricing-percentage">Ricarico percentuale</label><div class="input-shell"><input id="pricing-percentage" type="number" name="percentage_value" min="0" step="0.01" placeholder="20" required><span>%</span></div><small>Inserisci 20 per applicare un ricarico del 20%.</small></div>
        <div class="field" data-pricing-amount hidden><label for="pricing-value">Importo in centesimi</label><input id="pricing-value" type="number" name="adjustment_value" min="0" disabled><small>500 equivale a 5,00 EUR.</small></div>
        <div class="field"><label for="pricing-currency">Valuta</label><input id="pricing-currency" name="currency" value="EUR" pattern="[A-Z]{3}" maxlength="3" required></div>
        <div class="field"><label for="pricing-minimum">Prezzo minimo (centesimi)</label><input id="pricing-minimum" type="number" name="minimum_price_minor" min="0"></div>
        <div class="field"><label for="pricing-maximum">Prezzo massimo (centesimi)</label><input id="pricing-maximum" type="number" name="maximum_price_minor" min="0"></div>
        <div class="field"><label for="pricing-priority">Priorità</label><input id="pricing-priority" type="number" name="priority" min="0" max="100000" value="100" required></div>
        <div class="field"><label for="pricing-valid-from">Valida dal</label><input id="pricing-valid-from" type="datetime-local" name="valid_from"></div>
        <div class="field"><label for="pricing-valid-until">Valida fino al</label><input id="pricing-valid-until" type="datetime-local" name="valid_until"></div>
        <label><input type="checkbox" name="enabled" value="1" checked> Usa subito questa regola nel calcolo dell’anteprima</label>
        <button class="button button--primary" type="submit">Crea regola</button>
    </form>
</details>
<?php endif; ?>

<section class="panel data-panel" aria-labelledby="pricing-rules-title">
    <div class="panel__header"><div><p class="eyebrow">Precedenza commerciale</p><h2 id="pricing-rules-title">Regole di ricarico configurate</h2></div><span class="section-heading__meta"><?= $e((string) count($pricingRules ?? [])) ?> regole</span></div>
    <?php if (($pricingRules ?? []) === []): ?>
        <div class="empty-state empty-state--compact"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#settings"></use></svg></span><div><h3>Nessuna regola configurata</h3><p>Il prezzo Space resta invariato finché non viene creata una regola applicabile.</p></div></div>
    <?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Regola</th><th>Ambito</th><th>Destinazione</th><th>Ricarico</th><th>Limiti</th><th>Priorità</th><th>Versione</th><th>Stato</th></tr></thead><tbody>
    <?php foreach ($pricingRules as $rule): ?>
        <tr><td><strong><?= $e($rule['name']) ?></strong><small><?= $e($rule['code']) ?></small></td><td><?= $e($scopeLabels[$rule['scope']] ?? $rule['scope']) ?></td><td><?= $e($rule['marketplace_code'] ?? '—') ?><small><?= $e($rule['sku'] ?? '—') ?></small></td><td><strong><?= $e($formatAdjustment($rule)) ?></strong><small><?= $e($adjustmentLabels[$rule['adjustment_type']] ?? $rule['adjustment_type']) ?></small></td><td><?= $e($formatMoney($rule['minimum_price_minor'], $rule['currency'])) ?> / <?= $e($formatMoney($rule['maximum_price_minor'], $rule['currency'])) ?></td><td><?= $e((string) $rule['priority']) ?></td><td><?= $e((string) $rule['version']) ?></td><td><span class="status-badge status-badge--<?= $e($ruleTone($rule)) ?>"><?= $e($ruleStatus($rule)) ?></span></td></tr>
        <?php if (($currentUser?->role ?? '') === 'administrator' && $rule['retired_at'] === null): ?>
        <tr><td colspan="8"><details><summary>Modifica versione <?= $e((string) $rule['version']) ?></summary>
            <?php $pricingFieldPrefix = 'pricing-rule-' . (string) $rule['id']; ?>
            <form class="auth-form" action="/ui/catalog/pricing-rules/<?= $e((string) $rule['id']) ?>" method="post" data-pricing-form>
                <input type="hidden" name="_csrf_token" value="<?= $e($rule['update_csrf_token']) ?>"><input type="hidden" name="version" value="<?= $e((string) $rule['version']) ?>"><input type="hidden" name="commercial_catalog_id" value="<?= $e((string) $selectedCatalog['id']) ?>">
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-code">Codice</label><input id="<?= $e($pricingFieldPrefix) ?>-code" name="code" value="<?= $e($rule['code']) ?>" required maxlength="96"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-name">Nome</label><input id="<?= $e($pricingFieldPrefix) ?>-name" name="name" value="<?= $e($rule['name']) ?>" required maxlength="160"></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-scope">Ambito</label><select id="<?= $e($pricingFieldPrefix) ?>-scope" name="scope"><?php foreach (['global','marketplace','sku','marketplace_sku'] as $scope): ?><option value="<?= $e($scope) ?>"<?= $selected($rule['scope'] === $scope) ?>><?= $e($scope) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-marketplace">Marketplace</label><select id="<?= $e($pricingFieldPrefix) ?>-marketplace" name="marketplace_id"><option value="">Tutti quelli del catalogo</option><?php foreach (($marketplaces ?? []) as $marketplace): ?><?php if (in_array($marketplace['id'], $selectedCatalog['marketplace_ids'], true)): ?><option value="<?= $e((string) $marketplace['id']) ?>"<?= $selected($rule['marketplace_id'] === $marketplace['id']) ?>><?= $e($marketplace['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-sku">SKU</label><input id="<?= $e($pricingFieldPrefix) ?>-sku" name="sku" value="<?= $e($rule['sku'] ?? '') ?>" maxlength="160"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-type">Come calcolare il prezzo</label><select id="<?= $e($pricingFieldPrefix) ?>-type" name="adjustment_type" data-pricing-type><?php foreach (['percentage' => 'Aumenta di una percentuale','fixed_amount' => 'Aggiungi un importo fisso','fixed_price' => 'Imposta un prezzo finale'] as $type => $label): ?><option value="<?= $e($type) ?>"<?= $selected($rule['adjustment_type'] === $type) ?>><?= $e($label) ?></option><?php endforeach; ?></select></div>
                <div class="field" data-pricing-percentage<?= $rule['adjustment_type'] === 'percentage' ? '' : ' hidden' ?>><label for="<?= $e($pricingFieldPrefix) ?>-percentage">Ricarico percentuale</label><input id="<?= $e($pricingFieldPrefix) ?>-percentage" type="number" name="percentage_value" min="0" step="0.01" value="<?= $rule['adjustment_type'] === 'percentage' ? $e(number_format(((int) $rule['adjustment_value']) / 100, 2, '.', '')) : '' ?>"<?= $rule['adjustment_type'] === 'percentage' ? ' required' : ' disabled' ?>></div>
                <div class="field" data-pricing-amount<?= $rule['adjustment_type'] === 'percentage' ? ' hidden' : '' ?>><label for="<?= $e($pricingFieldPrefix) ?>-value">Importo in centesimi</label><input id="<?= $e($pricingFieldPrefix) ?>-value" type="number" name="adjustment_value" min="0" value="<?= $rule['adjustment_type'] === 'percentage' ? '' : $e((string) $rule['adjustment_value']) ?>"<?= $rule['adjustment_type'] === 'percentage' ? ' disabled' : ' required' ?>></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-currency">Valuta</label><input id="<?= $e($pricingFieldPrefix) ?>-currency" name="currency" value="<?= $e($rule['currency']) ?>" pattern="[A-Z]{3}" maxlength="3" required></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-minimum">Prezzo minimo</label><input id="<?= $e($pricingFieldPrefix) ?>-minimum" type="number" name="minimum_price_minor" min="0" value="<?= $e($rule['minimum_price_minor'] === null ? '' : (string) $rule['minimum_price_minor']) ?>"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-maximum">Prezzo massimo</label><input id="<?= $e($pricingFieldPrefix) ?>-maximum" type="number" name="maximum_price_minor" min="0" value="<?= $e($rule['maximum_price_minor'] === null ? '' : (string) $rule['maximum_price_minor']) ?>"></div>
                <div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-priority">Priorità</label><input id="<?= $e($pricingFieldPrefix) ?>-priority" type="number" name="priority" min="0" max="100000" value="<?= $e((string) $rule['priority']) ?>" required></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-valid-from">Valida dal</label><input id="<?= $e($pricingFieldPrefix) ?>-valid-from" type="datetime-local" name="valid_from" value="<?= $e($rule['valid_from'] === null ? '' : substr(str_replace(' ', 'T', $rule['valid_from']), 0, 16)) ?>"></div><div class="field"><label for="<?= $e($pricingFieldPrefix) ?>-valid-until">Valida fino al</label><input id="<?= $e($pricingFieldPrefix) ?>-valid-until" type="datetime-local" name="valid_until" value="<?= $e($rule['valid_until'] === null ? '' : substr(str_replace(' ', 'T', $rule['valid_until']), 0, 16)) ?>"></div>
                <label><input type="checkbox" name="enabled" value="1"<?= $rule['enabled'] ? ' checked' : '' ?>> Regola abilitata</label><button class="button button--secondary" type="submit">Salva nuova versione</button>
            </form>
            <form action="/ui/catalog/pricing-rules/<?= $e((string) $rule['id']) ?>/retire" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($rule['retire_csrf_token']) ?>"><input type="hidden" name="version" value="<?= $e((string) $rule['version']) ?>"><input type="hidden" name="commercial_catalog_id" value="<?= $e((string) $selectedCatalog['id']) ?>"><button class="button button--ghost" type="submit">Ritira regola</button></form>
        </details></td></tr>
        <?php endif; ?>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>

<section class="panel catalog-preview" id="catalog-preview" aria-labelledby="catalog-preview-title">
    <div class="panel__header">
        <div><p class="eyebrow">Passaggio 3</p><h2 id="catalog-preview-title">Controlla cosa verrà passato</h2><p>L’anteprima non pubblica nulla: applica criteri e prezzi ai dati reali ricevuti da Space.</p></div>
        <div class="catalog-preview__count"><strong><?= $e((string) $selectedCatalog['eligible_product_count']) ?></strong><span>prodotti selezionati</span></div>
    </div>
    <div class="catalog-preview__body">
        <?php if (($catalogPreviewRequested ?? false) !== true): ?>
            <div class="catalog-preview__empty">
                <div><h3>Calcola l’anteprima prima di attivare</h3><p>Vedrai EAN, SKU, artista, titolo, formato, costo Space e prezzo finale per marketplace.</p></div>
                <a class="button button--primary" href="/ui/catalog?catalog=<?= $e((string) $selectedCatalog['id']) ?>&amp;preview=1#catalog-preview">Calcola anteprima</a>
            </div>
        <?php elseif (($catalogPreviewProducts ?? []) === []): ?>
            <div class="empty-state"><h3>Nessun prodotto selezionato</h3><p>Controlla di avere almeno un’inclusione, una regola prezzo attiva e criteri che trovino prodotti reali.</p></div>
        <?php else: ?>
            <?php if ($selectedCatalog['eligible_product_count'] > count($catalogPreviewProducts)): ?>
                <p class="catalog-preview__summary">Sono mostrati i primi <?= $e((string) ($catalogPreviewLimit ?? 200)) ?> prodotti dei <?= $e((string) $selectedCatalog['eligible_product_count']) ?> attivati.</p>
            <?php endif; ?>
            <div class="table-scroll catalog-preview__table-scroll">
                <table class="data-table">
                    <thead><tr><th>EAN</th><th>SKU</th><th>Artista</th><th>Titolo</th><th>Formato</th><th>Marketplace</th><th>Prezzo acquisto</th><th>Prezzo vendita</th></tr></thead>
                    <tbody>
                    <?php foreach ($catalogPreviewProducts as $product): ?>
                        <?php $productPrices = $product['price_previews'] ?? []; ?>
                        <?php if ($productPrices === []): ?>
                            <tr>
                                <td><?= $e($product['ean'] ?? '—') ?></td><td><strong><?= $e($product['sku']) ?></strong></td>
                                <td><?= $e($product['artist'] ?? '—') ?></td><td><?= $e($product['title'] ?? $product['name'] ?? '—') ?></td>
                                <td><?= $e($product['format'] ?? '—') ?></td><td>—</td>
                                <td><?= $e($formatMoney($product['purchase_cost_minor'], $product['currency'] ?? null)) ?></td><td>Regola prezzo mancante</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productPrices as $pricePreview): ?>
                            <tr>
                                <td><?= $e($product['ean'] ?? '—') ?></td><td><strong><?= $e($product['sku']) ?></strong></td>
                                <td><?= $e($product['artist'] ?? '—') ?></td><td><?= $e($product['title'] ?? $product['name'] ?? '—') ?></td>
                                <td><?= $e($product['format'] ?? '—') ?></td><td><?= $e($pricePreview['marketplace_name']) ?></td>
                                <td><?= $e($formatMoney($product['purchase_cost_minor'], $product['currency'] ?? null)) ?></td>
                                <td><strong><?= $e($formatMoney($pricePreview['selling_price_minor'], $pricePreview['currency'] ?? null)) ?></strong><?php if (($pricePreview['applied_rule_code'] ?? null) !== null): ?><small><?= $e($pricePreview['applied_rule_code']) ?></small><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (($currentUser?->role ?? '') === 'administrator'): ?>
<details class="panel" id="delete-commercial-catalog">
    <summary><strong>Cancella catalogo</strong></summary>
    <form class="auth-form" action="/ui/catalog/catalogs/<?= $e((string) $selectedCatalog['id']) ?>/delete" method="post">
        <input type="hidden" name="_csrf_token" value="<?= $e($deleteCommercialCatalogCsrfToken ?? '') ?>">
        <p>La cancellazione rimuove definitivamente regole prezzo, inclusioni, esclusioni e associazioni marketplace. Le offerte HAPA verranno ricalcolate.</p>
        <div class="field">
            <label for="catalog-delete-confirmation">Scrivi “<?= $e($selectedCatalog['name']) ?>” per confermare</label>
            <input id="catalog-delete-confirmation" name="confirmation" required autocomplete="off">
        </div>
        <button class="button button--danger" type="submit">Cancella definitivamente il catalogo</button>
    </form>
</details>
<?php endif; ?>
