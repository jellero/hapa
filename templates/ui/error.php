<section class="error-state" aria-labelledby="error-title">
    <span class="error-state__code" aria-hidden="true">404</span>
    <p class="eyebrow"><?= $e($eyebrow) ?></p>
    <h1 id="error-title"><?= $e($title) ?></h1>
    <p><?= $e($description) ?></p>
    <div class="error-state__actions">
        <a class="button button--primary" href="/ui">
            <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#dashboard"></use></svg>
            Torna alla dashboard
        </a>
        <a class="button button--secondary" href="/ui/orders">Apri gli ordini</a>
    </div>
</section>
