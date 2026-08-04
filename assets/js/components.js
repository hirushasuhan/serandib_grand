/**
 * Modal dialogs, tabs, toast auto-dismiss & UI interaction components.
 */
document.addEventListener('DOMContentLoaded', () => {
  // Modal Handlers
  document.querySelectorAll('[data-modal-target]').forEach(trigger => {
    trigger.addEventListener('click', () => {
      const targetId = trigger.getAttribute('data-modal-target');
      const modal = document.getElementById(targetId);
      if (modal) {
        modal.setAttribute('data-open', 'true');
      }
    });
  });

  document.querySelectorAll('[data-modal-close], .modal__backdrop').forEach(closeBtn => {
    closeBtn.addEventListener('click', () => {
      const modal = closeBtn.closest('.modal');
      if (modal) {
        modal.setAttribute('data-open', 'false');
      }
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal[data-open="true"]').forEach(modal => {
        modal.setAttribute('data-open', 'false');
      });
    }
  });

  // Tab Handlers
  document.querySelectorAll('.tab-link').forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      const parent = tab.closest('.tabs-wrapper');
      if (!parent) return;

      parent.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
      parent.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

      tab.classList.add('active');
      const target = parent.querySelector(tab.getAttribute('href'));
      if (target) target.classList.add('active');
    });
  });

  // Toast Auto Dismiss
  document.querySelectorAll('.toast').forEach(toast => {
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  });

  /* ═══════════════════════════════════════════════════════════
     DELEGATED HANDLERS replacing inline HTML attributes.

     The Content-Security-Policy sent from bootstrap.php uses
     `script-src 'self' 'nonce-…'` with no 'unsafe-inline', which blocks
     inline `onclick=` / `onsubmit=` attributes outright. Rather than
     weakening the policy, those attributes were replaced with data-*
     hooks and are handled here. This also matches the project rule of
     "no inline event handlers".
     ═══════════════════════════════════════════════════════════ */

  /* 1. Destructive-action confirmation.
        Markup: <form data-confirm="Delete room 305?"> */
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form || !form.hasAttribute || !form.hasAttribute('data-confirm')) return;

    // Ask once; a second pass after the user agreed must not re-prompt.
    if (form.dataset.confirmed === 'true') return;

    if (!window.confirm(form.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopImmediatePropagation();   // keeps main.js from locking the button
      return;
    }
    form.dataset.confirmed = 'true';
  }, true);   // capture phase, so it runs before the submit-guard in main.js

  /* 2. Print buttons.
        Markup: <button type="button" data-print> */
  document.querySelectorAll('[data-print]').forEach(btn => {
    btn.addEventListener('click', () => window.print());
  });

  /* 3. Selects that reload the page with a new query parameter.
        Markup: <select data-reload-param="room_type_id" data-reload-url="..."> */
  document.querySelectorAll('[data-reload-param]').forEach(select => {
    select.addEventListener('change', () => {
      const base  = select.getAttribute('data-reload-url') || window.location.pathname;
      const param = select.getAttribute('data-reload-param');
      const form  = select.closest('form');

      // Carry the current dates across so the user does not lose their search.
      const params = new URLSearchParams();
      params.set(param, select.value);

      if (form) {
        ['check_in', 'check_out', 'adults', 'children'].forEach(name => {
          const field = form.querySelector('[name="' + name + '"]');
          if (field && field.value) params.set(name, field.value);
        });
      }

      window.location.href = base + '?' + params.toString();
    });
  });
});
