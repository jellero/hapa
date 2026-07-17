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
    <div class="inline-notice inline-notice--info" role="status"><div><strong>Configurazione salvata</strong><span>La nuova versione non abilita automaticamente alcun job provider.</span></div></div>
<?php endif; ?>
<?php if (($configurationError ?? '') !== ''): ?>
    <div class="inline-notice inline-notice--warning" role="alert"><div><strong>Configurazione non salvata</strong><span><?= $e($configurationError) ?></span></div></div>
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
                <td><?= $e(strtoupper($account['provider_code'])) ?></td><td><?= $e($account['environment']) ?></td><td><?= $e((string) $account['configuration_version']) ?></td>
                <td><span class="status-badge status-badge--<?= $e($account['secret_status'] === 'configured' ? 'success' : 'warning') ?>"><?= $e($account['secret_status']) ?></span></td>
                <td><?= $e($account['connection_test_status']) ?></td><td><?= $e($account['desired_status']) ?></td><td><?= $e(implode(', ', $account['capabilities'])) ?></td>
            </tr>
            <?php if (($currentUser?->role ?? '') === 'administrator'): ?>
            <tr><td colspan="8"><details><summary>Modifica configurazione v<?= $e((string) $account['configuration_version']) ?></summary>
                <form class="auth-form" action="/ui/integrations/<?= $e((string) $account['id']) ?>" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= $e($account['update_csrf_token']) ?>"><input type="hidden" name="configuration_version" value="<?= $e((string) $account['configuration_version']) ?>">
                    <input type="hidden" name="provider" value="<?= $e($account['provider_code']) ?>"><input type="hidden" name="code" value="<?= $e($account['code']) ?>">
                    <div class="field"><label>Nome visualizzato</label><input name="display_name" value="<?= $e($account['display_name']) ?>" required maxlength="160"></div>
                    <div class="field"><label>Ambiente</label><select name="environment"><option value="sandbox"<?= $account['environment'] === 'sandbox' ? ' selected' : '' ?>>Sandbox</option><option value="production"<?= $account['environment'] === 'production' ? ' selected' : '' ?>>Produzione</option></select></div>
                    <div class="field"><label>Capacità</label><input name="capabilities" value="<?= $e(implode(', ', $account['capabilities'])) ?>"></div>
                    <div class="field"><label>Impostazioni non segrete (JSON)</label><textarea name="settings_json" rows="6"><?= $e(json_encode($account['settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></textarea></div>
                    <div class="field"><label>Descrizione</label><textarea name="description" rows="3" maxlength="1000"><?= $e($account['description'] ?? '') ?></textarea></div>
                    <button class="button button--secondary" type="submit">Salva nuova versione</button>
                </form>
                <?php if ($account['desired_status'] !== 'retired'): ?>
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
                    <span><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#integration"></use></svg> 0 account configurati</span>
                </div>
                <button class="button button--ghost button--wide" type="button" disabled>Configura</button>
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
