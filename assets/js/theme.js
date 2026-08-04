/**
 * Dual Light / Dark Theme Switcher with localStorage & OS preference support.
 */
document.addEventListener('DOMContentLoaded', () => {
  const root = document.documentElement;
  const toggleBtn = document.querySelector('[data-theme-toggle]');

  const syncTheme = (theme) => {
    root.setAttribute('data-theme', theme);
    if (toggleBtn) {
      toggleBtn.setAttribute('aria-pressed', String(theme === 'dark'));
      toggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
      /* The sun/moon icon swap is pure CSS now — see .navbar__theme .icon--sun
         / .icon--moon in components.css, keyed off the [data-theme] attribute
         set on the line above. This used to look up a `.theme-icon` element
         that the markup never actually had, so the icon never changed. */
    }
  };

  const savedTheme = localStorage.getItem('hrs-theme');
  const osTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  const initialTheme = savedTheme || osTheme;

  syncTheme(initialTheme);

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const current = root.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      localStorage.setItem('hrs-theme', next);
      syncTheme(next);
    });
  }
});
