(() => {
  'use strict';

  const body = document.body;
  const openButton = document.querySelector('[data-nav-open]');
  const sidebar = document.querySelector('[data-sidebar]');
  const closeButtons = document.querySelectorAll('[data-nav-close]');
  const mobileNavigation = window.matchMedia('(max-width: 1024px)');

  const synchronizeNavigation = () => {
    if (!(sidebar instanceof HTMLElement) || !(openButton instanceof HTMLElement)) return;

    if (!mobileNavigation.matches) {
      body.classList.remove('nav-is-open');
      openButton.setAttribute('aria-expanded', 'false');
      sidebar.removeAttribute('aria-hidden');
      sidebar.removeAttribute('inert');
      return;
    }

    const open = body.classList.contains('nav-is-open');
    openButton.setAttribute('aria-expanded', String(open));
    sidebar.toggleAttribute('inert', !open);
    sidebar.setAttribute('aria-hidden', String(!open));
  };

  const setNavigation = (open, restoreFocus = true) => {
    if (!mobileNavigation.matches || !(sidebar instanceof HTMLElement)) return;

    body.classList.toggle('nav-is-open', open);

    if (open) {
      synchronizeNavigation();
      sidebar.querySelector('[data-nav-close]')?.focus();
    } else if (restoreFocus) {
      openButton?.focus();
      synchronizeNavigation();
    } else {
      synchronizeNavigation();
    }
  };

  openButton?.addEventListener('click', () => setNavigation(true));
  closeButtons.forEach((button) => button.addEventListener('click', () => setNavigation(false)));
  mobileNavigation.addEventListener('change', synchronizeNavigation);
  synchronizeNavigation();

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && body.classList.contains('nav-is-open')) {
      setNavigation(false);
    }

    if (
      event.key !== 'Tab'
      || !body.classList.contains('nav-is-open')
      || !(sidebar instanceof HTMLElement)
    ) return;

    const focusable = [...sidebar.querySelectorAll(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), '
      + 'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    )].filter((element) => element instanceof HTMLElement && !element.hasAttribute('inert'));

    const first = focusable.at(0);
    const last = focusable.at(-1);
    if (!(first instanceof HTMLElement) || !(last instanceof HTMLElement)) return;

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  const banner = document.querySelector('[data-preview-banner]');
  document.querySelector('[data-banner-dismiss]')?.addEventListener('click', () => {
    banner?.setAttribute('hidden', 'hidden');
  });

  const livenessStatus = document.querySelector('[data-liveness-status]');
  const livenessLabel = livenessStatus?.querySelector('[data-liveness-label]');
  const updateLiveness = async () => {
    if (!(livenessStatus instanceof HTMLOutputElement) || !(livenessLabel instanceof HTMLElement)) return;

    let healthy = false;
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 5000);
    try {
      const response = await fetch('/health/live', {
        cache: 'no-store',
        credentials: 'same-origin',
        signal: controller.signal,
      });
      const payload = response.ok ? await response.json() : null;
      healthy = payload?.status === 'ok';
    } catch {
      healthy = false;
    } finally {
      window.clearTimeout(timeout);
    }

    livenessStatus.classList.toggle('service-health--checking', false);
    livenessStatus.classList.toggle('service-health--ok', healthy);
    livenessStatus.classList.toggle('service-health--ko', !healthy);
    livenessLabel.textContent = healthy ? 'Sistema OK' : 'Sistema KO';
  };
  if (livenessStatus instanceof HTMLOutputElement) {
    updateLiveness();
    window.setInterval(updateLiveness, 30000);
  }

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const field = button.closest('.input-shell')?.querySelector('[data-password-input]');
      if (!(field instanceof HTMLInputElement)) return;

      const reveal = field.type === 'password';
      field.type = reveal ? 'text' : 'password';
      button.setAttribute('aria-label', reveal ? 'Nascondi password' : 'Mostra password');
    });
  });

  const providerSelect = document.querySelector('#integration-provider');
  const spaceFeedConfiguration = document.querySelector('[data-space-feed-config]');
  const genericProviderSettings = document.querySelector('[data-generic-provider-settings]');
  const genericSettingsInput = document.querySelector('#integration-settings');
  const capabilityInput = document.querySelector('#integration-capabilities');
  const spaceAccountKind = document.querySelector('#space-account-kind');
  const spaceFrequency = document.querySelector('#space-frequency');
  const synchronizeSpaceAccountKind = () => {
    if (!(spaceAccountKind instanceof HTMLSelectElement)) return;

    const suppliers = spaceAccountKind.value === 'suppliers';
    document.querySelectorAll('[data-space-catalog-config]').forEach((element) => {
      element.hidden = suppliers;
      element.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = suppliers;
      });
    });
    document.querySelectorAll('[data-space-supplier-config]').forEach((element) => {
      element.hidden = !suppliers;
      element.querySelectorAll('input, select, textarea').forEach((field) => {
        field.disabled = !suppliers;
      });
    });
    if (capabilityInput instanceof HTMLInputElement) {
      capabilityInput.value = suppliers ? 'suppliers.read' : 'catalog.read';
      capabilityInput.readOnly = true;
    }
    if (spaceFrequency instanceof HTMLSelectElement) {
      spaceFrequency.value = suppliers ? '3600' : '300';
    }
  };
  const synchronizeProviderForm = () => {
    if (!(providerSelect instanceof HTMLSelectElement)
      || !(spaceFeedConfiguration instanceof HTMLFieldSetElement)) return;

    const isSpace = providerSelect.value === 'space';
    spaceFeedConfiguration.hidden = !isSpace;
    spaceFeedConfiguration.disabled = !isSpace;
    if (genericProviderSettings instanceof HTMLDetailsElement) {
      genericProviderSettings.hidden = isSpace;
    }
    if (genericSettingsInput instanceof HTMLTextAreaElement) {
      genericSettingsInput.disabled = isSpace;
    }
    if (isSpace && capabilityInput instanceof HTMLInputElement && capabilityInput.value.trim() === '') {
      capabilityInput.value = 'catalog.read';
    }
    if (!isSpace && capabilityInput instanceof HTMLInputElement) {
      capabilityInput.readOnly = false;
    }
    if (isSpace) synchronizeSpaceAccountKind();
  };
  providerSelect?.addEventListener('change', synchronizeProviderForm);
  spaceAccountKind?.addEventListener('change', synchronizeSpaceAccountKind);
  synchronizeProviderForm();

  const imagePreview = document.querySelector('[data-image-preview]');
  const imagePreviewImage = imagePreview?.querySelector('[data-image-preview-image]');
  const imagePreviewTitle = imagePreview?.querySelector('[data-image-preview-title]');
  let imagePreviewTrigger = null;

  document.querySelectorAll('[data-image-preview-open]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!(imagePreview instanceof HTMLDialogElement)
        || !(imagePreviewImage instanceof HTMLImageElement)
        || !(imagePreviewTitle instanceof HTMLElement)) return;

      const source = button.getAttribute('data-image-src');
      if (!source) return;

      const title = button.getAttribute('data-image-title') || 'Copertina prodotto';
      imagePreviewTrigger = button;
      imagePreviewImage.src = source;
      imagePreviewImage.alt = `Copertina di ${title}`;
      imagePreviewTitle.textContent = title;
      imagePreview.showModal();
    });
  });

  const closeImagePreview = () => {
    if (!(imagePreview instanceof HTMLDialogElement)) return;
    imagePreview.close();
  };

  imagePreview?.querySelector('[data-image-preview-close]')?.addEventListener('click', closeImagePreview);
  imagePreview?.addEventListener('click', (event) => {
    if (event.target === imagePreview) closeImagePreview();
  });
  imagePreview?.addEventListener('close', () => {
    if (imagePreviewImage instanceof HTMLImageElement) {
      imagePreviewImage.removeAttribute('src');
    }
    if (imagePreviewTrigger instanceof HTMLElement) imagePreviewTrigger.focus();
    imagePreviewTrigger = null;
  });

  document.querySelectorAll('[data-pricing-form]').forEach((form) => {
    const type = form.querySelector('[data-pricing-type]');
    const percentage = form.querySelector('[data-pricing-percentage]');
    const amount = form.querySelector('[data-pricing-amount]');
    if (!(type instanceof HTMLSelectElement)
      || !(percentage instanceof HTMLElement)
      || !(amount instanceof HTMLElement)) return;

    const synchronizePricingFields = () => {
      const usesPercentage = type.value === 'percentage';
      percentage.hidden = !usesPercentage;
      amount.hidden = usesPercentage;
      percentage.querySelectorAll('input').forEach((input) => {
        input.disabled = !usesPercentage;
        input.required = usesPercentage;
      });
      amount.querySelectorAll('input').forEach((input) => {
        input.disabled = usesPercentage;
        input.required = !usesPercentage;
      });
    };
    type.addEventListener('change', synchronizePricingFields);
    synchronizePricingFields();
  });
})();
