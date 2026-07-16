<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <button class="button button--primary" type="button" disabled>
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#edit"></use></svg>
            Modifica
        </button>
    </div>
</header>

<div class="settings-grid">
    <?php foreach ($groups as $group): ?>
        <section class="panel settings-card">
            <div class="panel__header">
                <div>
                    <h2><?= $e($group['title']) ?></h2>
                    <p><?= $e($group['description']) ?></p>
                </div>
                <button class="icon-button" type="button" disabled aria-label="Modifica <?= $e($group['title']) ?>">
                    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#edit"></use></svg>
                </button>
            </div>
            <dl class="settings-list">
                <?php foreach ($group['fields'] as $field): ?>
                    <div>
                        <dt><?= $e($field['label']) ?></dt>
                        <dd><?= $e($field['value']) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endforeach; ?>
</div>

<div class="inline-notice" role="note">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg>
    <div>
        <strong>Configurazione protetta</strong>
        <span>Credenziali, URL provider e altri secret non saranno mai mostrati integralmente nell’interfaccia.</span>
    </div>
</div>
