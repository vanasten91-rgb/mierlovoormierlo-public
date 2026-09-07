'use strict';

(() => {
  const root = document.querySelector('[data-mvm-hub]');
  if (!root) return;

  root.setAttribute('data-js', 'true');

  const navToggle = root.querySelector('[data-mvm-hub-nav-toggle]');
  const nav = root.querySelector('#mvm-hub-workspaces');
  const themeToggle = root.querySelector('[data-mvm-hub-theme-toggle]');
  const THEME_KEY = 'mvm-hub-theme';

  const setNavState = (open) => {
    if (!navToggle || !nav) return;
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    root.toggleAttribute('data-nav-open', open);
  };

  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      setNavState(navToggle.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && navToggle.getAttribute('aria-expanded') === 'true') {
        setNavState(false);
        navToggle.focus();
      }
    });
  }

  const preferredTheme = () => {
    try {
      const stored = window.localStorage.getItem(THEME_KEY);
      if (stored === 'light' || stored === 'dark') return stored;
    } catch (_) {
      // Preference storage is optional; never block the Hub if unavailable.
    }

    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  };

  const applyTheme = (theme) => {
    const normalized = theme === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-theme', normalized);
    if (themeToggle) themeToggle.setAttribute('aria-pressed', normalized === 'dark' ? 'true' : 'false');
  };

  applyTheme(preferredTheme());

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      try {
        window.localStorage.setItem(THEME_KEY, next);
      } catch (_) {
        // Preference persistence is non-essential.
      }
    });
  }
})();
