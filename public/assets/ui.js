(() => {
  'use strict';

  const body = document.body;
  const openButton = document.querySelector('[data-nav-open]');
  const closeButtons = document.querySelectorAll('[data-nav-close]');

  const setNavigation = (open) => {
    body.classList.toggle('nav-is-open', open);
    openButton?.setAttribute('aria-expanded', String(open));

    if (open) {
      document.querySelector('[data-nav-close]')?.focus();
    } else {
      openButton?.focus();
    }
  };

  openButton?.addEventListener('click', () => setNavigation(true));
  closeButtons.forEach((button) => button.addEventListener('click', () => setNavigation(false)));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && body.classList.contains('nav-is-open')) {
      setNavigation(false);
    }
  });

  const banner = document.querySelector('[data-preview-banner]');
  document.querySelector('[data-banner-dismiss]')?.addEventListener('click', () => {
    banner?.setAttribute('hidden', 'hidden');
  });

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const field = button.closest('.input-shell')?.querySelector('[data-password-input]');
      if (!(field instanceof HTMLInputElement)) return;

      const reveal = field.type === 'password';
      field.type = reveal ? 'text' : 'password';
      button.setAttribute('aria-label', reveal ? 'Nascondi password' : 'Mostra password');
    });
  });
})();
