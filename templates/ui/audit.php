<?php
$formatDate = static function (string $value): string {
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i:s');
    } catch (Throwable) {
        return $value;
    }
};
$formatJson = static function (?array $value): string {
    if ($value === null) {
        return '—';
    }
    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $encoded === false ? '{}' : $encoded;
};
?>
<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
</header>

<section class="panel data-panel" aria-labelledby="audit-results-title">
    <div class="panel__header">
        <div><p class="eyebrow">Registro append-only</p><h2 id="audit-results-title">Eventi registrati</h2></div>
        <span class="section-heading__meta"><?= $e((string) count($auditEntries ?? [])) ?> eventi visualizzati</span>
    </div>

    <form class="data-toolbar" method="get" action="/ui/audit" role="search">
        <label class="search-field" for="audit-query">
            <span class="sr-only">Cerca nel registro audit</span>
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg>
            <input id="audit-query" type="search" name="q" value="<?= $e($query ?? '') ?>" placeholder="Attore, azione, entità o correlation ID">
        </label>
        <div class="toolbar-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#filter"></use></svg>
            <label class="sr-only" for="audit-entity-type">Filtra per entità</label>
            <select id="audit-entity-type" name="entity_type">
                <option value="">Tutte le entità</option>
                <?php foreach (($entityTypes ?? []) as $entityType): ?>
                    <option value="<?= $e($entityType) ?>"<?= ($selectedEntityType ?? '') === $entityType ? ' selected' : '' ?>><?= $e($entityType) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button--secondary" type="submit">Applica</button>
        <?php if (($query ?? '') !== '' || ($selectedEntityType ?? '') !== ''): ?><a class="button button--ghost" href="/ui/audit">Azzera</a><?php endif; ?>
    </form>

    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Data e ora</th><th>Attore</th><th>Azione</th><th>Entità</th><th>Correlation ID</th><th>Dettaglio</th></tr></thead>
            <tbody>
            <?php if (($auditEntries ?? []) === []): ?>
                <tr><td colspan="6"><div class="empty-state"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#audit"></use></svg></span><h3>Nessun evento trovato</h3><p>Modifica i filtri oppure esegui un’azione operativa autorizzata.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($auditEntries as $entry): ?>
                    <tr>
                        <td><time datetime="<?= $e($entry['created_at']) ?>"><?= $e($formatDate($entry['created_at'])) ?></time></td>
                        <td><strong><?= $e($entry['actor_name'] ?? 'Sistema') ?></strong><small><?= $e($entry['actor_email'] ?? $entry['actor_id'] ?? '—') ?></small></td>
                        <td><code><?= $e($entry['action']) ?></code></td>
                        <td><strong><?= $e($entry['entity_type']) ?></strong><small><?= $e($entry['entity_id']) ?></small></td>
                        <td><code><?= $e($entry['correlation_id'] ?? '—') ?></code></td>
                        <td><details><summary>Mostra</summary><strong>Prima</strong><pre><?= $e($formatJson($entry['before'])) ?></pre><strong>Dopo</strong><pre><?= $e($formatJson($entry['after'])) ?></pre></details></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
