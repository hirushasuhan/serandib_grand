<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $submittedToken = null): void
    {
        if ($submittedToken === null) {
            $submittedToken = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        if (!$submittedToken || empty($_SESSION['_token']) || !hash_equals($_SESSION['_token'], $submittedToken)) {
            Logger::warning('CSRF validation mismatch', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
            http_response_code(419);
            if (file_exists(BASE_PATH . '/403.php')) {
                require BASE_PATH . '/403.php';
            } else {
                echo '<h1>419 - CSRF Token Mismatch / Expired</h1><p>Your session has expired. Please refresh the page and try again.</p>';
            }
            exit;
        }
    }
}
