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
$selected = static fn (string $actual, string $expected): string => $actual === $expected ? ' selected' : '';
$filters = $selectedFilters ?? [];
$options = $productFilterOptions ?? ['feeds' => [], 'formats' => [], 'suppliers' => []];
?>

<header class="page-header">
    <div>
        <p class="eyebrow">Anagrafica prodotti</p>
        <h1>Prodotti importati da Space</h1>
        <p class="page-header__description">Ricerca e consulta copertina, identificativi, prezzo, disponibilità e dati editoriali ricevuti dal feed Space.</p>
    </div>
    <div class="page-header__actions">
        <a class="button button--secondary" href="/ui/catalog">Cataloghi e prezzi</a>
    </div>
</header>

<section class="metric-grid" aria-label="Stato prodotti importati">
    <article class="metric-card metric-card--info"><div class="metric-card__top"><span>Prodotti</span></div><strong><?= $e((string) ($productMetrics['total'] ?? 0)) ?></strong><p>Articoli censiti da Space.</p></article>
    <article class="metric-card metric-card--success"><div class="metric-card__top"><span>In stock</span></div><strong><?= $e((string) ($productMetrics['in_stock'] ?? 0)) ?></strong><p>Disponibilità immediata maggiore di zero.</p></article>
    <article class="metric-card metric-card--warning"><div class="metric-card__top"><span>Backorder</span></div><strong><?= $e((string) ($productMetrics['backorder'] ?? 0)) ?></strong><p>Senza stock immediato, ma ordinabili dal fornitore.</p></article>
    <article class="metric-card metric-card--warning"><div class="metric-card__top"><span>Non disponibili</span></div><strong><?= $e((string) ($productMetrics['unavailable'] ?? 0)) ?></strong><p>Senza stock e senza consegna prevista.</p></article>
</section>

<section class="panel" aria-labelledby="product-filters-title">
    <div class="panel__header">
        <div><p class="eyebrow">Ricerca prodotti</p><h2 id="product-filters-title">Filtri anagrafica</h2></div>
    </div>
    <form class="integration-create__form" method="get" action="/ui/products">
        <div class="integration-create__grid">
            <div class="field integration-create__wide">
                <label for="product-query">SKU, EAN, ID Space, artista, titolo o etichetta</label>
                <input id="product-query" type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Cerca nel feed prodotti">
            </div>
            <div class="field">
                <label for="product-availability">Disponibilità</label>
                <select id="product-availability" name="availability">
                    <option value="">Tutte</option>
                    <option value="in_stock"<?= $selected((string) ($filters['availability'] ?? ''), 'in_stock') ?>>In stock</option>
                    <option value="backorder"<?= $selected((string) ($filters['availability'] ?? ''), 'backorder') ?>>Backorder</option>
                    <option value="unavailable"<?= $selected((string) ($filters['availability'] ?? ''), 'unavailable') ?>>Non disponibile</option>
                </select>
            </div>
            <div class="field">
                <label for="product-status">Revisione</label>
                <select id="product-status" name="status">
                    <option value="">Tutti gli stati</option>
                    <option value="pending_review"<?= $selected((string) ($filters['status'] ?? ''), 'pending_review') ?>>Da verificare</option>
                    <option value="approved"<?= $selected((string) ($filters['status'] ?? ''), 'approved') ?>>Approvato</option>
                    <option value="rejected"<?= $selected((string) ($filters['status'] ?? ''), 'rejected') ?>>Rifiutato</option>
                </select>
            </div>
            <div class="field">
                <label for="product-feed">Feed</label>
                <select id="product-feed" name="feed_name"><option value="">Tutti i feed</option><?php foreach ($options['feeds'] as $value): ?><option value="<?= $e($value) ?>"<?= $selected((string) ($filters['feed_name'] ?? ''), $value) ?>><?= $e($value) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field">
                <label for="product-format">Formato</label>
                <select id="product-format" name="format"><option value="">Tutti i formati</option><?php foreach ($options['formats'] as $value): ?><option value="<?= $e($value) ?>"<?= $selected((string) ($filters['format'] ?? ''), $value) ?>><?= $e($value) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field">
                <label for="product-supplier">Fornitore Space</label>
                <select id="product-supplier" name="supplier_id"><option value="">Tutti i fornitori</option><?php foreach ($options['suppliers'] as $supplier): ?><option value="<?= $e($supplier['id']) ?>"<?= $selected((string) ($filters['supplier_id'] ?? ''), $supplier['id']) ?>>#<?= $e($supplier['id']) ?><?= $supplier['name'] === null ? '' : ' · ' . $e($supplier['name']) ?></option><?php endforeach; ?></select>
            </div>
        </div>
        <div class="integration-create__footer">
            <span><?= $e((string) count($products ?? [])) ?> prodotti visualizzati, massimo 200 per ricerca.</span>
            <div><a class="button button--ghost" href="/ui/products">Azzera filtri</a> <button class="button button--primary" type="submit">Applica filtri</button></div>
        </div>
    </form>
</section>

<section class="panel product-results" aria-labelledby="products-list-title">
    <div class="panel__header">
        <div><p class="eyebrow">Risultati</p><h2 id="products-list-title">Prodotti Space</h2></div>
        <span class="section-heading__meta"><?= $e((string) count($products ?? [])) ?> risultati</span>
    </div>

    <?php if (($products ?? []) === []): ?>
        <div class="empty-state"><h3>Nessun prodotto trovato</h3><p>Modifica i filtri oppure verifica la sincronizzazione del feed Space.</p></div>
    <?php else: ?>
        <div class="product-list" role="list">
            <?php foreach ($products as $product): ?>
                <?php
                $stock = (int) ($product['available_quantity'] ?? 0);
                $backorder = (int) ($product['backorder_quantity'] ?? 0);
                $delivery = (int) ($product['delivery_time_days'] ?? 0);
                $availabilityLabel = $stock > 0 ? 'In stock' : ($backorder > 0 ? 'Backorder' : 'Non disponibile');
                $availabilityTone = $stock > 0 ? 'success' : 'warning';
                $displayName = trim((string) ($product['artist'] ?? '') . ' - ' . (string) ($product['title'] ?? ''), " -");
                if ($displayName === '') {
                    $displayName = (string) ($product['name'] ?? 'Prodotto senza titolo');
                }
                $imageUrl = is_string($product['image_url'] ?? null) && trim((string) $product['image_url']) !== ''
                    ? (string) $product['image_url']
                    : null;
                ?>
                <article class="product-result" role="listitem">
                    <div class="product-result__cover">
                        <?php if ($imageUrl !== null): ?>
                            <button
                                class="product-result__cover-button"
                                type="button"
                                data-image-preview-open
                                data-image-src="<?= $e($imageUrl) ?>"
                                data-image-title="<?= $e($displayName) ?>"
                                aria-label="Ingrandisci la copertina di <?= $e($displayName) ?>"
                            >
                                <img src="<?= $e($imageUrl) ?>" alt="Copertina di <?= $e($displayName) ?>" loading="lazy" decoding="async">
                            </button>
                        <?php else: ?>
                            <div class="product-result__cover-placeholder" aria-label="Copertina non disponibile"><?= $e((string) ($product['format'] ?? '—')) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="product-result__main">
                        <div class="product-result__title-line">
                            <h3><?= $e($displayName) ?></h3>
                        </div>
                        <p><?= $e((string) ($product['label'] ?? 'Etichetta non disponibile')) ?><?php if (($product['format'] ?? null) !== null): ?> · <?= $e((string) $product['format']) ?><?php endif; ?><?php if (($product['release_date'] ?? null) !== null): ?> · <?= $e((string) $product['release_date']) ?><?php endif; ?></p>
                        <div class="product-result__tags">
                            <?php if (($product['format'] ?? null) !== null): ?><span><?= $e((string) $product['format']) ?></span><?php endif; ?>
                            <?php if (($product['category'] ?? null) !== null): ?><span><?= $e((string) $product['category']) ?></span><?php endif; ?>
                            <?php if (($product['space_supplier_id'] ?? null) !== null): ?><span>Fornitore #<?= $e((string) $product['space_supplier_id']) ?></span><?php endif; ?>
                        </div>
                    </div>

                    <div class="product-result__facts product-result__facts--identifiers">
                        <span class="product-result__label">Identificativi</span>
                        <strong><?= $e((string) $product['sku']) ?></strong>
                        <span>EAN <?= $e((string) ($product['ean'] ?? '—')) ?></span>
                        <span>ID Space <?= $e((string) ($product['external_item_id'] ?? '—')) ?></span>
                    </div>

                    <div class="product-result__facts product-result__facts--commercial">
                        <span class="product-result__label">Costo Space</span>
                        <strong><?= $e($formatMoney($product['purchase_cost_minor'], $product['currency'])) ?></strong>
                        <span>Vendibile HAPA: <?= $e((string) $product['sellable_quantity']) ?></span>
                    </div>

                    <div class="product-result__facts product-result__facts--availability">
                        <span class="status-badge status-badge--<?= $availabilityTone ?>"><?= $e($availabilityLabel) ?></span>
                        <strong><?= $stock > 0 ? $e((string) $stock) . ' in stock' : ($backorder > 0 ? $e((string) $backorder) . ' in backorder' : '0 disponibili') ?></strong>
                        <span><?= $delivery > 0 ? $e('Consegna in ' . $delivery . ' gg') : 'Consegna non indicata' ?></span>
                    </div>

                    <div class="product-result__facts product-result__facts--freshness">
                        <span class="product-result__label">Ultimo dato</span>
                        <strong><?= $e($formatAge($product['age_seconds'])) ?></strong>
                        <span><?= $e((string) ($product['feed_name'] ?? 'Space')) ?></span>
                    </div>

                    <details class="product-result__details">
                        <summary>Scheda completa</summary>
                        <div class="product-detail">
                            <section>
                                <h4>Dati editoriali</h4>
                                <dl class="product-attributes">
                                    <div><dt>Artista</dt><dd><?= $e((string) ($product['artist'] ?? '—')) ?></dd></div>
                                    <div><dt>Titolo</dt><dd><?= $e((string) ($product['title'] ?? '—')) ?></dd></div>
                                    <div><dt>Etichetta</dt><dd><?= $e((string) ($product['label'] ?? '—')) ?></dd></div>
                                    <div><dt>Formato</dt><dd><?= $e((string) ($product['format'] ?? '—')) ?></dd></div>
                                    <div><dt>Categoria</dt><dd><?= $e((string) ($product['category'] ?? '—')) ?></dd></div>
                                    <div><dt>Famiglia / gruppo</dt><dd><?= $e(trim((string) ($product['family'] ?? '') . ' / ' . (string) ($product['group'] ?? ''), ' /') ?: '—') ?></dd></div>
                                    <div><dt>Data uscita</dt><dd><?= $e((string) ($product['release_date'] ?? '—')) ?></dd></div>
                                    <div><dt>Peso</dt><dd><?= $e(trim((string) ($product['weight'] ?? '') . ' ' . (string) ($product['weight_unit'] ?? '')) ?: '—') ?></dd></div>
                                </dl>
                            </section>
                            <section>
                                <h4>Fornitura Space</h4>
                                <dl class="product-attributes">
                                    <div><dt>SKU Space</dt><dd><?= $e((string) ($product['supplier_sku'] ?? '—')) ?></dd></div>
                                    <div><dt>Feed / fornitore</dt><dd><?= $e((string) ($product['feed_name'] ?? '—')) ?> / <?= $e((string) ($product['space_supplier_name'] ?? 'Fornitore #' . ($product['space_supplier_id'] ?? '—'))) ?></dd></div>
                                    <div><dt>Costo</dt><dd><?= $e($formatMoney($product['purchase_cost_minor'], $product['currency'])) ?></dd></div>
                                    <div><dt>Stock Space</dt><dd><?= $e((string) $stock) ?></dd></div>
                                    <div><dt>Backorder fornitore</dt><dd><?= $e((string) $backorder) ?></dd></div>
                                    <div><dt>Tempo consegna</dt><dd><?= $e($delivery > 0 ? $delivery . ' giorni' : '—') ?></dd></div>
                                    <div><dt>Stato sorgente</dt><dd><?= $e((string) ($product['source_status'] ?? '—')) ?></dd></div>
                                    <div><dt>Precisione</dt><dd><?= $e((string) ($product['precision_score'] ?? '—')) ?></dd></div>
                                    <div><dt>Osservato il</dt><dd><?= $e((string) ($product['observed_at'] ?? '—')) ?></dd></div>
                                </dl>
                            </section>
                            <section>
                                <h4>Stato HAPA</h4>
                                <dl class="product-attributes">
                                    <div><dt>Revisione</dt><dd><?= $e((string) $product['onboarding_status']) ?></dd></div>
                                    <div><dt>Vendibile HAPA</dt><dd><?= $e((string) $product['sellable_quantity']) ?></dd></div>
                                    <div><dt>Offerte HAPA</dt><dd><?= $e((string) $product['marketplace_offer_count']) ?></dd></div>
                                    <div><dt>Attivo in Space</dt><dd><?= ($product['offer_active'] ?? false) ? 'Sì' : 'No' ?></dd></div>
                                    <div><dt>Mancante dalla sorgente</dt><dd><?= ($product['missing_from_source'] ?? false) ? 'Sì' : 'No' ?></dd></div>
                                    <div><dt>Temu abilitato</dt><dd><?= ($product['temu_sync_enabled'] ?? false) ? 'Sì' : 'No' ?></dd></div>
                                    <div><dt>Versione prodotto</dt><dd><?= $e((string) $product['version']) ?></dd></div>
                                    <div><dt>Scorta sicurezza</dt><dd><?= $e((string) $product['safety_stock']) ?></dd></div>
                                </dl>
                            </section>
                            <details class="source-payload">
                                <summary>Dati tecnici sorgente</summary>
                                <p>Versione <?= $e((string) ($product['source_version'] ?? '—')) ?></p>
                                <pre><code><?= $e((string) ($product['source_attributes_json'] ?? '{}')) ?></code></pre>
                            </details>
                        </div>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<dialog class="image-preview" data-image-preview aria-labelledby="image-preview-title">
    <div class="image-preview__header">
        <div>
            <p class="eyebrow">Anteprima copertina</p>
            <h2 id="image-preview-title" data-image-preview-title>Copertina prodotto</h2>
        </div>
        <button class="image-preview__close" type="button" data-image-preview-close aria-label="Chiudi anteprima">×</button>
    </div>
    <div class="image-preview__canvas">
        <img data-image-preview-image src="" alt="">
    </div>
</dialog>
