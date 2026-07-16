<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#132238">
    <meta name="description" content="<?= $e($description ?? 'Centro operativo HAPA') ?>">
    <title><?= $e($title ?? 'HAPA') ?> · HAPA</title>
    <link rel="stylesheet" href="/assets/ui.css?v=1">
    <script defer src="/assets/ui.js?v=1"></script>
</head>
<body class="app-body" data-ui-shell>
    <a class="skip-link" href="#main-content">Vai al contenuto</a>

    <div class="app-frame">
        <aside class="sidebar" id="app-sidebar" aria-label="Navigazione principale" data-sidebar>
            <div class="sidebar__brand">
                <a class="brand" href="/ui" aria-label="HAPA, centro operativo">
                    <span class="brand__mark" aria-hidden="true">H</span>
                    <span class="brand__copy">
                        <strong>HAPA</strong>
                        <small>Operations</small>
                    </span>
                </a>
                <button class="icon-button sidebar__close" type="button" aria-label="Chiudi navigazione" data-nav-close>
                    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#close"></use></svg>
                </button>
            </div>

            <nav class="sidebar__nav">
                <?php foreach ($navigation as $group): ?>
                    <section class="nav-group" aria-labelledby="nav-<?= $e(strtolower($group['label'])) ?>">
                        <div class="nav-group__label" id="nav-<?= $e(strtolower($group['label'])) ?>"><?= $e($group['label']) ?></div>
                        <ul class="nav-list">
                            <?php foreach ($group['items'] as $item): ?>
                                <?php $isActive = $active === $item['active']; ?>
                                <li>
                                    <a class="nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= $e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                                        <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#<?= $e($item['icon']) ?>"></use></svg>
                                        <span><?= $e($item['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar__footer">
                <div class="environment-pill">
                    <span class="environment-pill__dot" aria-hidden="true"></span>
                    <span>Ambiente <?= $e($environment) ?></span>
                </div>
                <p>Interfaccia operativa v0.4</p>
            </div>
        </aside>

        <button class="sidebar-backdrop" type="button" aria-label="Chiudi navigazione" tabindex="-1" data-nav-close></button>

        <div class="workspace">
            <header class="topbar">
                <div class="topbar__start">
                    <button class="icon-button topbar__menu" type="button" aria-label="Apri navigazione" aria-controls="app-sidebar" aria-expanded="false" data-nav-open>
                        <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#menu"></use></svg>
                    </button>
                    <div class="topbar__context">
                        <span>HAPA</span>
                        <svg class="icon icon--small" aria-hidden="true"><use href="/assets/icons.svg#chevron"></use></svg>
                        <strong><?= $e($title ?? 'Centro operativo') ?></strong>
                    </div>
                </div>

                <div class="topbar__actions">
                    <a class="icon-button" href="/ui/automation" aria-label="Apri notifiche operative">
                        <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#bell"></use></svg>
                    </a>
                    <a class="account-chip<?= $active === 'profile' ? ' is-active' : '' ?>" href="/ui/profile">
                        <span class="account-chip__avatar" aria-hidden="true">OP</span>
                        <span class="account-chip__copy">
                            <strong>Anteprima operatore</strong>
                            <small>Sessione non attiva</small>
                        </span>
                        <svg class="icon icon--small" aria-hidden="true"><use href="/assets/icons.svg#chevron-down"></use></svg>
                    </a>
                </div>
            </header>

            <div class="preview-banner" role="status" data-preview-banner>
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg>
                <div>
                    <strong>Interfaccia pronta, dati non ancora collegati</strong>
                    <span>Le schermate non espongono dati reali né azioni mutative finché autenticazione, dominio e repository non saranno attivi.</span>
                </div>
                <button class="icon-button icon-button--compact" type="button" aria-label="Nascondi avviso" data-banner-dismiss>
                    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#close"></use></svg>
                </button>
            </div>

            <main class="main-content" id="main-content" tabindex="-1">
                <?= $content ?>
            </main>

            <footer class="app-footer">
                <span>HAPA · piattaforma operativa proprietaria</span>
                <span>Correlation ID: <code><?= $e($correlationId ?: 'non disponibile') ?></code></span>
            </footer>
        </div>
    </div>
</body>
</html>
