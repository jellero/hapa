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
