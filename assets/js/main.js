/**
 * main.js — application bootstrapper: preloader, navigation feedback,
 * reveal fallback, form submit guarding and the mobile nav.
 *
 * Runs on EVERY page (public and dashboard).
 */
document.addEventListener('DOMContentLoaded', function () {

  /* ── 1. PRELOADER ─────────────────────────────────────────────
     By the time DOMContentLoaded fires, PHP has already sent a complete page —
     there is nothing left to wait for. Hide it on the very next animation
     frame rather than after an artificial delay; the previous 200ms timeout
     was itself part of the felt "lag" on every navigation. `display: none`
     follows shortly after so the element is fully out of the layout, not just
     invisible. */
  var preloader = document.getElementById('site-preloader');
  if (preloader) {
    window.requestAnimationFrame(function () {
      preloader.classList.add('fade-out');
    });
    window.setTimeout(function () { preloader.style.display = 'none'; }, 300);
  }

  /* ── 2. TOP PROGRESS BAR ──────────────────────────────────── */
  var progressBar = document.getElementById('top-progress-bar');
  if (!progressBar) {
    progressBar = document.createElement('div');
    progressBar.id = 'top-progress-bar';
    document.body.appendChild(progressBar);
  }

  function resetChrome() {
    progressBar.style.width = '0';
    progressBar.style.opacity = '0';
    document.body.classList.remove('page-exiting');
  }

  /* ── 3. NAVIGATION FEEDBACK ───────────────────────────────────
     One delegated listener instead of binding to every <a> on the page.

     FIX: this used to also dim the ENTIRE page to invisible the instant a link
     was clicked (via the `page-exiting` class), 250ms BEFORE the browser even
     requested the next page. On a server-rendered app the real navigation
     starts immediately — that dimming was pure added delay stacked on top of
     the preloader on the page that followed. The progress bar alone gives the
     "something is happening" cue without hiding content the user can still
     read while the next page loads. */
  document.addEventListener('click', function (event) {
    var link = event.target.closest ? event.target.closest('a[href]') : null;
    if (!link) return;

    var href = link.getAttribute('href');

    // Skip anything that is not a same-tab, same-document navigation.
    if (!href
      || href.charAt(0) === '#'
      || href.indexOf('javascript:') === 0
      || href.indexOf('mailto:') === 0
      || href.indexOf('tel:') === 0
      || link.hasAttribute('download')
      || link.getAttribute('target') === '_blank'
      || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
      || event.button !== 0) {
      return;
    }

    // Off-site links: the page is leaving anyway, no local feedback needed.
    if (link.host && link.host !== window.location.host) return;

    progressBar.style.opacity = '1';
    progressBar.style.width = '70%';
    window.setTimeout(function () { progressBar.style.width = '100%'; }, 150);

    // Safety net only: if navigation never actually happens (cancelled
    // download, blocked popup, in-page handler), clear the bar rather than
    // leaving it stuck mid-animation.
    window.setTimeout(resetChrome, 4000);
  });

  // Restoring from the back/forward cache re-runs neither DOMContentLoaded nor
  // load, so the faded state has to be cleared explicitly.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) resetChrome();
  });

  /* ── 4. SCROLL REVEAL (dashboard pages) ───────────────────────
     Public pages use the richer [data-reveal] system in public3d.js; this
     covers the rest. Cards inside a modal are excluded — they are display:none
     when observed and would otherwise never become visible. */
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var revealTargets = Array.prototype.slice.call(
    document.querySelectorAll('.card, .page-title, .section-title')
  ).filter(function (el) {
    return !el.hasAttribute('data-reveal')
        && !el.closest('[data-reveal]')
        && !el.closest('.modal');
  });

  /*
   * FIX: this JS was adding the class "revealed" on intersection, but the
   * matching CSS rule in base.css is `.reveal.is-revealed { opacity: 1; }` —
   * the class names never matched. Every element this selector touches
   * (.card, .page-title, .section-title) got `.reveal` applied (opacity: 0)
   * and then NEVER un-hidden, on every dashboard page for every role. That
   * is exactly the "no data after login" report: the KPI cards, the arrivals/
   * departures tables, even the page's own <h1> were all rendering correctly
   * server-side and sitting in the DOM — just permanently invisible.
   */
  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealTargets.forEach(function (el) { el.classList.add('is-revealed'); });
  } else {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    revealTargets.forEach(function (el) {
      el.classList.add('reveal');
      observer.observe(el);
    });

    // Failsafe: never leave content permanently invisible.
    window.setTimeout(function () {
      revealTargets.forEach(function (el) { el.classList.add('is-revealed'); });
    }, 3000);
  }

  /* ── 5. FORM SUBMIT GUARD ─────────────────────────────────────
     The old version swapped the button label but left it ENABLED, so a
     double-click still sent two POSTs — two bookings, two payments. It also
     fired on GET filter forms and stuck on "Processing…" whenever client-side
     validation blocked the submit. */
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {

      // Let HTML5 / custom validation win; do not touch the button.
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
      if (event.defaultPrevented) return;

      // Search and filter forms are cheap and idempotent — leave them alone.
      var method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'post') return;

      var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
      if (!submitBtn || submitBtn.disabled) return;

      // Already submitted once? Block the second attempt outright.
      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }
      form.dataset.submitting = 'true';

      submitBtn.dataset.originalHtml = submitBtn.innerHTML;
      submitBtn.textContent = 'Processing…';
      submitBtn.setAttribute('aria-busy', 'true');
      submitBtn.style.opacity = '0.75';
      submitBtn.style.cursor = 'progress';

      /* A disabled control is not serialised with the form, which would drop a
         named submit button's value. Disable on the next tick, once the browser
         has already collected the form data. */
      window.setTimeout(function () { submitBtn.disabled = true; }, 0);

      // Release the lock if the navigation never happens.
      window.setTimeout(function () {
        form.dataset.submitting = 'false';
        submitBtn.disabled = false;
        submitBtn.removeAttribute('aria-busy');
        submitBtn.style.opacity = '';
        submitBtn.style.cursor = '';
        if (submitBtn.dataset.originalHtml) {
          submitBtn.innerHTML = submitBtn.dataset.originalHtml;
        }
      }, 10000);
    });
  });

  /* ── 6. MOBILE SIDEBAR DRAWER ─────────────────────────────────
     The sidebar is translateX(-100%) below 1024px. Until now nothing in the
     markup carried [data-nav-toggle], so on a phone the dashboard menu was
     rendered but permanently off-screen and unreachable. includes/nav.php now
     renders the button; this wires it up. */
  var navToggle   = document.querySelector('[data-nav-toggle]');
  var drawer      = document.getElementById('app-sidebar');
  var backdrop    = document.querySelector('[data-sidebar-backdrop]');
  var drawerClose = document.querySelector('[data-nav-close]');

  if (navToggle && drawer) {

    var setDrawer = function (open) {
      drawer.setAttribute('data-open', String(open));
      navToggle.setAttribute('aria-expanded', String(open));

      if (backdrop) {
        if (open) {
          backdrop.hidden = false;
          // Next frame so the CSS transition has a state to animate from.
          window.requestAnimationFrame(function () {
            backdrop.setAttribute('data-open', 'true');
          });
        } else {
          backdrop.removeAttribute('data-open');
          window.setTimeout(function () { backdrop.hidden = true; }, 250);
        }
      }

      // Stop the page behind the drawer from scrolling while it is open.
      document.body.style.overflow = open ? 'hidden' : '';
    };

    navToggle.addEventListener('click', function (event) {
      event.stopPropagation();
      setDrawer(drawer.getAttribute('data-open') !== 'true');
    });

    if (drawerClose) {
      drawerClose.addEventListener('click', function () {
        setDrawer(false);
        navToggle.focus();
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', function () { setDrawer(false); });
    }

    // Escape closes it — expected of any overlay.
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && drawer.getAttribute('data-open') === 'true') {
        setDrawer(false);
        navToggle.focus();
      }
    });

    // Following a link inside the drawer should not leave it open behind the
    // next page (it would also leave body overflow locked on bfcache restore).
    drawer.addEventListener('click', function (event) {
      if (event.target.closest && event.target.closest('a[href]')) setDrawer(false);
    });

    // Returning to desktop width must always clear the mobile-only state.
    window.addEventListener('resize', function () {
      if (window.innerWidth > 1024 && drawer.getAttribute('data-open') === 'true') {
        setDrawer(false);
      }
    });
  }

  /* ── 7. PUBLIC NAVBAR MENU TOGGLE ─────────────────────────────
     Public pages have no sidebar; their nav links collapse behind their own
     button instead. Previously the whole link row stayed as a flex line and
     overflowed the viewport on any phone. */
  var menuToggle = document.querySelector('[data-menu-toggle]');
  var navMenu    = document.getElementById('navbar-menu');

  if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', function (event) {
      event.stopPropagation();
      var open = navMenu.getAttribute('data-open') !== 'true';
      navMenu.setAttribute('data-open', String(open));
      menuToggle.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('click', function (event) {
      if (navMenu.getAttribute('data-open') !== 'true') return;
      if (navMenu.contains(event.target) || menuToggle.contains(event.target)) return;
      navMenu.setAttribute('data-open', 'false');
      menuToggle.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && navMenu.getAttribute('data-open') === 'true') {
        navMenu.setAttribute('data-open', 'false');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.focus();
      }
    });
  }

});
