<div class="auth-heading">
    <span class="auth-heading__icon" aria-hidden="true">
        <svg class="icon"><use href="/assets/icons.svg#lock"></use></svg>
    </span>
    <p class="eyebrow">Centro operativo</p>
    <h2>Accedi a HAPA</h2>
    <p>Usa le credenziali del tuo account operativo.</p>
</div>

<?php if (($error ?? '') !== ''): ?>
    <div class="inline-notice inline-notice--warning" role="alert">
        <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#warning"></use></svg>
        <div><strong>Accesso non riuscito</strong><span><?= $e($error) ?></span></div>
    </div>
<?php endif; ?>

<form class="auth-form" action="/login" method="post" aria-label="Accesso">
    <input type="hidden" name="_csrf_token" value="<?= $e($csrfToken ?? '') ?>">
    <input type="hidden" name="next" value="<?= $e($next ?? '/ui') ?>">
    <fieldset>
        <div class="field">
            <label for="email">Email di lavoro</label>
            <div class="input-shell">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#mail"></use></svg>
                <input id="email" name="email" type="email" autocomplete="username" placeholder="nome@azienda.it" value="<?= $e($email ?? '') ?>" required maxlength="254">
            </div>
        </div>

        <div class="field">
            <div class="field__label-row">
                <label for="password">Password</label>
                <a href="/password/recovery">Password dimenticata?</a>
            </div>
            <div class="input-shell">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#key"></use></svg>
                <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Inserisci la password" data-password-input required>
                <button class="input-action" type="button" aria-label="Mostra password" data-password-toggle>
                    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#eye"></use></svg>
                </button>
            </div>
        </div>

        <label class="check-field">
            <input type="checkbox" name="remember">
            <span>Ricorda questo dispositivo</span>
        </label>

        <button class="button button--primary button--wide" type="submit">
            Accedi
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-right"></use></svg>
        </button>
    </fieldset>
</form>

<div class="auth-support">
    <span>Per problemi di accesso contatta l’amministratore di sistema.</span>
</div>
