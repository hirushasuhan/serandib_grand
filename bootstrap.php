<?php
declare(strict_types=1);

// Load Configuration
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
} else {
    require_once __DIR__ . '/config/config.sample.php';
}

// Load Autoloader & Helpers
require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/src/Helpers/functions.php';

// Configure Error Handling
if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (!is_dir(STORAGE_PATH . '/logs')) {
        @mkdir(STORAGE_PATH . '/logs', 0755, true);
    }
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Uncaught Exception Handler
set_exception_handler(function (Throwable $e): void {
    $errStr = "[Uncaught Exception] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    \App\Core\Logger::error($e);
    @file_put_contents('php://stderr', $errStr . PHP_EOL);
    $GLOBALS['last_error'] = $errStr;
    http_response_code(500);
    if (file_exists(__DIR__ . '/500.php')) {
        require __DIR__ . '/500.php';
    } else {
        echo '<h1>500 - Internal Server Error</h1><p>' . htmlspecialchars($errStr) . '</p>';
    }
    exit;
});

// Secure Session Initialization
\App\Core\Session::secureStart();

/**
 * Content-Security-Policy with a per-request nonce.
 *
 * This is sent from PHP rather than .htaccess because the nonce must change on
 * every response — Apache cannot generate one. The nonce lets our single inline
 * <script> (the theme-flash preventer in includes/header.php) run while any
 * script an attacker manages to inject is still blocked.
 *
 * style-src allows 'unsafe-inline' because the pages use inline style attributes
 * for layout. That is an accepted trade-off: inline styles cannot execute code.
 */
if (!headers_sent()) {
    define('CSP_NONCE', base64_encode(random_bytes(16)));

    // Universal HTTP Security Headers (active on Wasmer Edge, Apache, Nginx)
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    // Enforce HSTS when serving over HTTPS or behind Wasmer edge SSL proxy
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Strip sensitive runtime headers
    if (function_exists('header_remove')) {
        header_remove('X-Powered-By');
    }

    header("Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'nonce-" . CSP_NONCE . "'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'");
} else {
    define('CSP_NONCE', '');
}
