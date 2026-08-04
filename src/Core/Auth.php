<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static ?User $cachedUser = null;

    /** Set when attempt() fails because of throttling, so the UI can say so. */
    private static bool $lockedOut = false;

    public static function wasLockedOut(): bool
    {
        return self::$lockedOut;
    }

    public static function attempt(string $email, string $password): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        self::$lockedOut = false;

        if (RateLimiter::tooManyAttempts($email, $ip)) {
            self::$lockedOut = true;
            AuditLog::record('auth.throttled', 'users', null, "Throttled login for {$email}");
            return false;
        }

        $user = User::findByEmail($email);

        // Constant-ish response time regardless of whether the email exists, plus
        // a small random delay, so responses cannot be timed to enumerate accounts.
        if (!$user || $user->status !== 'active' || !$user->verifyPassword($password)) {
            RateLimiter::hit($email, $ip, false);
            usleep(random_int(150000, 350000));
            return false;
        }

        RateLimiter::hit($email, $ip, true);

        // Login success: establish session.
        // regenerate() defeats session fixation; rotating the CSRF token too means
        // a token harvested before login cannot be replayed against the new session.
        Session::regenerate();
        unset($_SESSION['_token']);
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['role']    = $user->role;

        $user->updateLastLogin();
        AuditLog::record('auth.login', 'users', $user->id, "User {$user->email} logged in");

        self::$cachedUser = $user;
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']) && self::user() !== null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function user(): ?User
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $user = User::find((int)$_SESSION['user_id']);
        if (!$user || $user->status !== 'active') {
            self::logout();
            return null;
        }

        self::$cachedUser = $user;
        return $user;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user ? $user->role : null;
    }

    public static function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            AuditLog::record('auth.logout', 'users', (int)$_SESSION['user_id']);
        }
        Session::destroy();
        self::$cachedUser = null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Session::put('intended', $_SERVER['REQUEST_URI'] ?? '');
            Flash::warning('Please log in to access this page.');
            redirect('auth/login.php');
        }
    }

    public static function requireRole(array $allowedRoles): void
    {
        self::requireLogin();
        $user = self::user();

        if (!$user || !in_array($user->role, $allowedRoles, true)) {
            AuditLog::record('authz.denied', 'page', null, $_SERVER['REQUEST_URI'] ?? '');
            http_response_code(403);
            if (file_exists(BASE_PATH . '/403.php')) {
                require BASE_PATH . '/403.php';
            } else {
                echo '<h1>403 - Forbidden</h1><p>You do not have permission to view this page.</p>';
            }
            exit;
        }
    }

    /**
     * Encodes the permission matrix from §3.1.
     */
    public static function can(string $ability): bool
    {
        $user = self::user();
        if (!$user) return false;

        $role = $user->role;

        return match ($ability) {
            'create_own_booking', 'edit_own_booking', 'cancel_own_booking', 'submit_review' => $role === 'guest',
            'create_walkin', 'confirm_booking', 'checkin_guest', 'checkout_guest', 'change_room_status', 'record_payment', 'add_extra_charge', 'print_invoice' => in_array($role, ['receptionist', 'manager', 'admin'], true),
            'apply_high_discount', 'approve_cancellation', 'moderate_reviews', 'view_reports' => in_array($role, ['manager', 'admin'], true),
            'manage_staff', 'crud_room_types', 'crud_rooms', 'edit_settings', 'view_audit_log' => $role === 'admin' || ($ability === 'view_audit_log' && $role === 'manager'),
            default => false,
        };
    }
}
