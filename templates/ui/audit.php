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
        <div class="toolbar-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#alert"></use></svg>
            <label class="sr-only" for="audit-level">Filtra per esito</label>
            <select id="audit-level" name="level">
                <option value="">Tutti gli esiti</option>
                <option value="error"<?= ($selectedLevel ?? '') === 'error' ? ' selected' : '' ?>>Solo errori</option>
            </select>
        </div>
        <button class="button button--secondary" type="submit">Applica</button>
        <?php if (($query ?? '') !== '' || ($selectedEntityType ?? '') !== '' || ($selectedLevel ?? '') !== ''): ?><a class="button button--ghost" href="/ui/audit">Azzera</a><?php endif; ?>
    </form>

    <div class="table-scroll">
        <table class="data-table audit-table">
            <colgroup><col class="audit-table__date"><col class="audit-table__actor"><col class="audit-table__action"><col class="audit-table__entity"><col class="audit-table__correlation"><col class="audit-table__detail"></colgroup>
            <thead><tr><th>Data e ora</th><th>Attore</th><th>Azione</th><th>Entità</th><th>Correlation ID</th><th>Dettaglio</th></tr></thead>
            <tbody>
            <?php if (($auditEntries ?? []) === []): ?>
                <tr><td colspan="6"><div class="empty-state"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#audit"></use></svg></span><h3>Nessun evento trovato</h3><p>Modifica i filtri oppure esegui un’azione operativa autorizzata.</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($auditEntries as $entry): ?>
                    <?php $diagnostic = is_array($entry['diagnostic'] ?? null) ? $entry['diagnostic'] : null; ?>
                    <tr<?= $diagnostic !== null ? ' class="audit-table__error-row"' : '' ?>>
                        <td><time datetime="<?= $e($entry['created_at']) ?>"><?= $e($formatDate($entry['created_at'])) ?></time></td>
                        <td><strong><?= $e($entry['actor_name'] ?? 'Sistema') ?></strong><small><?= $e($entry['actor_email'] ?? $entry['actor_id'] ?? '—') ?></small></td>
                        <td><code><?= $e($entry['action']) ?></code></td>
                        <td><strong><?= $e($entry['entity_type']) ?></strong><small><?= $e($entry['entity_id']) ?></small></td>
                        <td><code><?= $e($entry['correlation_id'] ?? '—') ?></code></td>
                        <td class="audit-table__detail-cell">
                            <?php if ($diagnostic !== null): ?>
                                <div class="audit-error-card">
                                    <div class="audit-error-card__heading"><span class="status-badge status-badge--danger">Errore</span><strong><?= $e($diagnostic['message']) ?></strong></div>
                                    <?php if ($diagnostic['cause'] !== null): ?><p><?= $e($diagnostic['cause']) ?></p><?php endif; ?>
                                    <dl class="audit-diagnostic">
                                        <?php if ($diagnostic['field'] !== null): ?><div><dt>Campo</dt><dd><code><?= $e($diagnostic['field']) ?></code></dd></div><?php endif; ?>
                                        <?php if ($diagnostic['observed'] !== null): ?><div><dt>Ricevuto</dt><dd><?= $e($diagnostic['observed']) ?></dd></div><?php endif; ?>
                                        <?php if ($diagnostic['expected'] !== null): ?><div><dt>Atteso</dt><dd><?= $e($diagnostic['expected']) ?></dd></div><?php endif; ?>
                                        <?php if ($diagnostic['value'] !== null): ?><div class="audit-diagnostic__value"><dt>Valore</dt><dd title="<?= $e($diagnostic['value']) ?>"><?= $e($diagnostic['value']) ?></dd></div><?php endif; ?>
                                    </dl>
                                </div>
                            <?php endif; ?>
                            <details class="audit-raw-details">
                                <summary><?= $diagnostic === null ? 'Mostra dettaglio' : 'Apri payload tecnico' ?></summary>
                                <?php if ($entry['before'] !== null): ?><strong>Prima</strong><pre><?= $e($formatJson($entry['before'])) ?></pre><?php endif; ?>
                                <strong><?= $entry['before'] === null ? 'Dati evento' : 'Dopo' ?></strong>
                                <pre><?= $e($formatJson($entry['after'])) ?></pre>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
