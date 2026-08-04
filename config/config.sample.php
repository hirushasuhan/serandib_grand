<?php
declare(strict_types=1);

// ── Environment ──────────────────────────────────────────────
define('APP_ENV', 'development');   // 'development' | 'production'
define('APP_NAME', 'Serendib Grand Hotel');
define('BASE_URL', 'http://localhost/serandib_grand');

// ── Paths ────────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('STORAGE_PATH', BASE_PATH . '/storage');

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'hotel_reservation_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

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
