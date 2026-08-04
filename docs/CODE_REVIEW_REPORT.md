# Code Review & Security Audit — Findings and Fixes

**Project:** Hotel Reservation System (CST 226-2)
**Review date:** 30 July 2026
**Scope:** all 130 source files — core classes, models, services, action handlers, pages, CSS, JS, Apache config
**Method:** manual line-by-line review plus scripted checks for structural balance, unresolvable class imports, unescaped output, missing guards, missing CSRF verification and leaked exception messages.

**Result:** 31 issues found. All fixed. 8 were critical.

> Useful for the report's *Testing* and *Challenges & Solutions* sections — each entry names the attack or failure mode, not just the change.

---

## Summary

| Severity | Found | Fixed |
|---|:--:|:--:|
| 🔴 Critical — crashes or exploitable | 8 | 8 |
| 🟠 High — security weakness | 9 | 9 |
| 🟡 Medium — functional bug | 9 | 9 |
| 🔵 Low — quality / accessibility | 5 | 5 |
| **Total** | **31** | **31** |

---

## 🔴 Critical

### C1 — Exception classes could never be loaded (fatal 500)

**Files:** `src/Core/Exceptions/*.php` → moved to `src/Exceptions/`

The three exception classes declared `namespace App\Exceptions;` but the files sat in `src/Core/Exceptions/`. The PSR-4 autoloader maps `App\Exceptions\BookingConflictException` to `src/Exceptions/BookingConflictException.php`, which did not exist.

**Impact:** every `throw new BookingConflictException(...)` raised `Error: Class not found` instead. So the moment two guests competed for one room — the exact scenario the booking engine exists to handle — the guest got a blank 500 page. The same applied to `ValidationException` on any failed image upload.

**Fix:** moved all three files to `src/Exceptions/` so path matches namespace. Verified by a script that resolves every `use App\...` import against the class map: 40/40 now resolve.

---

### C2 — A guest could book any room for LKR 0.00

**Files:** `actions/booking/store.php`, `src/Services/PricingService.php`

`store.php` forwarded the entire `$_POST` array into `BookingService::create()`, which passed `$data['discount']` to `PricingService::quote()`. The service only clamped the lower bound:

```php
$discountAmt = max(0.00, $discount);          // no upper limit
$taxableAmount = max(0.00, $subtotal - $discountAmt);
```

**Impact:** adding one field to the request — `discount=999999` — set `taxableAmount` to 0, so `service_charge`, `tax_amount` and `total_amount` all became `0.00`. A free stay, with a valid confirmed booking record. No special tooling needed, just DevTools.

**Fix, in two independent layers:**

1. `store.php` now builds an explicit six-field payload. `discount` is not among them, so it cannot arrive from a request at all.
2. `PricingService` clamps both ends — `min(max(0.00, $discount), $subtotal)` — so even a future caller passing a bad value cannot drive a total below zero.

Also added: occupancy must fit the room type's declared capacity, max 30 nights, max 3 concurrent active bookings per guest.

---

### C3 — Same free-booking hole in the walk-in flow

**File:** `staff/walkin.php`

`$bs->create($_POST, $guest, 'walk_in', Auth::id())` — identical raw-`$_POST` forwarding. A receptionist could grant themselves an unlimited discount, bypassing the manager-approval rule for discounts over 10%.

**Fix:** explicit payload, same as C2. The form was also missing `adults` / `children` inputs that the handler required, so **every walk-in submission failed validation** — those fields are now present.

---

### C4 — Every walk-in guest shared one hard-coded password

**File:** `staff/walkin.php`

```php
'password_hash' => User::hashPassword('Walkin@1234'),
```

**Impact:** the password is a literal in a public GitHub repository. Anyone could log in as any walk-in guest and read their name, phone, NIC/passport, reservations and invoices. Argon2id hashing is irrelevant when the plaintext is published.

**Fix:** each account now gets `bin2hex(random_bytes(24))` — a value nobody, including staff, ever sees. Guests who want online access use the password-reset flow.

---

### C5 — Protected directories were not actually protected

**File:** `.htaccess`

```apache
RedirectMatch 403 ^/(config|src|storage|database|docs)/.*$
```

`RedirectMatch` matches the **full URL path**. The app is served from `/Hotel%20Reservation%20System/`, so the pattern never matched anything and every "protected" directory was reachable.

**Impact:** `config/database.php`, `src/Core/Database.php` and the whole application internals were fetchable. The saving grace was that those files only `define()` or `return` and emit no output — but the control that was supposed to stop this did nothing, and one `echo` or a parse error would have exposed credentials.

**Fix:** replaced with a `Require all denied` `.htaccess` **inside** each protected folder (`config/`, `src/`, `storage/`, `database/`, `includes/`). Directory-relative, so it works regardless of what the project folder is named. `actions/` deliberately keeps `Require all granted` — form handlers must stay reachable and defend themselves in PHP.

---

### C6 — No `.gitignore`: DB credentials headed for GitHub

There was no `.gitignore`, so `config/config.php` and `config/database.php` — containing the live database password — would be committed to the public repository the assignment asks you to submit.

**Fix:** created `.gitignore` excluding both config files, `storage/logs/*`, `uploads/rooms/*` and editor noise, while keeping `config/config.sample.php`, `database/schema.sql` and `database/seed.sql` tracked (the marker needs those to run the project).

> **Action for the group:** if `config.php` has already been pushed, change the database password. Removing a file from a later commit does not remove it from Git history.

---

### C7 — Reflected XSS on the rooms listing page

**File:** `rooms.php` (line 158 of the previous version)

```php
<a href="<?= url('room-details.php?slug=' . $type->slug . '&check_in=' . $checkIn . ...) ?>">
```

`$checkIn` came straight from `$_GET` with no validation, and `url()` performs no escaping.

**Impact:** `?check_in=" onmouseover="alert(document.cookie)` closed the `href` attribute and injected an event handler. With a logged-in guest following the link, an attacker could act as that guest.

**Fix, three layers:**

1. Dates are validated by `normaliseStay()` — strict `Y-m-d` via `DateTime::createFromFormat`, past dates rejected, stay capped at 30 nights.
2. All links now use the new `urlWithQuery()` helper, which runs values through `http_build_query()` (URL-encoded).
3. The resulting URL is still passed through `e()` on output.

The `HttpOnly` session cookie was already limiting the damage — that layer did its job.

---

### C8 — Password length check bypassable with digits

**File:** `src/Core/Validator.php`

```php
case 'min':
    if (is_numeric($value) && (float)$value < $min) { ... }        // checked first
    elseif (is_string($value) && mb_strlen($value) < $min) { ... }
```

For a numeric password the first branch won. `min:8` on `"99999"` evaluated `99999 < 8` → false → **passed**.

**Impact:** 5-digit PIN-style passwords were accepted anywhere `min:8` was the only rule — registration, password change, password reset, admin account creation. Trivially brute-forceable.

**Fix:** whether `min`/`max` means *length* or *range* is now decided by the ruleset (does it contain `integer`/`numeric`/`decimal`?), not by what the value happens to look like. Explicit `min_length` / `max_length` rules were added too.

This change also fixed a latent bug in the opposite direction: `room_number => 'min:1|max:10'` used to **reject** room "305", because 305 > 10 as a number.

A real `password` rule now enforces the specification's policy: 8+ characters with upper, lower, digit and symbol, not in a common-password blocklist, and not containing the user's own email name. Applied at registration, password change and admin account creation.

---

## 🟠 High

### H1 — Unknown validation rules were silently ignored

The `applyRule()` switch had no `default`, so a misspelled or unimplemented rule fell through and validated nothing. A field the developer believed was checked was wide open. Rules referenced in the spec (`date_format`, `max_nights`) did not exist at all.

**Fix:** added a `KNOWN_RULES` whitelist; an unrecognised rule is logged as a warning instead of being ignored. Implemented the 10 missing rules (`date_format`, `max_nights`, `before_days`, `password`, `accepted`, `boolean`, `regex`, `string`, `min_length`, `max_length`). Verified by script: all 21 rules used across the project are implemented.

### H2 — Booking state machine bypassed on cancellation

`actions/booking/cancel.php` handled `pending` explicitly, then in a bare `else` set **any** other status to `cancel_requested` — never calling `BookingStatus::canTransition()`. A `checked_out`, `cancelled` or `rejected` booking could be re-opened, corrupting revenue reports.

**Fix:** an explicit whitelist of cancellable statuses; anything else is refused and audit-logged. Duplicate approval rows are prevented, and the whole update runs in one transaction.

### H3 — Ownership check depended on PDO typing

`$booking->user_id !== $user->id` — a strict comparison between values whose types depend on driver configuration. It happened to be safe (`STRINGIFY_FETCHES => false`), but a config change would have silently broken authorisation.

**Fix:** added `Booking::belongsTo(int $userId)` which casts both sides. A guest requesting another guest's booking now gets **404, not 403** — a 403 confirms the record exists.

### H4 — Internal exception messages shown to users

Seven locations did `Flash::error($e->getMessage())` or echoed it as JSON inside `catch (\Throwable)`. PDO messages quote SQL, table and column names.

**Fix:** typed catch blocks — our own domain exceptions (`BookingConflictException`, `RuntimeException` from the services) are shown because we wrote those strings; everything else is logged and replaced with a generic message.

**Subtle point worth noting in the report:** `PDOException extends RuntimeException`, so `catch (\RuntimeException)` also catches database errors. The `catch (\PDOException)` block must come **first**. It now does in all three staff pages.

### H5 — Public API leaked the hotel's room inventory

`api/availability.php` returned `'rooms' => $rooms` — internal room IDs, room numbers, floor numbers and housekeeping status — to **anonymous** callers.

**Fix:** anonymous callers get `count` and `available` only. The room array is returned solely to authenticated `receptionist`/`manager`/`admin`. Both endpoints also now validate date format strictly, cap the range at 30 nights and reject past check-ins.

### H6 — Rate limiter was both too weak and too aggressive

```php
// counting
WHERE (email = :email OR ip_address = INET6_ATON(:ip))
// clearing on success
DELETE FROM login_attempts WHERE email = :email OR ip_address = INET6_ATON(:ip)
```

- **Too weak:** anyone with one valid account could log in and wipe the failure counter for *every* account on that IP, then keep brute-forcing.
- **Too aggressive:** on a shared campus IP, one student's typos locked out the whole lab — a self-inflicted denial of service during the demo.

**Fix:** separate counters — 5 failures per email, 20 per IP; clearing is scoped to the single account that authenticated (`AND`, not `OR`). Added registration throttling (5/hour/IP), applied to the public contact form too, and a `purgeOld()` housekeeping method.

The lockout is also now **visible**: `Auth::wasLockedOut()` lets the login page say *"Too many failed attempts. Try again in N minutes."* instead of a misleading "invalid password".

### H7 — `redirect()` was an open redirect

```php
header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
```

Any caller passing an absolute URL redirected off-site. `actions/auth/login.php` fed it the session's `intended` value.

**Fix:** `redirect()` only honours absolute URLs that begin with `BASE_URL`; anything else falls back to the home page. CR/LF are stripped to prevent header injection. The `intended` path is additionally validated in `login.php` (no scheme, no `..`). The JS fallback now uses `json_encode` with hex flags — `e()` escapes for HTML and is not safe in a JS string context.

### H8 — Upload path traversal, and missing upload hardening

`Uploader::store()` did `trim($subdir, '/')`, which does not stop `../../`. There was also no `is_uploaded_file()` check, and PHP execution was only disabled in `uploads/rooms/`, not the whole `uploads/` tree.

**Fix:** `$subdir` whitelisted against `/^[a-z0-9_-]+$/i`; added `is_uploaded_file()`; created `uploads/.htaccess` covering all subfolders with `php_flag engine off` (wrapped in `IfModule` so a non-mod_php server does not 500), a deny rule for executable extensions, and `AddType text/plain .php`. Raw `UPLOAD_ERR_*` codes are now translated to human messages.

### H9 — Missing Content-Security-Policy

The specification called for CSP; `.htaccess` did not set it.

**Fix:** CSP is sent from `bootstrap.php` with a **per-request nonce**, because Apache cannot generate one. `script-src 'self' 'nonce-…'` with no `unsafe-inline`, so the single inline theme-flash script runs while any injected `<script>` is blocked.

This required removing all 9 inline event handlers (`onclick`, `onsubmit`, `onchange`), which CSP blocks. Rather than weakening the policy with `unsafe-inline`, they were converted to `data-confirm` / `data-print` / `data-reload-param` attributes handled by delegated listeners in `components.js` — which also satisfies the project's own "no inline event handlers" rule.

---

## 🟡 Medium — functional bugs

### M1 — Occupied rooms could not be booked for future dates

`AvailabilityService` filtered `status IN ('available','cleaning')`. But housekeeping status describes the room **now**, not on the requested dates — the date-overlap check already handles conflicts.

**Impact:** every room with a guest currently in it silently vanished from search results. On a busy day most of the hotel looked sold out for next month. **Direct lost revenue, and the most damaging functional bug found.**

**Fix:** only `maintenance` blocks a sale.

### M2 — Home page linked to a URL the detail page could not read

`index.php` linked `room-details.php?id=…` while `room-details.php` only read `$_GET['slug']` → 404 on every featured room.

**Fix:** the detail page accepts either; the home page now links by slug.

### M3 — `grid--sidebar` was never defined

`room-details.php` used `class="grid grid--sidebar"`, which exists in no stylesheet. It fell back to a single column, pushing the booking widget far below the content on desktop.

**Fix:** real `.detail-layout` grid (`1fr / 380px`) with a sticky rail, collapsing to one column under 1024px, plus a sticky mobile booking bar.

### M4 — Double submission was still possible

`main.js` swapped the submit button's label but left it **enabled**. A double-click sent two POSTs — two bookings, two payments.

**Fix:** a `data-submitting` latch plus disabling the button on the next tick (disabling it synchronously would drop a named submit button's value from the payload). Also: the guard no longer fires on GET filter forms, no longer sticks on "Processing…" when validation blocks the submit, and releases after 10s if navigation never happens.

### M5 — The page could stay permanently faded out

`main.js` added `page-exiting` on every link click and never removed it. A cancelled navigation or a back/forward-cache restore left the page invisible.

**Fix:** reset on a 4s timer and on `pageshow` when restored from cache. The preloader also now hard-hides after 2.5s so a stalled asset cannot leave an overlay covering the page.

### M6 — Scroll reveal could hide content permanently

`main.js` added `.reveal` to **every** `.card`, including cards inside `display:none` modals, which the observer never fires for.

**Fix:** modal contents excluded, and a 3s failsafe force-reveals anything still hidden. Same failsafe in `public3d.js`, plus a `.no-js` rule in CSS so nothing is invisible without JavaScript.

### M7 — Booking reference collisions were unhandled

`booking_ref` is `UNIQUE` but only 4 hex characters (65 536/day) were generated with no retry — a duplicate threw a raw SQL error at the guest.

**Fix:** up to 8 attempts, checked inside the transaction.

### M8 — `substr()` corrupted multibyte descriptions

`substr($type->description, 0, 100)` cuts mid-character on UTF-8, producing mojibake.

**Fix:** `mb_substr` / `mb_strlen` throughout, with a proper ellipsis only when truncation occurred.

### M9 — Invalid dates caused 500s; hard-coded tax labels

`new DateTime($_GET['check_in'])` threw uncaught on bad input. Labels read "(10%)" and "(8%)" while the real values came from `settings` — they would silently disagree the moment an admin changed the tax rate.

**Fix:** `PricingService` validates dates strictly and throws a typed exception the callers handle; every page reads `Setting::get('service_charge')` / `Setting::get('tax_rate')` for both the label and the maths.

---

## 🔵 Low — quality & accessibility

- **L1** `Database` threw `'Database connection failure: ' . $e->getMessage()`, exposing the DSN (host, database name, sometimes user). Now logged, generic message thrown.
- **L2** `Validator`'s `unique`/`exists` interpolated table and column names into SQL. Developer-controlled today, but now validated against `/^[A-Za-z_][A-Za-z0-9_]*$/` and backtick-quoted — defence in depth.
- **L3** No skip link and `<main>` had no `id`. Added `.skip-link` and `id="main"` to all 35 pages.
- **L4** `admin/users.php` swallowed validation failures — the admin saw a silent redirect with no error. Now reports them.
- **L5** N+1 queries: `availableCount()` was called inside render loops (9+ extra queries per page load). Added `availableCountsForTypes()` — one query for the whole page.

---

## Public site rebuild

Beyond the fixes, the five public pages were rebuilt with the requested 3D treatment. All of it is driven by CSS custom properties from `tokens.css`, so it works unchanged in both themes.

**New files:** `assets/css/public.css`, `assets/js/public3d.js`

| Effect | How it works |
|---|---|
| Hero parallax | 3 layers, JS writes `--parallax` on scroll at different rates; CSS keeps ownership of the full transform |
| Card tilt + glare | Pointer position → `--rx`/`--ry` rotation and `--mx`/`--my` for a cursor-tracking specular highlight |
| Layered depth | `.tilt__depth-1/2/3` lift children off the card face with `translateZ` |
| Word-by-word headline | Staggered `wordRise` keyframe rotating in from `rotateX(-35deg)` |
| Scroll reveal | IntersectionObserver, staggered per grid position, reveal-once |
| Animated counters | `requestAnimationFrame` count-up with `easeOutCubic`, tabular figures so digits don't jitter |
| 3D gallery | Cross-fading slides with keyboard-navigable thumbnails |
| Live price quote | Debounced `fetch` with `AbortController` so responses can't land out of order |

**Performance and correctness discipline:**

- Every scroll/mousemove handler is `requestAnimationFrame`-throttled; element rects are cached, never read inside the handler (no layout thrashing).
- Only `transform` and `opacity` are animated — both GPU-composited, no reflow.
- `prefers-reduced-motion: reduce` disables **all** of it via one override block.
- Tilt is disabled on touch devices and coarse pointers, where it only makes cards feel broken.
- `@media print` strips the decoration.
- Contrast is guaranteed by a gradient scrim rather than hoping the hero photo cooperates.
- The gold gradient headline has a plain-gold fallback outside `@supports (background-clip: text)`, so it degrades to gold text rather than invisible text.

Also fixed: two pages each declared a global `safeDate()` — a fatal redeclare if any request ever loaded both. Consolidated into `normaliseStay()` / `safeDate()` / `isValidDate()` in `functions.php`.

---

## Verification performed

| Check | Result |
|---|---|
| Brace/paren/bracket balance, all 143 files | ✓ balanced |
| PHP alternative syntax pairing (`if`/`endif`, `foreach`/`endforeach`) | ✓ matched |
| CSS and JS brace balance | ✓ balanced |
| Every `use App\...` import resolves to a real file | ✓ 40/40 |
| Class namespace matches file path | ✓ 40/40 |
| Duplicate global function declarations | ✓ none |
| All helper functions defined before use | ✓ 10/10 |
| Every validation rule used is implemented | ✓ 21/21 |
| `min`/`max` semantics change — regression sweep | ✓ all 33 usages correct |
| Raw `$_POST` forwarded into a service | ✓ none |
| Request variable concatenated into SQL | ✓ none |
| MD5/SHA1 on a password | ✓ none |
| Every `actions/` handler verifies CSRF | ✓ 6/6 |
| Every protected page has a role guard | ✓ 25/25 |
| Exception messages reaching the user | ✓ only our own typed exceptions |
| Inline event handlers (CSP-blocking) | ✓ 0 remaining |
| `<main id="main">` for the skip link | ✓ 35/35 |

**Not verified — needs to run on your XAMPP:** no PHP binary was available in this environment, so these checks are structural, not executed. Before the demo please run through §17 of `PROJECT_SPECIFICATION.md`, and in particular:

1. Load every page in both themes — confirms no fatal errors and that CSP does not block anything.
2. Open DevTools → Console on each page — a CSP violation appears there, not on the page.
3. Book a room end to end, then try booking the same room and dates in a second browser (should conflict, not 500).
4. Set `discount=99999` on the booking POST via DevTools — total must stay correct.
5. Try registering with password `99999` — must be rejected.
6. Request `/config/database.php` directly — must be 403.
7. Create a walk-in booking — confirms the new `adults`/`children` fields work.

---

## Suggested follow-ups (not blocking)

1. **Self-host the webfonts.** Google Fonts is loaded from a CDN; if the lab has no internet on demo day the display font silently falls back. Downloading three `woff2` files into `assets/fonts/` removes that risk. The spec calls for this.
2. **Rotate the DB password** if `config/config.php` was ever pushed.
3. **`audit_logs` read access for managers.** §3.1 grants managers read access but `admin/audit-logs.php` uses the admin guard.
4. **Add a `CHECK` constraint on `bookings.discount`** (`discount <= subtotal`) so the database enforces C2 as well as the application.
5. **Consider a DB-level exclusion guard** on overlapping bookings. The `SELECT … FOR UPDATE` transaction is correct, but a constraint would make double-booking impossible even from phpMyAdmin.

---

*Every fix carries an inline comment naming the attack or failure mode it prevents — useful when the marker asks "why is this line here?"*
