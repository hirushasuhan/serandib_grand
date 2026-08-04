<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function secureStart(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('HRSSESSID');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_start();

        self::enforceFingerprint();
        self::enforceTimeouts();
        self::rotatePeriodically();
    }

    /**
     * FIX: this used to also bind the session to the client's IP /24 subnet
     * (`implode('.', array_slice(explode('.', $ip), 0, 3))`). That is why
     * users were being randomly logged out mid-session with "session was
     * reset for security reasons" — on mobile data, the carrier hands a
     * phone off between cell towers and NAT gateways constantly, and each
     * handoff can change the visible IP's subnet several times an hour. Wifi
     * users behind a large corporate/campus NAT saw the same thing. None of
     * that is an attacker; it's just how mobile networking works, and the
     * check was punishing the exact audience this pass is optimising for.
     *
     * The User-Agent half is kept: it still catches the common case of a
     * stolen session cookie being replayed from a different browser/device,
     * and a UA string does not change mid-session the way an IP does. Cookie
     * flags (HttpOnly, Secure, SameSite=Lax), CSRF tokens and periodic ID
     * rotation remain the primary defences against session hijacking.
     */
    private static function enforceFingerprint(): void
    {
        $fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

        if (!isset($_SESSION['_fp'])) {
            $_SESSION['_fp'] = $fingerprint;
        } elseif (!hash_equals($_SESSION['_fp'], $fingerprint)) {
            self::destroy();
            redirect('auth/login.php?reason=security');
        }
    }

    private static function enforceTimeouts(): void
    {
        $now = time();

        if (isset($_SESSION['_last']) && ($now - $_SESSION['_last'] > SESSION_IDLE_TIMEOUT)) {
            self::destroy();
            redirect('auth/login.php?reason=idle');
        }
        if (isset($_SESSION['_start']) && ($now - $_SESSION['_start'] > SESSION_ABSOLUTE_TIMEOUT)) {
            self::destroy();
            redirect('auth/login.php?reason=expired');
        }

        $_SESSION['_last'] = $now;
        $_SESSION['_start'] ??= $now;
    }

    private static function rotatePeriodically(): void
    {
        $now = time();
        if (!isset($_SESSION['_rotated_at'])) {
            $_SESSION['_rotated_at'] = $now;
        } elseif ($now - $_SESSION['_rotated_at'] > SESSION_ROTATE_EVERY) {
            session_regenerate_id(true);
            $_SESSION['_rotated_at'] = $now;
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_rotated_at'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        @session_destroy();
    }
}
