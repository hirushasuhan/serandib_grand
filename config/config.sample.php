<?php
declare(strict_types=1);

// Helper to read env from getenv, $_ENV or $_SERVER (crucial for WCGI/CGI in Wasmer)
$getEnv = static function (string $key, ?string $default = null): ?string {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    if (isset($_ENV[$key]) && (string) $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && (string) $_SERVER[$key] !== '') {
        return (string) $_SERVER[$key];
    }
    return $default;
};

// ── Environment ──────────────────────────────────────────────
define('APP_ENV', $getEnv('APP_ENV', 'development'));   // 'development' | 'production'
define('APP_NAME', $getEnv('APP_NAME', 'Serendib Grand Hotel'));

$envBaseUrl = $getEnv('BASE_URL');
if ($envBaseUrl !== null && $envBaseUrl !== '') {
    define('BASE_URL', rtrim($envBaseUrl, '/'));
} elseif (isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https' : 'http';
    $subDir = '';
    if (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], '/serandib_grand/')) {
        $subDir = '/serandib_grand';
    }
    define('BASE_URL', $scheme . '://' . $_SERVER['HTTP_HOST'] . $subDir);
} else {
    define('BASE_URL', 'http://localhost/serandib_grand');
}

// ── Paths ────────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('STORAGE_PATH', BASE_PATH . '/storage');

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', $getEnv('DB_HOST', '127.0.0.1'));
define('DB_PORT', $getEnv('DB_PORT', '3306'));
define('DB_NAME', $getEnv('DB_NAME', 'hotel_reservation_db'));
define('DB_USER', $getEnv('DB_USER', 'root'));
define('DB_PASS', $getEnv('DB_PASS', $getEnv('DB_PASSWORD', '') ?? ''));
define('DB_CHARSET', $getEnv('DB_CHARSET', 'utf8mb4'));
define('DB_SSL', filter_var($getEnv('DB_SSL', 'false'), FILTER_VALIDATE_BOOLEAN));

// ── Security ─────────────────────────────────────────────────
define('SESSION_IDLE_TIMEOUT', 1800);   // 30 minutes
define('SESSION_ABSOLUTE_TIMEOUT', 28800);  // 8 hours
define('SESSION_ROTATE_EVERY', 900);    // 15 minutes
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900);    // 15 minutes
define('PASSWORD_MIN_LENGTH', 8);
define('RESET_TOKEN_TTL', 1800);   // 30 minutes
define('UPLOAD_MAX_BYTES', 2097152);// 2 MB

// ── Locale ───────────────────────────────────────────────────
define('APP_TIMEZONE', 'Asia/Colombo');
define('CURRENCY', 'LKR');
date_default_timezone_set(APP_TIMEZONE);
