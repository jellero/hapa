<header class="page-header">
    <div>
        <p class="eyebrow"><?= $e($eyebrow) ?></p>
        <h1><?= $e($title) ?></h1>
        <p class="page-header__description"><?= $e($description) ?></p>
    </div>
    <div class="page-header__actions">
        <?php if (($currentUser?->role ?? '') === 'administrator'): ?>
            <a class="button button--primary" href="#new-integration-account">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#plus"></use></svg>
                Aggiungi account
            </a>
        <?php endif; ?>
    </div>
</header>

<?php if (($saved ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Configurazione salvata</strong><span>Sincronizza la nuova versione con Automation; i job vengono attivati soltanto quando l’account entra in pilot o attivo.</span></div></output>
<?php endif; ?>
<?php if (($configurationError ?? '') !== ''): ?>
    <div class="inline-notice inline-notice--warning" role="alert"><div><strong>Configurazione non salvata</strong><span><?= $e($configurationError) ?></span></div></div>
<?php endif; ?>
<?php if (($secretsSaved ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Credenziali aggiornate</strong><span>I valori sono stati cifrati in HAPA Automation e non possono essere riletti dall’interfaccia.</span></div></output>
<?php endif; ?>
<?php if (($secretsRevoked ?? false) === true): ?>
    <output class="inline-notice inline-notice--warning"><div><strong>Credenziali revocate</strong><span>Il ciphertext è stato eliminato e l’account dovrà essere riconfigurato prima dell’uso.</span></div></output>
<?php endif; ?>
<?php if (($configurationSynced ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Configurazione sincronizzata</strong><span>Automation ha applicato la stessa versione non segreta visibile in HAPA.</span></div></output>
<?php endif; ?>
<?php if (($statusRefreshed ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Stato tecnico aggiornato</strong><span>Versioni e stato credenziali sono stati riletti direttamente da Automation.</span></div></output>
<?php endif; ?>
<?php if (($connectionTested ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Connessione provider verificata</strong><span>Credenziali ed endpoint configurati risultano raggiungibili.</span></div></output>
<?php endif; ?>
<?php if (($ordersImported ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Import SellRapido completato</strong><span><?= $e((string) ($ordersPublished ?? 0)) ?> osservazioni ordine pubblicate verso HAPA.</span></div></output>
<?php endif; ?>

<div class="inline-notice inline-notice--warning" role="note">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#alert"></use></svg>
    <div>
        <strong>Confini di integrazione espliciti</strong>
        <span>Per ogni account-canale resta attivo un solo percorso per ordini e offerte; Space alimenta prezzo e stock base, mentre HAPA pubblica ricarichi e quantità vendibili senza sovrascrivere la sorgente.</span>
    </div>
</div>

<?php if (($currentUser?->role ?? '') === 'administrator'): ?>
<section class="panel" id="new-integration-account" aria-labelledby="new-integration-title">
    <div class="panel__header"><div><p class="eyebrow">Amministrazione</p><h2 id="new-integration-title">Nuovo account tecnico</h2></div><span class="status-badge status-badge--warning">Nasce disabilitato</span></div>
    <form class="auth-form" action="/ui/integrations" method="post">
        <input type="hidden" name="_csrf_token" value="<?= $e($createIntegrationCsrfToken ?? '') ?>">
        <div class="field"><label for="integration-provider">Provider</label><select id="integration-provider" name="provider" required><?php foreach (array_keys($availableCapabilities) as $provider): ?><option value="<?= $e($provider) ?>"><?= $e(strtoupper($provider)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="integration-code">Codice account</label><input id="integration-code" name="code" required maxlength="96" placeholder="sellrapido-primary"></div>
        <div class="field"><label for="integration-name">Nome visualizzato</label><input id="integration-name" name="display_name" required maxlength="160" placeholder="SellRapido principale"></div>
        <div class="field"><label for="integration-environment">Ambiente</label><select id="integration-environment" name="environment"><option value="sandbox">Sandbox</option><option value="production">Produzione</option></select></div>
        <div class="field"><label for="integration-capabilities">Capacità abilitate</label><input id="integration-capabilities" name="capabilities" placeholder="products.read, orders.read"><small>Valori separati da virgola; devono appartenere al provider scelto.</small></div>
        <div class="field"><label for="integration-settings">Impostazioni non segrete (JSON)</label><textarea id="integration-settings" name="settings_json" rows="6">{}</textarea><small>Password, token, API key e cookie vengono rifiutati.</small></div>
        <div class="field"><label for="integration-description">Descrizione</label><textarea id="integration-description" name="description" rows="3" maxlength="1000"></textarea></div>
        <button class="button button--primary" type="submit">Crea account disabilitato</button>
    </form>
</section>
<?php endif; ?>

<section class="panel data-panel" aria-labelledby="configured-accounts-title">
    <div class="panel__header"><div><p class="eyebrow">Configurazione desiderata</p><h2 id="configured-accounts-title">Account tecnici configurati</h2></div><span class="section-heading__meta"><?= $e((string) count($configuredAccounts ?? [])) ?> account</span></div>
    <?php if (($configuredAccounts ?? []) === []): ?>
        <div class="empty-state empty-state--compact"><span class="empty-state__icon"><svg class="icon"><use href="/assets/icons.svg#integration"></use></svg></span><div><h3>Nessun account configurato</h3><p>Gli account nascono disabilitati e non contengono mai credenziali.</p></div></div>
    <?php else: ?>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>Account</th><th>Provider</th><th>Ambiente</th><th>Versione</th><th>Segreto</th><th>Test</th><th>Stato</th><th>Capacità</th></tr></thead><tbody>
        <?php foreach ($configuredAccounts as $account): ?>
            <tr>
                <td><strong><?= $e($account['display_name']) ?></strong><small><?= $e($account['code']) ?></small></td>
                <td><?= $e(strtoupper($account['provider_code'])) ?></td><td><?= $e($account['environment']) ?></td><td><strong>HAPA v<?= $e((string) $account['configuration_version']) ?></strong><small>Automation v<?= $e((string) $account['automation_configuration_version']) ?></small></td>
                <td><span class="status-badge status-badge--<?= $e($account['secret_status'] === 'configured' ? 'success' : 'warning') ?>"><?= $e($account['secret_status']) ?></span></td>
                <td><?= $e($account['connection_test_status']) ?></td><td><?= $e($account['desired_status']) ?></td><td><?= $e(implode(', ', $account['capabilities'])) ?></td>
            </tr>
            <?php if (($currentUser?->role ?? '') === 'administrator'): ?>
            <tr><td colspan="8"><details><summary>Gestisci account v<?= $e((string) $account['configuration_version']) ?></summary>
                <?php $accountFieldPrefix = 'integration-' . (string) $account['id']; ?>
                <form class="auth-form" action="/ui/integrations/<?= $e((string) $account['id']) ?>" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= $e($account['update_csrf_token']) ?>"><input type="hidden" name="configuration_version" value="<?= $e((string) $account['configuration_version']) ?>">
                    <input type="hidden" name="provider" value="<?= $e($account['provider_code']) ?>"><input type="hidden" name="code" value="<?= $e($account['code']) ?>">
                    <div class="field"><label for="<?= $e($accountFieldPrefix) ?>-name">Nome visualizzato</label><input id="<?= $e($accountFieldPrefix) ?>-name" name="display_name" value="<?= $e($account['display_name']) ?>" required maxlength="160"></div>
                    <div class="field"><label for="<?= $e($accountFieldPrefix) ?>-environment">Ambiente</label><select id="<?= $e($accountFieldPrefix) ?>-environment" name="environment"><option value="sandbox"<?= $account['environment'] === 'sandbox' ? ' selected' : '' ?>>Sandbox</option><option value="production"<?= $account['environment'] === 'production' ? ' selected' : '' ?>>Produzione</option></select></div>
                    <div class="field"><label for="<?= $e($accountFieldPrefix) ?>-capabilities">Capacità</label><input id="<?= $e($accountFieldPrefix) ?>-capabilities" name="capabilities" value="<?= $e(implode(', ', $account['capabilities'])) ?>"></div>
                    <div class="field"><label for="<?= $e($accountFieldPrefix) ?>-settings">Impostazioni non segrete (JSON)</label><textarea id="<?= $e($accountFieldPrefix) ?>-settings" name="settings_json" rows="6"><?= $e(json_encode($account['settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea></div>
                    <div class="field"><label for="<?= $e($accountFieldPrefix) ?>-description">Descrizione</label><textarea id="<?= $e($accountFieldPrefix) ?>-description" name="description" rows="3" maxlength="1000"><?= $e($account['description'] ?? '') ?></textarea></div>
                    <button class="button button--secondary" type="submit">Salva nuova versione</button>
                </form>
                <div class="auth-form">
                    <h3>Stato tecnico Automation</h3>
                    <p>Versione applicata <?= $e((string) $account['automation_configuration_version']) ?>; ultimo allineamento <?= $e($account['automation_configured_at'] ?? 'mai') ?>.</p>
                    <p>Ultima verifica <?= $e($account['technical_checked_at'] ?? 'mai') ?>; test connessione <?= $e($account['connection_test_status']) ?> <?= $e($account['connection_tested_at'] ?? '') ?>; scadenza token <?= $e($account['token_expires_at'] ?? 'non disponibile') ?>.</p>
                    <?php if (($account['last_error'] ?? '') !== ''): ?><div class="inline-notice inline-notice--warning"><span><?= $e($account['last_error']) ?></span></div><?php endif; ?>
                    <?php if ($account['automation_configuration_version'] !== $account['configuration_version']): ?>
                    <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/configuration/sync" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($account['sync_configuration_csrf_token']) ?>"><button class="button button--secondary" type="submit">Sincronizza configurazione</button></form>
                    <?php endif; ?>
                    <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/status/refresh" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($account['refresh_status_csrf_token']) ?>"><button class="button button--ghost" type="submit">Aggiorna stato tecnico</button></form>
                    <?php if (in_array($account['provider_code'], ['sellrapido', 'space'], true) && $account['secret_status'] === 'configured' && $account['automation_configuration_version'] === $account['configuration_version']): ?>
                    <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/connection-test" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($account['connection_test_csrf_token']) ?>"><button class="button button--secondary" type="submit">Verifica connessione <?= $e($account['provider_code'] === 'space' ? 'Space' : 'SellRapido') ?></button></form>
                    <?php endif; ?>
                    <?php if ($account['provider_code'] === 'sellrapido' && in_array($account['desired_status'], ['pilot', 'active'], true) && $account['connection_test_status'] === 'passed' && $account['automation_configuration_version'] === $account['configuration_version']): ?>
                    <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/orders/import" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($account['orders_import_csrf_token']) ?>"><button class="button button--primary" type="submit">Importa ordini ora</button></form>
                    <?php endif; ?>
                    <?php if ($account['provider_code'] === 'space' && in_array('catalog.read', $account['capabilities'], true) && in_array($account['desired_status'], ['pilot', 'active'], true) && $account['connection_test_status'] === 'passed' && $account['automation_configuration_version'] === $account['configuration_version']): ?>
                    <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/catalog/sync" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($account['catalog_sync_csrf_token']) ?>"><button class="button button--primary" type="submit">Leggi ora costo e disponibilità da Space</button></form>
                    <?php endif; ?>
                </div>
                <?php if ($account['desired_status'] !== 'retired'): ?>
                <form class="auth-form" action="/ui/integrations/<?= $e((string) $account['id']) ?>/secrets" method="post" autocomplete="off">
                    <input type="hidden" name="_csrf_token" value="<?= $e($account['replace_secrets_csrf_token']) ?>">
                    <h3>Credenziali API write-only</h3>
                    <p>Compila soltanto i valori da inserire o sostituire. I campi vuoti non modificano le credenziali già cifrate.</p>
                    <?php foreach ($account['secret_fields'] as $fieldName => $fieldLabel): ?>
                        <div class="field"><label for="secret-<?= $e((string) $account['id']) ?>-<?= $e($fieldName) ?>"><?= $e($fieldLabel) ?></label><input id="secret-<?= $e((string) $account['id']) ?>-<?= $e($fieldName) ?>" type="password" name="secrets[<?= $e($fieldName) ?>]" maxlength="8192" autocomplete="new-password" spellcheck="false"></div>
                    <?php endforeach; ?>
                    <button class="button button--secondary" type="submit">Salva credenziali cifrate</button>
                </form>
                <?php if ($account['secret_status'] === 'configured'): ?>
                <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/secrets/revoke" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= $e($account['revoke_secrets_csrf_token']) ?>">
                    <label><input type="checkbox" name="confirm_revoke" value="yes" required> Confermo la revoca delle credenziali correnti</label>
                    <button class="button button--ghost" type="submit">Revoca credenziali</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($account['desired_status'] !== 'retired'): ?>
                <form class="auth-form" action="/ui/integrations/<?= $e((string) $account['id']) ?>/status" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= $e($account['change_status_csrf_token']) ?>"><input type="hidden" name="configuration_version" value="<?= $e((string) $account['configuration_version']) ?>">
                    <div class="field"><label for="<?= $e($accountFieldPrefix) ?>-status">Stato desiderato</label><select id="<?= $e($accountFieldPrefix) ?>-status" name="target_status"><option value="disabled">Disabilitato</option><option value="suspended">Sospeso</option><option value="pilot">Pilot</option><option value="active">Attivo</option></select><small>Pilot e attivo richiedono credenziali, test connessione superato e versione Automation allineata.</small></div>
                    <?php if ($account['environment'] === 'production'): ?><label><input type="checkbox" name="confirm_production" value="yes"> Confermo esplicitamente l'attivazione in produzione</label><?php endif; ?>
                    <button class="button button--secondary" type="submit">Aggiorna stato desiderato</button>
                </form>
                <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/retire" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= $e($account['retire_csrf_token']) ?>"><input type="hidden" name="configuration_version" value="<?= $e((string) $account['configuration_version']) ?>">
                    <button class="button button--ghost" type="submit">Ritira account</button>
                </form>
                <?php endif; ?>
            </details></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<section aria-labelledby="integrations-grid-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Portafoglio</p>
            <h2 id="integrations-grid-title">Canali, servizi e corrieri</h2>
        </div>
        <span class="section-heading__meta"><?= $e(count($integrations)) ?> integrazioni censite</span>
    </div>

    <div class="integration-grid">
        <?php foreach ($integrations as $integration): ?>
            <article class="integration-card">
                <div class="integration-card__top">
                    <span class="integration-logo integration-logo--<?= $e($integration['code']) ?>" aria-hidden="true">
                        <?= $e(strtoupper(substr($integration['name'], 0, 2))) ?>
                    </span>
                    <span class="status-badge status-badge--<?= $e($integration['tone']) ?>"><?= $e($integration['status']) ?></span>
                </div>
                <div class="integration-card__copy">
                    <h3><?= $e($integration['name']) ?></h3>
                    <p><?= $e($integration['kind']) ?></p>
                </div>
                <div class="integration-card__meta">
                    <?php $integrationAccountCount = (int) (($accountCounts ?? [])[$integration['code']] ?? 0); ?>
                    <span><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#integration"></use></svg> <?= $e((string) $integrationAccountCount) ?> account configurati</span>
                </div>
                <?php if (array_key_exists($integration['code'], $availableCapabilities ?? [])): ?>
                    <a class="button button--ghost button--wide" href="#new-integration-account">Configura</a>
                <?php else: ?>
                    <button class="button button--ghost button--wide" type="button" disabled>Non disponibile</button>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel discovery-panel" aria-labelledby="discovery-title">
    <div class="panel__header">
        <div>
            <p class="eyebrow">Discovery</p>
            <h2 id="discovery-title">Gate prima dell’attivazione</h2>
        </div>
        <span class="section-heading__meta">Riferimenti: MARKETPLACES.md e CARRIERS.md</span>
    </div>
    <ol class="gate-grid">
        <li><span>01</span><strong>Specifiche e account test</strong><small>Contratto tecnico, ambiente prova e permessi reali</small></li>
        <li><span>02</span><strong>Capacità e limiti</strong><small>Operazioni, quote, paginazione e dati personali</small></li>
        <li><span>03</span><strong>Conformità adapter</strong><small>Fake, errori tipizzati e test condivisi</small></li>
        <li><span>04</span><strong>Pilot controllato</strong><small>Un account-canale, metriche e arresto rapido</small></li>
    </ol>
</section>
