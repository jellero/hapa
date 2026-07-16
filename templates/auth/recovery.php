<a class="back-link" href="/login">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#arrow-left"></use></svg>
    Torna all’accesso
</a>

<div class="auth-heading auth-heading--recovery">
    <span class="auth-heading__icon" aria-hidden="true">
        <svg class="icon"><use href="/assets/icons.svg#key"></use></svg>
    </span>
    <p class="eyebrow">Sicurezza account</p>
    <h2>Recupera l’accesso</h2>
    <p>Quando il modulo utenti sarà attivo, riceverai istruzioni monouso senza rivelare l’esistenza dell’account.</p>
</div>

<form class="auth-form" aria-label="Recupero accesso" novalidate>
    <fieldset disabled>
        <div class="field">
            <label for="recovery-email">Email di lavoro</label>
            <div class="input-shell">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#mail"></use></svg>
                <input id="recovery-email" name="email" type="email" autocomplete="email" placeholder="nome@azienda.it">
            </div>
            <p class="field__hint">La risposta sarà identica per account esistenti e non esistenti.</p>
        </div>

        <button class="button button--primary button--wide" type="submit">Invia istruzioni</button>
    </fieldset>
</form>

<div class="inline-notice" role="note">
    <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#shield"></use></svg>
    <div>
        <strong>Recupero protetto</strong>
        <span>I token saranno monouso, a scadenza breve e memorizzati solo come hash.</span>
    </div>
</div>
