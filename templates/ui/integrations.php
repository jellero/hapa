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
<?php if (($catalogSynchronized ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Feed prodotti Space sincronizzato</strong><span><?php if (($catalogPublished ?? 0) > 0): ?><?= $e((string) $catalogPublished) ?> variazioni prodotto acquisite da Space.<?php else: ?>Nessuna nuova variazione prodotto rilevata; il catalogo resta aggiornato.<?php endif; ?></span></div></output>
<?php endif; ?>
<?php if (($suppliersSynchronized ?? false) === true): ?>
    <output class="inline-notice inline-notice--info"><div><strong>Elenco fornitori Space sincronizzato</strong><span><?php if (($suppliersPublished ?? 0) > 0): ?><?= $e((string) $suppliersPublished) ?> variazioni fornitore acquisite da Space.<?php else: ?>Nessuna nuova variazione fornitore rilevata; l’anagrafica resta aggiornata.<?php endif; ?></span></div></output>
<?php endif; ?>

<?php if (($currentUser?->role ?? '') === 'administrator'): ?>
<section class="panel integration-create" id="new-integration-account" aria-labelledby="new-integration-title">
    <div class="panel__header integration-create__header">
        <div>
            <p class="eyebrow">Configurazione provider</p>
            <h2 id="new-integration-title">Collega un nuovo account</h2>
            <p class="integration-create__intro">Seleziona il servizio da collegare e completa i parametri richiesti. Per Space puoi configurare feed, pianificazione e token nello stesso modulo.</p>
        </div>
        <span class="status-badge status-badge--neutral">Configurazione iniziale</span>
    </div>
    <form class="integration-create__form" action="/ui/integrations" method="post">
        <input type="hidden" name="_csrf_token" value="<?= $e($createIntegrationCsrfToken ?? '') ?>">
        <div class="integration-create__grid">
            <div class="field">
                <label for="integration-provider">Provider</label>
                <select id="integration-provider" name="provider" required><?php foreach (array_keys($availableCapabilities) as $provider): ?><option value="<?= $e($provider) ?>"><?= $e(ucfirst($provider)) ?></option><?php endforeach; ?></select>
                <small>Il servizio esterno da collegare.</small>
            </div>
            <div class="field">
                <label for="integration-environment">Ambiente</label>
                <select id="integration-environment" name="environment"><option value="sandbox">Sandbox — prove</option><option value="production">Produzione — dati reali</option></select>
                <small>Usa Sandbox finché la configurazione non è validata.</small>
            </div>
            <div class="field">
                <label for="integration-code">Codice identificativo</label>
                <input id="integration-code" name="code" required maxlength="96" placeholder="es. sellrapido-primary">
                <small>Un codice stabile, senza spazi.</small>
            </div>
            <div class="field">
                <label for="integration-name">Nome account</label>
                <input id="integration-name" name="display_name" required maxlength="160" placeholder="es. SellRapido principale">
                <small>Il nome mostrato agli operatori.</small>
            </div>
            <div class="field integration-create__wide">
                <label for="integration-capabilities">Funzioni abilitate</label>
                <input id="integration-capabilities" name="capabilities" placeholder="Per Space: catalog.read">
                <small>Per sincronizzare il feed Space inserisci <strong>catalog.read</strong>.</small>
            </div>
            <div class="field integration-create__wide">
                <label for="integration-description">Note interne <span class="field__optional">facoltative</span></label>
                <textarea id="integration-description" name="description" rows="3" maxlength="1000" placeholder="Scopo dell’account, referente o altre informazioni utili"></textarea>
            </div>
        </div>
        <fieldset class="integration-create__advanced" data-space-feed-config hidden disabled>
            <legend>Sincronizzazione Space</legend>
            <p class="integration-create__intro">Prodotti e fornitori sono due account tecnici indipendenti: ciascuno conserva token, endpoint, cursore incrementale e frequenza propri.</p>
            <div class="integration-create__grid">
                <div class="field integration-create__wide">
                    <label for="space-account-kind">Dati da sincronizzare</label>
                    <select id="space-account-kind" name="space_account_kind">
                        <option value="catalog">Feed prodotti SPECE (/apih)</option>
                        <option value="suppliers">Elenco fornitori (/apie)</option>
                    </select>
                    <small>Per usare entrambi crea due account Space separati.</small>
                </div>
                <div class="field integration-create__wide">
                    <label for="space-bearer-token">Token Bearer dedicato</label>
                    <input id="space-bearer-token" name="space_bearer_token" type="password" required maxlength="8192" autocomplete="new-password" spellcheck="false" placeholder="Incolla il token fornito da Space">
                    <small>Campo write-only: dopo il salvataggio non sarà più leggibile.</small>
                </div>
                <div class="field integration-create__wide">
                    <label for="space-base-url">Server Space</label>
                    <input id="space-base-url" name="space_base_url" type="url" value="https://admin.space1999.com">
                    <small>Dominio base, senza token e senza parametri query.</small>
                </div>
                <div class="field" data-space-catalog-config>
                    <label for="space-incremental-path">Percorso feed</label>
                    <input id="space-incremental-path" name="space_catalog_incremental_path" value="/apih/index.php">
                </div>
                <div class="field" data-space-catalog-config>
                    <label for="space-incremental-action">Azione API</label>
                    <input id="space-incremental-action" name="space_catalog_incremental_action" value="spece">
                </div>
                <div class="field" data-space-catalog-config>
                    <label for="space-health-path">Percorso verifica</label>
                    <input id="space-health-path" name="space_health_path" value="/apih/index.php?action=help">
                </div>
                <div class="field" data-space-supplier-config hidden>
                    <label for="space-supplier-api-path">Percorso API fornitori</label>
                    <input id="space-supplier-api-path" name="space_supplier_api_path" value="/apie/index.php">
                </div>
                <div class="field">
                    <label for="space-frequency">Frequenza sincronizzazione</label>
                    <select id="space-frequency" name="space_poll_interval_seconds">
                        <option value="60">Ogni minuto</option>
                        <option value="300" selected>Ogni 5 minuti</option>
                        <option value="600">Ogni 10 minuti</option>
                        <option value="1800">Ogni 30 minuti</option>
                        <option value="3600">Ogni ora</option>
                    </select>
                </div>
                <div class="field" data-space-catalog-config>
                    <label for="space-page-size">Prodotti per pagina</label>
                    <input id="space-page-size" name="space_catalog_page_size" type="number" min="1" max="5000" value="1000">
                </div>
                <div class="field" data-space-catalog-config>
                    <label for="space-max-pages">Pagine massime per esecuzione</label>
                    <input id="space-max-pages" name="space_maximum_catalog_pages_per_run" type="number" min="1" max="1000" value="20">
                </div>
                <div class="field" data-space-supplier-config hidden>
                    <label for="space-supplier-page-size">Fornitori per pagina</label>
                    <input id="space-supplier-page-size" name="space_supplier_page_size" type="number" min="1" max="5000" value="1000">
                </div>
                <div class="field" data-space-supplier-config hidden>
                    <label for="space-supplier-max-pages">Pagine massime per esecuzione</label>
                    <input id="space-supplier-max-pages" name="space_maximum_supplier_pages_per_run" type="number" min="1" max="1000" value="25">
                </div>
                <div class="field">
                    <label for="space-response-limit">Dimensione massima risposta</label>
                    <input id="space-response-limit" name="space_maximum_response_bytes" type="number" min="1048576" max="16777216" value="8388608">
                    <small>8 MiB, adeguati alle pagine SPECE da 1.000 prodotti.</small>
                </div>
            </div>
            <?php
            $spaceMappingFields = [
                'idspace' => ['ID prodotto', 'id_album'],
                'idspacefull' => ['SKU completo', 'id_space_full'],
                'barcode' => ['EAN', 'ean'],
                'artista' => ['Artista', 'artista'],
                'titolo' => ['Titolo', 'titolo'],
                'format' => ['Formato', 'formato'],
                'label' => ['Etichetta', 'etichetta'],
                'categoria' => ['Categoria', 'categoria'],
                'famiglia' => ['Famiglia', 'famiglia'],
                'gruppo' => ['Gruppo', 'gruppo'],
                'price' => ['Costo HAPA', 'prezzo_vendita'],
                'stock' => ['Disponibilità immediata', 'onstock'],
                'delitime' => ['Tempo di consegna', 'giorni_consegna'],
                'precisione' => ['Precisione', 'precisione'],
                'uscita' => ['Data uscita', 'release_date'],
                'url' => ['Pagina prodotto Space', 'url_pagina'],
                'url_img' => ['Immagine prodotto', 'url_immagine'],
                'updated_at' => ['Ultimo aggiornamento', 'aggiornato_il'],
            ];
            ?>
            <details class="integration-create__advanced" data-space-catalog-config open>
                <summary>Mappatura campi Space</summary>
                <p class="integration-create__intro">A sinistra è indicato il dato HAPA; nel campo inserisci il nome esatto ricevuto dall’API Space. I valori sono già impostati per il feed concordato.</p>
                <div class="integration-create__grid">
                    <?php foreach ($spaceMappingFields as $target => [$label, $source]): ?>
                        <div class="field">
                            <label for="space-map-<?= $e($target) ?>"><?= $e($label) ?></label>
                            <input id="space-map-<?= $e($target) ?>" name="space_field_mapping[<?= $e($target) ?>]" value="<?= $e($source) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
            <ol class="gate-grid" data-space-catalog-config>
                <li><span>01</span><strong>Legge il feed</strong><small>Automation invia action=spece, after e limit</small></li>
                <li><span>02</span><strong>Mappa il record</strong><small>id_album, EAN, prezzo, stock e metadati diventano catalogo HAPA</small></li>
                <li><span>03</span><strong>Avanza il cursore</strong><small>next_after viene salvato soltanto dopo la pubblicazione del lotto</small></li>
                <li><span>04</span><strong>Ripete automaticamente</strong><small>A fine ciclo riparte per rilevare variazioni di prezzo e stock</small></li>
            </ol>
            <ol class="gate-grid" data-space-supplier-config hidden>
                <li><span>01</span><strong>Scarica l’anagrafica</strong><small>Prima esecuzione full su entity=fornitori</small></li>
                <li><span>02</span><strong>Avanza il cursore</strong><small>La lettura completa riprende dall’ultimo ID salvato</small></li>
                <li><span>03</span><strong>Passa all’incrementale</strong><small>Legge inserimenti, modifiche e cancellazioni</small></li>
                <li><span>04</span><strong>Conferma il batch</strong><small>Conferma solo dopo la pubblicazione verso HAPA</small></li>
            </ol>
        </fieldset>
        <details class="integration-create__advanced" data-generic-provider-settings>
            <summary>Configurazione avanzata</summary>
            <div class="field">
                <label for="integration-settings">Impostazioni tecniche in formato JSON</label>
                <textarea id="integration-settings" name="settings_json" rows="6" spellcheck="false">{}</textarea>
                <small>Non inserire password, token, API key o cookie: vengono gestiti separatamente e cifrati.</small>
            </div>
        </details>
        <div class="integration-create__footer">
            <div>
                <strong>Nessuna pubblicazione immediata</strong>
                <span>L’account viene creato inattivo e potrà essere verificato prima dell’attivazione.</span>
            </div>
            <button class="button button--primary" type="submit">Crea account</button>
        </div>
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
                    <?php if ($account['provider_code'] === 'space' && in_array('catalog.read', $account['capabilities'], true)): ?>
                        <?php $spaceSettings = is_array($account['settings']) ? $account['settings'] : []; ?>
                        <?php $savedMapping = is_array($spaceSettings['catalog_field_mapping'] ?? null) ? $spaceSettings['catalog_field_mapping'] : []; ?>
                        <fieldset class="integration-create__advanced">
                            <legend>Feed e mapping Space</legend>
                            <div class="integration-create__grid">
                                <div class="field integration-create__wide"><label for="<?= $e($accountFieldPrefix) ?>-space-url">Server Space</label><input id="<?= $e($accountFieldPrefix) ?>-space-url" name="space_base_url" type="url" value="<?= $e((string) ($spaceSettings['base_url'] ?? 'https://admin.space1999.com')) ?>"></div>
                                <div class="field"><label>Percorso feed</label><input name="space_catalog_incremental_path" value="<?= $e((string) ($spaceSettings['catalog_incremental_path'] ?? '/apih/index.php')) ?>"></div>
                                <div class="field"><label>Azione API</label><input name="space_catalog_incremental_action" value="<?= $e((string) ($spaceSettings['catalog_incremental_action'] ?? 'spece')) ?>"></div>
                                <div class="field"><label>Percorso verifica</label><input name="space_health_path" value="<?= $e((string) ($spaceSettings['health_path'] ?? '/apih/index.php?action=help')) ?>"></div>
                                <div class="field"><label>Frequenza in secondi</label><input name="space_poll_interval_seconds" type="number" min="60" max="86400" value="<?= $e((string) ($spaceSettings['poll_interval_seconds'] ?? 300)) ?>"></div>
                                <div class="field"><label>Prodotti per pagina</label><input name="space_catalog_page_size" type="number" min="1" max="5000" value="<?= $e((string) ($spaceSettings['catalog_page_size'] ?? 1000)) ?>"></div>
                                <div class="field"><label>Pagine per esecuzione</label><input name="space_maximum_catalog_pages_per_run" type="number" min="1" max="1000" value="<?= $e((string) ($spaceSettings['maximum_catalog_pages_per_run'] ?? 20)) ?>"></div>
                                <div class="field"><label>Dimensione massima risposta</label><input name="space_maximum_response_bytes" type="number" min="1048576" max="16777216" value="<?= $e((string) ($spaceSettings['maximum_response_bytes'] ?? 8388608)) ?>"></div>
                                <?php foreach ($spaceMappingFields as $target => [$label, $source]): ?>
                                    <div class="field">
                                        <label><?= $e($label) ?></label>
                                        <input name="space_field_mapping[<?= $e($target) ?>]" value="<?= $e((string) ($savedMapping[$target] ?? $source)) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php elseif ($account['provider_code'] === 'space' && in_array('suppliers.read', $account['capabilities'], true)): ?>
                        <?php $spaceSettings = is_array($account['settings']) ? $account['settings'] : []; ?>
                        <input type="hidden" name="space_account_kind" value="suppliers">
                        <fieldset class="integration-create__advanced">
                            <legend>API elenco fornitori Space</legend>
                            <p class="integration-create__intro">Queste impostazioni e il relativo token sono indipendenti dal feed prodotti.</p>
                            <div class="integration-create__grid">
                                <div class="field integration-create__wide"><label for="<?= $e($accountFieldPrefix) ?>-space-url">Server Space</label><input id="<?= $e($accountFieldPrefix) ?>-space-url" name="space_base_url" type="url" value="<?= $e((string) ($spaceSettings['base_url'] ?? 'https://admin.space1999.com')) ?>"></div>
                                <div class="field"><label>Percorso API fornitori</label><input name="space_supplier_api_path" value="<?= $e((string) ($spaceSettings['supplier_api_path'] ?? '/apie/index.php')) ?>"></div>
                                <div class="field"><label>Frequenza in secondi</label><input name="space_poll_interval_seconds" type="number" min="60" max="86400" value="<?= $e((string) ($spaceSettings['poll_interval_seconds'] ?? 3600)) ?>"></div>
                                <div class="field"><label>Fornitori per pagina</label><input name="space_supplier_page_size" type="number" min="1" max="5000" value="<?= $e((string) ($spaceSettings['supplier_page_size'] ?? 1000)) ?>"></div>
                                <div class="field"><label>Pagine per esecuzione</label><input name="space_maximum_supplier_pages_per_run" type="number" min="1" max="1000" value="<?= $e((string) ($spaceSettings['maximum_supplier_pages_per_run'] ?? 25)) ?>"></div>
                                <div class="field"><label>Dimensione massima risposta</label><input name="space_maximum_response_bytes" type="number" min="1048576" max="16777216" value="<?= $e((string) ($spaceSettings['maximum_response_bytes'] ?? 8388608)) ?>"></div>
                            </div>
                        </fieldset>
                    <?php endif; ?>
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
                    <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/catalog/sync" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($account['catalog_sync_csrf_token']) ?>"><button class="button button--primary" type="submit">Sincronizza feed prodotti Space</button></form>
                    <?php endif; ?>
                    <?php if ($account['provider_code'] === 'space' && in_array('suppliers.read', $account['capabilities'], true) && in_array($account['desired_status'], ['pilot', 'active'], true) && $account['connection_test_status'] === 'passed' && $account['automation_configuration_version'] === $account['configuration_version']): ?>
                    <form action="/ui/integrations/<?= $e((string) $account['id']) ?>/suppliers/sync" method="post"><input type="hidden" name="_csrf_token" value="<?= $e($account['supplier_sync_csrf_token']) ?>"><button class="button button--primary" type="submit">Scarica ora elenco fornitori</button></form>
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
