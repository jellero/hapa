<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#132238">
    <meta name="description" content="<?= $e($description ?? 'Centro operativo HAPA') ?>">
    <title><?= $e($title ?? 'HAPA') ?> · HAPA</title>
    <link rel="stylesheet" href="/assets/ui.css?v=5">
    <script defer src="/assets/ui.js?v=5"></script>
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

            <nav class="sidebar__nav" aria-label="Sezioni principali">
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
                <p>Interfaccia operativa v0.8</p>
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
                    <a class="icon-button" href="/ui/audit" aria-label="Apri audit operativo">
                        <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#bell"></use></svg>
                    </a>
                    <a class="account-chip<?= $active === 'profile' ? ' is-active' : '' ?>" href="/ui/profile">
                        <span class="account-chip__avatar" aria-hidden="true">OP</span>
                        <span class="account-chip__copy">
                            <strong><?= $e($currentUser?->displayName ?? 'Operatore') ?></strong>
                            <small><?= $e($currentUser?->role ?? 'sessione attiva') ?></small>
                        </span>
                        <svg class="icon icon--small" aria-hidden="true"><use href="/assets/icons.svg#chevron-down"></use></svg>
                    </a>
                    <form action="/logout" method="post">
                        <input type="hidden" name="_csrf_token" value="<?= $e($logoutCsrfToken ?? '') ?>">
                        <button class="icon-button" type="submit" aria-label="Esci da HAPA">
                            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#lock"></use></svg>
                        </button>
                    </form>
                </div>
            </header>

            <div class="preview-banner" role="status" data-preview-banner>
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg>
                <div>
                    <strong>Sessione protetta attiva</strong>
                    <span>Le funzioni disponibili dipendono dal ruolo; le azioni mutative richiedono CSRF e vengono registrate nell’audit.</span>
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
