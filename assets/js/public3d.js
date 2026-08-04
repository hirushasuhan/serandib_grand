/**
 * public3d.js — 3D interaction layer for the public pages.
 *
 * Design rules followed throughout this file:
 *
 *  1. PROGRESSIVE ENHANCEMENT. Every effect is additive. If this file fails to
 *     load, or JS is disabled, the pages remain fully readable and bookable —
 *     `.no-js [data-reveal]` in public.css guarantees nothing stays invisible.
 *
 *  2. RESPECT prefers-reduced-motion. Checked once up front; when set, the
 *     tilt/parallax/counter effects are never wired up at all.
 *
 *  3. NEVER read layout inside a scroll or mousemove handler. Positions are
 *     cached and all writes are batched into requestAnimationFrame, so we don't
 *     cause layout thrashing.
 *
 *  4. NO innerHTML with data that could come from the database — textContent only.
 */
(function () {
  'use strict';

  /* The stylesheet uses .no-js as its failsafe; remove it now that JS is alive. */
  document.documentElement.classList.remove('no-js');

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer  = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ═══════════════════════════════════════════════════════════
     1. HERO PARALLAX
     Layers move at fractional scroll rates to create depth.
     ═══════════════════════════════════════════════════════════ */
  function initParallax() {
    if (reduceMotion) return;

    var layers = Array.prototype.slice.call(
      document.querySelectorAll('[data-parallax-speed]')
    );
    if (!layers.length) return;

    var ticking = false;

    function apply() {
      var y = window.pageYOffset;

      layers.forEach(function (layer) {
        var speed = parseFloat(layer.getAttribute('data-parallax-speed')) || 0.2;
        // Write to a custom property rather than `transform` directly, so the
        // stylesheet keeps ownership of the full transform (including scale).
        layer.style.setProperty('--parallax', (y * speed).toFixed(1) + 'px');
      });

      ticking = false;
    }

    window.addEventListener('scroll', function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(apply);
      }
    }, { passive: true });

    apply();
  }

  /* ═══════════════════════════════════════════════════════════
     1b. HERO VIDEO
     Autoplay is unreliable and sometimes simply unwanted, so the
     poster image must always be a valid final state.
     ═══════════════════════════════════════════════════════════ */
  function initHeroVideo() {
    // Handles every decorative background video on the site: the home hero and
    // the login page. Both carry data-src-* instead of a <source src>.
    var videos = Array.prototype.slice.call(
      document.querySelectorAll('#hero-drone-video, [data-bg-video]')
    );
    videos.forEach(setupBackgroundVideo);
  }

  function setupBackgroundVideo(video) {
    if (!video) return;

    var mp4  = video.getAttribute('data-src-mp4');
    var webm = video.getAttribute('data-src-webm');
    if (!mp4 && !webm) return;

    /* ── Reasons to never load the video at all ──
       In every one of these cases the poster image is the final state, which
       is why it is set as a CSS background on the wrapper as well as on the
       `poster` attribute. The hero always looks complete. */

    // 1. The user asked for reduced motion.
    if (reduceMotion) return;

    // 2. Metered or slow connection, or explicit data-saver.
    //    Downloading a decorative megabyte on a 2G phone plan is hostile.
    var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (conn) {
      if (conn.saveData) return;
      if (/(^|-)2g$/.test(conn.effectiveType || '')) return;
    }

    // 3. Small screens: a background video is invisible behind the search
    //    panel on a phone, so it is pure wasted bandwidth and battery.
    if (window.innerWidth < 768) return;

    /**
     * Attach the sources. This is the moment the download actually starts, and
     * it happens only after the page has finished loading, so it can never
     * delay first paint or make the tab appear frozen.
     */
    function attach() {
      // If the file 404s or the codec is unsupported, hide the <video> element
      // so the wrapper's background image shows through rather than a black box.
      video.addEventListener('error', function () {
        video.style.display = 'none';
      }, true);

      if (webm) {
        var s1 = document.createElement('source');
        s1.type = 'video/webm';
        s1.src = webm;
        video.appendChild(s1);
      }
      if (mp4) {
        var s2 = document.createElement('source');
        s2.type = 'video/mp4';
        s2.src = mp4;
        video.appendChild(s2);
      }

      video.preload = 'auto';
      video.load();

      // Only reveal the video once there is a real frame to show, otherwise it
      // flashes black over the poster.
      video.addEventListener('playing', function () {
        video.classList.add('is-ready');
      }, { once: true });

      // Autoplay may still be refused. Muted autoplay is normally allowed, but
      // if it is blocked we retry once on the first user gesture and then stop.
      var attempt = video.play();
      if (attempt && typeof attempt.catch === 'function') {
        attempt.catch(function () {
          var once = function () {
            video.play().catch(function () {});
          };
          document.addEventListener('click', once, { once: true });
          document.addEventListener('keydown', once, { once: true });
        });
      }

      // Pause while scrolled away — decoding an unseen video drains battery.
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              video.play().catch(function () {});
            } else {
              video.pause();
            }
          });
        }, { threshold: 0.05 }).observe(video);
      }
    }

    // Wait for full page load, then one idle slice, so the video never competes
    // with the CSS, fonts and room images that the user actually needs.
    function schedule() {
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(attach, { timeout: 2500 });
      } else {
        window.setTimeout(attach, 900);
      }
    }

    if (document.readyState === 'complete') {
      schedule();
    } else {
      window.addEventListener('load', schedule, { once: true });
    }
  }

  /* ═══════════════════════════════════════════════════════════
     2. TILT CARDS
     Sets --rx/--ry (rotation) and --mx/--my (glare centre).
     Pointer-only: a tilt you cannot hover is just a broken card.
     ═══════════════════════════════════════════════════════════ */
  function initTilt() {
    if (reduceMotion || !finePointer) return;

    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-tilt]'));
    if (!cards.length) return;

    var MAX_DEG = 7; // Subtle. Anything past ~10deg looks like a gimmick.

    cards.forEach(function (card) {
      var rect = null;
      var frame = null;

      function measure() {
        rect = card.getBoundingClientRect();
      }

      function onMove(event) {
        if (!rect) measure();
        if (frame) return;

        frame = window.requestAnimationFrame(function () {
          frame = null;

          // Normalise cursor position within the card to -0.5 … +0.5
          var px = (event.clientX - rect.left) / rect.width;
          var py = (event.clientY - rect.top) / rect.height;

          card.style.setProperty('--ry', ((px - 0.5) * MAX_DEG * 2).toFixed(2) + 'deg');
          card.style.setProperty('--rx', ((0.5 - py) * MAX_DEG * 2).toFixed(2) + 'deg');
          card.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
          card.style.setProperty('--my', (py * 100).toFixed(1) + '%');
        });
      }

      function reset() {
        if (frame) {
          window.cancelAnimationFrame(frame);
          frame = null;
        }
        card.style.setProperty('--rx', '0deg');
        card.style.setProperty('--ry', '0deg');
        rect = null; // Re-measure next time; the page may have reflowed.
      }

      card.addEventListener('mouseenter', measure);
      card.addEventListener('mousemove', onMove);
      card.addEventListener('mouseleave', reset);
      // Keyboard users get the depth without the pointer-driven rotation.
      card.addEventListener('focusout', reset);
    });

    // Cached rects go stale on resize.
    window.addEventListener('resize', function () {
      cards.forEach(function (card) {
        card.style.setProperty('--rx', '0deg');
        card.style.setProperty('--ry', '0deg');
      });
    }, { passive: true });
  }

  /* ═══════════════════════════════════════════════════════════
     3. SCROLL REVEAL
     ═══════════════════════════════════════════════════════════ */
  function initReveal() {
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
    if (!items.length) return;

    // No IntersectionObserver (or motion suppressed)? Show everything at once.
    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-revealed'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;

        var el = entry.target;
        // Stagger siblings so a grid cascades instead of popping in as a block.
        var delay = parseInt(el.getAttribute('data-reveal-delay') || '0', 10);
        el.style.setProperty('--reveal-delay', delay + 'ms');
        el.classList.add('is-revealed');

        observer.unobserve(el); // Reveal once; never animate back out.
      });
    }, {
      threshold: 0.12,
      rootMargin: '0px 0px -60px 0px'
    });

    items.forEach(function (el) { observer.observe(el); });

    /* SAFETY NET: if anything is still hidden after 3s (observer edge cases,
       elements inside a scroll container, etc.) force it visible. Invisible
       content is a far worse bug than a missing animation. */
    window.setTimeout(function () {
      items.forEach(function (el) {
        if (!el.classList.contains('is-revealed')) {
          el.classList.add('is-revealed');
        }
      });
    }, 3000);
  }

  /* ═══════════════════════════════════════════════════════════
     4. COUNT-UP STATISTICS
     ═══════════════════════════════════════════════════════════ */
  function initCounters() {
    var counters = Array.prototype.slice.call(document.querySelectorAll('[data-count-to]'));
    if (!counters.length) return;

    function paint(el, value, suffix) {
      // textContent, not innerHTML — these values originate in the database.
      el.textContent = Math.round(value).toLocaleString('en-US') + suffix;
    }

    if (reduceMotion || !('IntersectionObserver' in window)) {
      counters.forEach(function (el) {
        paint(el, parseFloat(el.getAttribute('data-count-to')) || 0,
              el.getAttribute('data-count-suffix') || '');
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;

        var el     = entry.target;
        var target = parseFloat(el.getAttribute('data-count-to')) || 0;
        var suffix = el.getAttribute('data-count-suffix') || '';
        var start  = null;
        var DURATION = 1400;

        function step(timestamp) {
          if (start === null) start = timestamp;
          var progress = Math.min((timestamp - start) / DURATION, 1);
          // easeOutCubic — fast then settling, reads as "counting up".
          var eased = 1 - Math.pow(1 - progress, 3);

          paint(el, target * eased, suffix);

          if (progress < 1) window.requestAnimationFrame(step);
        }

        window.requestAnimationFrame(step);
        observer.unobserve(el);
      });
    }, { threshold: 0.4 });

    counters.forEach(function (el) { observer.observe(el); });
  }

  /* ═══════════════════════════════════════════════════════════
     5. 3D GALLERY (room details)
     ═══════════════════════════════════════════════════════════ */
  function initGallery() {
    var galleries = Array.prototype.slice.call(document.querySelectorAll('[data-gallery]'));

    galleries.forEach(function (gallery) {
      var slides = Array.prototype.slice.call(gallery.querySelectorAll('.gallery3d__slide'));
      var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('.gallery3d__thumb'));
      if (slides.length < 2) return;

      function show(index) {
        slides.forEach(function (slide, i) {
          slide.classList.toggle('is-active', i === index);
        });
        thumbs.forEach(function (thumb, i) {
          thumb.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });
      }

      thumbs.forEach(function (thumb, i) {
        thumb.addEventListener('click', function () { show(i); });
      });

      // Left/right arrows move between images once a thumb has focus.
      gallery.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;

        var current = thumbs.findIndex(function (t) {
          return t.getAttribute('aria-selected') === 'true';
        });
        if (current < 0) current = 0;

        var next = event.key === 'ArrowRight'
          ? (current + 1) % thumbs.length
          : (current - 1 + thumbs.length) % thumbs.length;

        show(next);
        thumbs[next].focus();
        event.preventDefault();
      });

      show(0);
    });
  }

  /* ═══════════════════════════════════════════════════════════
     6. DATE RANGE COUPLING
     Keeps check-out strictly after check-in on every public form,
     mirroring the server rule so the user never submits an
     impossible range.
     ═══════════════════════════════════════════════════════════ */
  function initDateRanges() {
    var forms = Array.prototype.slice.call(document.querySelectorAll('form'));

    forms.forEach(function (form) {
      var inEl  = form.querySelector('input[name="check_in"]');
      var outEl = form.querySelector('input[name="check_out"]');
      if (!inEl || !outEl) return;

      function addDays(value, days) {
        var d = new Date(value + 'T00:00:00');
        if (isNaN(d.getTime())) return null;
        d.setDate(d.getDate() + days);
        return d.toISOString().slice(0, 10);
      }

      var today = new Date().toISOString().slice(0, 10);
      inEl.min = today;

      function sync() {
        if (!inEl.value) return;

        var minOut = addDays(inEl.value, 1);
        if (!minOut) return;

        outEl.min = minOut;

        // Push an invalid check-out forward rather than silently allowing it.
        if (!outEl.value || outEl.value <= inEl.value) {
          outEl.value = minOut;
        }

        // Mirror the 30-night server cap.
        var maxOut = addDays(inEl.value, 30);
        if (maxOut) outEl.max = maxOut;
      }

      inEl.addEventListener('change', sync);
      sync();
    });
  }

  /* ═══════════════════════════════════════════════════════════
     7. LIVE PRICE QUOTE
     Display only — actions/booking/store.php recalculates the real
     total server-side, so a tampered response changes nothing.
     ═══════════════════════════════════════════════════════════ */
  function initLiveQuote() {
    var form = document.querySelector('[data-quote-form]');
    if (!form) return;

    var roomId = form.getAttribute('data-room-id');
    if (!roomId) return;

    var fields = {
      nights:  form.querySelector('[data-quote-nights]'),
      service: form.querySelector('[data-quote-service]'),
      tax:     form.querySelector('[data-quote-tax]'),
      total:   form.querySelector('[data-quote-total]')
    };

    var inEl  = form.querySelector('input[name="check_in"]');
    var outEl = form.querySelector('input[name="check_out"]');
    if (!inEl || !outEl) return;

    var timer = null;
    var controller = null;

    function setText(el, value) {
      if (el) el.textContent = value;
    }

    function fetchQuote() {
      if (!inEl.value || !outEl.value || outEl.value <= inEl.value) return;

      // Abandon a request still in flight so responses cannot land out of order.
      if (controller) controller.abort();
      controller = new AbortController();

      setText(fields.total, '…');

      var url = form.getAttribute('data-quote-url')
        + '?room_id=' + encodeURIComponent(roomId)
        + '&check_in=' + encodeURIComponent(inEl.value)
        + '&check_out=' + encodeURIComponent(outEl.value);

      fetch(url, {
        signal: controller.signal,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data || !data.success) {
            setText(fields.total, '—');
            return;
          }
          setText(fields.nights,  data.nights + ' Night(s)');
          setText(fields.service, data.formatted_service);
          setText(fields.tax,     data.formatted_tax);
          setText(fields.total,   data.formatted_total);
        })
        .catch(function (error) {
          if (error && error.name === 'AbortError') return; // superseded, not a failure
          setText(fields.total, '—');
        });
    }

    function debounced() {
      window.clearTimeout(timer);
      timer = window.setTimeout(fetchQuote, 350);
    }

    [inEl, outEl].forEach(function (el) {
      el.addEventListener('change', debounced);
    });
  }

  /* ═══════════════════════════════════════════════════════════
     BOOT
     ═══════════════════════════════════════════════════════════ */
  function boot() {
    initHeroVideo();
    initParallax();
    initTilt();
    initReveal();
    initCounters();
    initGallery();
    initDateRanges();
    initLiveQuote();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
