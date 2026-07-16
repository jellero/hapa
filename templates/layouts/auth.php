<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#132238">
    <meta name="description" content="<?= $e($description ?? 'Accesso HAPA') ?>">
    <title><?= $e($title ?? 'Accesso') ?> · HAPA</title>
    <link rel="stylesheet" href="/assets/ui.css?v=5">
    <script defer src="/assets/ui.js?v=5"></script>
</head>
<body class="auth-body">
    <a class="skip-link" href="#auth-content">Vai al contenuto</a>
    <main class="auth-shell" id="auth-content">
        <section class="auth-story" aria-labelledby="auth-story-title">
            <a class="brand brand--inverse" href="/ui" aria-label="HAPA, centro operativo">
                <span class="brand__mark" aria-hidden="true">H</span>
                <span class="brand__copy">
                    <strong>HAPA</strong>
                    <small>Operations</small>
                </span>
            </a>

            <div class="auth-story__content">
                <p class="eyebrow eyebrow--inverse">Space → Prezzi e stock → Marketplace → Ordini e corrieri</p>
                <h1 id="auth-story-title">Ogni ordine.<br>Un solo controllo.</h1>
                <p>HAPA riunisce ordini, picking, spedizioni, retry e audit in un centro operativo progettato per decisioni rapide e tracciabili.</p>

                <ul class="auth-benefits">
                    <li><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#check"></use></svg> Stato autorevole e riconciliabile</li>
                    <li><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#check"></use></svg> Eccezioni evidenti e azioni controllate</li>
                    <li><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#check"></use></svg> Audit incorporato in ogni flusso</li>
                </ul>
            </div>

            <div class="auth-story__footer">
                <span class="environment-pill environment-pill--inverse">
                    <span class="environment-pill__dot" aria-hidden="true"></span>
                    Ambiente <?= $e($environment) ?>
                </span>
                <span>Correlation ID: <?= $e($correlationId ?: 'non disponibile') ?></span>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-panel__content">
                <?= $content ?>
            </div>
            <p class="auth-panel__legal">Accesso riservato agli operatori autorizzati. Le attività operative saranno registrate nell’audit.</p>
        </section>
    </main>
</body>
</html>
