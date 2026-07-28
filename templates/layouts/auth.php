<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#132238">
    <meta name="description" content="<?= $e($description ?? 'Accesso HAPA') ?>">
    <title><?= $e($title ?? 'Accesso') ?> · HAPA</title>
    <link rel="stylesheet" href="/assets/ui.css?v=7">
    <script defer src="/assets/ui.js?v=7"></script>
</head>
<body class="auth-body">
    <a class="skip-link" href="#auth-content">Vai al contenuto</a>
    <main class="auth-shell" id="auth-content">
        <section class="auth-panel" aria-labelledby="auth-portal-title">
            <div class="auth-panel__brand">
            <a class="brand" href="/ui" aria-label="HAPA">
                <span class="brand__mark" aria-hidden="true">H</span>
                <span class="brand__copy">
                    <strong>HAPA</strong>
                    <small>Azienda</small>
                </span>
            </a>
                <div>
                    <p class="auth-panel__company">Portale operativo HAPA</p>
                    <h2 id="auth-portal-title">Accesso ai servizi aziendali</h2>
                </div>
            </div>

            <div class="auth-panel__card">
                <?= $content ?>
            </div>
            <p class="auth-panel__legal">Accesso riservato al personale autorizzato.</p>
            <div class="auth-technical">
                <span class="environment-pill environment-pill--inverse">
                    <span class="environment-pill__dot" aria-hidden="true"></span>
                    Ambiente <?= $e($environment) ?>
                </span>
                <span>Correlation ID: <?= $e($correlationId ?: 'non disponibile') ?></span>
            </div>
        </section>
    </main>
</body>
</html>
