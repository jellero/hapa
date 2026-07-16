<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <button class="button button--primary" type="button" disabled title="Funzione disponibile dopo il collegamento del caso d’uso">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#plus"></use></svg>
            <?= $e($primaryAction) ?>
        </button>
    </div>
</header>

<section class="panel data-panel" aria-labelledby="<?= $e($active) ?>-results-title">
    <h2 class="sr-only" id="<?= $e($active) ?>-results-title">Elenco <?= $e(strtolower($title)) ?></h2>

    <form class="data-toolbar" method="get" action="<?= $e($clearUrl) ?>" role="search">
        <div class="search-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#search"></use></svg>
            <label class="sr-only" for="search-<?= $e($active) ?>"><?= $e($searchLabel) ?></label>
            <input id="search-<?= $e($active) ?>" type="search" name="q" value="<?= $e($query) ?>" placeholder="<?= $e($searchLabel) ?>">
        </div>
        <div class="toolbar-field">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#filter"></use></svg>
            <label class="sr-only" for="status-<?= $e($active) ?>">Filtra per stato</label>
            <select id="status-<?= $e($active) ?>" name="status">
                <?php foreach ($filters as $filter): ?>
                    <option value="<?= $e($filter) ?>"<?= $selectedFilter === $filter ? ' selected' : '' ?>><?= $e($filter) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button--secondary" type="submit">Applica</button>
        <?php if ($query !== '' || $selectedFilter !== ''): ?>
            <a class="button button--ghost" href="<?= $e($clearUrl) ?>">Azzera</a>
        <?php endif; ?>
    </form>

    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th scope="col"><?= $e($column) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="<?= $e(count($columns)) ?>">
                        <div class="empty-state">
                            <span class="empty-state__icon" aria-hidden="true">
                                <svg class="icon"><use href="/assets/icons.svg#<?= $e($emptyIcon) ?>"></use></svg>
                            </span>
                            <h3><?= $e($emptyTitle) ?></h3>
                            <p><?= $e($emptyBody) ?></p>
                            <?php if ($query !== ''): ?>
                                <span class="empty-state__query">Ricerca applicata: “<?= $e($query) ?>”</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <footer class="data-panel__footer">
        <span>0 risultati</span>
        <div class="pagination" aria-label="Paginazione">
            <button class="icon-button icon-button--compact" type="button" disabled aria-label="Pagina precedente">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-left"></use></svg>
            </button>
            <span>Pagina 1 di 1</span>
            <button class="icon-button icon-button--compact" type="button" disabled aria-label="Pagina successiva">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-right"></use></svg>
            </button>
        </div>
    </footer>
</section>
