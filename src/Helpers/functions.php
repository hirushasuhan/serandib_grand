<?php
declare(strict_types=1);

/**
 * Escape HTML special characters for safe output.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate full application URL.
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

/**
 * Generate asset URL.
 */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Redirect to a path inside this application and exit.
 *
 * SECURITY: only same-origin destinations are allowed. The previous version
 * forwarded any value starting with "http" straight into the Location header,
 * which made every caller a potential open redirect — useful to an attacker for
 * phishing, because the link genuinely starts on our domain.
 */
function redirect(string $path): void
{
    $target = url($path);

    // Absolute URL supplied? Only honour it if it stays on our own origin.
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path)) {
        $target = str_starts_with($path, BASE_URL) ? $path : url('');
    }

    if (!headers_sent()) {
        // Strip CR/LF to prevent header injection via a crafted path.
        header('Location: ' . str_replace(["\r", "\n"], '', $target), true, 302);
        exit;
    }

    // Fallback when output has already started. json_encode escapes for a JS
    // string context — e() is for HTML and would not be safe here.
    echo '<script>window.location.href=' . json_encode(
        $target,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) . ';</script>';
    exit;
}

/**
 * Build a query string safely for use inside an href.
 *
 * Always use this instead of concatenating request values into a URL —
 * `url('page.php?d=' . $_GET['d'])` let a crafted value break out of the
 * href attribute and execute script.
 */
function urlWithQuery(string $path, array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    return url($path) . ($params ? '?' . http_build_query($params) : '');
}

/**
 * Retrieve old input from session for form repopulation.
 */
function old(string $key, mixed $default = ''): mixed
{
    if (isset($_SESSION['_old'][$key])) {
        $val = $_SESSION['_old'][$key];
        unset($_SESSION['_old'][$key]);
        return $val;
    }
    return $default;
}

/**
 * Format currency string.
 */
function money(float|int|string $amount): string
{
    return CURRENCY . ' ' . number_format((float)$amount, 2);
}

/**
 * Format Date cleanly.
 */
function formatDate(?string $dateStr, string $format = 'd M Y'): string
{
    if (!$dateStr) return '';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : $dateStr;
}

/**
 * Is this string a real calendar date in strict Y-m-d form?
 *
 * strtotime() is far too permissive for anything that will be stored or
 * compared in SQL — it accepts "next friday", "+1 week" and other text that
 * then reaches MySQL as a meaningless value. Use this instead.
 */
function isValidDate(mixed $value, string $format = 'Y-m-d'): bool
{
    if (!is_string($value) || $value === '') {
        return false;
    }
    $d = DateTime::createFromFormat($format, $value);
    return $d !== false && $d->format($format) === $value;
}

/**
 * Return a request value only if it is a valid Y-m-d date, else the fallback.
 *
 * Defined once here rather than in each page: rooms.php and room-details.php
 * both declared their own copy at global scope, which is a fatal redeclare the
 * moment any request loads both.
 */
function safeDate(mixed $value, string $fallback): string
{
    return isValidDate(is_string($value) ? trim($value) : $value)
        ? trim((string) $value)
        : $fallback;
}

/**
 * Clamp a requested stay to the system's business rules and return
 * [checkIn, checkOut, nights]. Used by every public page that takes dates.
 */
function normaliseStay(mixed $rawIn, mixed $rawOut, int $maxNights = 30): array
{
    $today    = date('Y-m-d');
    $checkIn  = safeDate($rawIn, $today);
    $checkOut = safeDate($rawOut, date('Y-m-d', strtotime('+2 days')));

    if ($checkIn < $today) {
        $checkIn = $today;
    }
    if ($checkOut <= $checkIn) {
        $checkOut = date('Y-m-d', strtotime($checkIn . ' +1 day'));
    }
    if ((strtotime($checkOut) - strtotime($checkIn)) / 86400 > $maxNights) {
        $checkOut = date('Y-m-d', strtotime($checkIn . ' +' . $maxNights . ' days'));
    }

    $nights = (int) ((strtotime($checkOut) - strtotime($checkIn)) / 86400);

    return [$checkIn, $checkOut, $nights];
}

/**
 * Dump and die helper for debugging (not used in production).
 */
function dd(mixed ...$vars): void
{
    echo '<pre style="background:#111;color:#0f0;padding:15px;border-radius:5px;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit;
}

/**
 * Inline SVG icon set — replaces the emoji that used to be scattered across
 * every template (🚪 🛏️ 📅 ⭐ etc).
 *
 * WHY: emoji render differently per OS/browser font (Windows, macOS, Android
 * and every emoji-font browser extension draw them differently), can't take
 * `currentColor` so they never match button/text colour in either theme, and
 * scale inconsistently next to real text. These are plain stroke-based paths
 * (the common minimal-icon style used by icon sets like Feather/Lucide) drawn
 * with `stroke="currentColor"`, so every icon automatically matches whatever
 * text colour surrounds it and both themes without any extra CSS.
 *
 * Usage: <?= icon('bed') ?>  or  <?= icon('bed', 20, 'icon--muted') ?>
 * The returned markup is trusted (no user input flows through it) — it does
 * not need e().
 */
function icon(string $name, int $size = 16, string $class = ''): string
{
    static $paths = [
        'ban'            => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
        'compass'        => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
        'alert-triangle' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'star'           => '<polygon points="12 2.5 15.09 8.76 22 9.77 17 14.64 18.18 21.52 12 18.27 5.82 21.52 7 14.64 2 9.77 8.91 8.76 12 2.5" fill="currentColor" stroke="none"/>',
        'star-outline'   => '<polygon points="12 2.5 15.09 8.76 22 9.77 17 14.64 18.18 21.52 12 18.27 5.82 21.52 7 14.64 2 9.77 8.91 8.76 12 2.5"/>',
        'sparkle'        => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18" fill="none"/>',
        'leaf'           => '<path d="M11 20A7 7 0 0 1 4 13c0-5 4-11 8-11s8 6 8 11a7 7 0 0 1-9 6.75"/><path d="M12 11c-3 3-4 6-4 9"/>',
        'utensils'       => '<path d="M3 2v7c0 1.1.9 2 2 2s2-.9 2-2V2M5 2v20M9 2v7c0 1.1.9 2 2 2M21 2c-2.2 0-4 4-4 8s1.8 4 4 4M21 2v20"/>',
        'award'          => '<circle cx="12" cy="8" r="6"/><path d="M8.21 13.89 7 22l5-3 5 3-1.21-8.11"/>',
        'heart'          => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>',
        'check-circle'   => '<circle cx="12" cy="12" r="10"/><polyline points="8 12.5 11 15.5 16 9"/>',
        'x-circle'       => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        'check'          => '<polyline points="20 6 9 17 4 12"/>',
        'plus'           => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'tag'            => '<path d="M20.59 13.41 12 22 2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/>',
        'users'          => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'           => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'log-out'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'log-in'         => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'shield'         => '<path d="M12 2 4 5v6c0 5.25 3.4 9.74 8 11 4.6-1.26 8-5.75 8-11V5z"/>',
        'zap'            => '<polygon points="13 2 3 14 11 14 11 22 21 10 13 10 13 2"/>',
        'mail'           => '<rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2 6 12 13 22 6"/>',
        'map-pin'        => '<path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'phone'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13 1 .36 1.98.68 2.92a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.16-1.16a2 2 0 0 1 2.11-.45c.94.32 1.92.55 2.92.68A2 2 0 0 1 22 16.92z"/>',
        'printer'        => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'settings'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 0 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.55-1H3a2 2 0 0 1 0-4h.09a1.7 1.7 0 0 0 1.55-1.11 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.55V3a2 2 0 0 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.55 1H21a2 2 0 0 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1z"/>',
        'calendar'       => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clipboard-list' => '<rect x="6" y="3" width="12" height="4" rx="1"/><path d="M6 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/>',
        'bell'           => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'globe'          => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'credit-card'    => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'trending-up'    => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'bar-chart'      => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'bed'            => '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/>',
        'dollar-sign'    => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'search'         => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'maximize'       => '<path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M3 16v3a2 2 0 0 0 2 2h3"/>',
        'wifi'           => '<path d="M5 12.55a11 11 0 0 1 14 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
        'truck'          => '<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'building'       => '<rect x="4" y="2" width="16" height="20" rx="1"/><line x1="9" y1="6" x2="9" y2="6.01"/><line x1="15" y1="6" x2="15" y2="6.01"/><line x1="9" y1="10" x2="9" y2="10.01"/><line x1="15" y1="10" x2="15" y2="10.01"/><line x1="9" y1="14" x2="9" y2="14.01"/><line x1="15" y1="14" x2="15" y2="14.01"/><path d="M9 22v-4h6v4"/>',
        'droplet'        => '<path d="M12 2s7 8.5 7 13a7 7 0 0 1-14 0c0-4.5 7-13 7-13z"/>',
        'activity'       => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'arrow-right'    => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'lock'           => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'moon'           => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'info'           => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'refresh-cw'     => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'door-open'      => '<path d="M13 4v16M3 20h9M13 4l7 2v14h-7M8 4H3v16h5"/>',
        'umbrella'       => '<path d="M22 12a10 10 0 1 0-20 0z"/><path d="M12 12v8a2 2 0 0 0 4 0M12 2v1"/>',
        'baby'           => '<circle cx="12" cy="8" r="4"/><path d="M6 21v-3a6 6 0 0 1 12 0v3"/>',
    ];

    $body = $paths[$name] ?? $paths['ban'];
    $extra = $class !== '' ? ' ' . $class : '';

    return sprintf(
        '<svg class="icon%s" viewBox="0 0 24 24" width="%d" height="%d" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        $extra,
        $size,
        $size,
        $body
    );
}
