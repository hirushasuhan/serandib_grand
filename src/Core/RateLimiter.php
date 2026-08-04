<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Login / registration throttling.
 *
 * Per-account and per-IP counters are kept SEPARATE on purpose:
 *
 *  - The previous version counted `email = ? OR ip_address = ?`, so on a shared
 *    campus NAT one student's typos locked out everybody else — a self-inflicted
 *    denial of service in the middle of a demo.
 *  - It also cleared with `email = ? OR ip_address = ?` on success, so anybody
 *    holding one valid account could wipe the failure counter for every other
 *    account on that IP and keep brute-forcing.
 *
 * So: a strict threshold per email, a looser one per IP, and clearing is
 * scoped to the single account that just authenticated.
 */
final class RateLimiter
{
    /** Per-IP allowance is higher because many people legitimately share one IP. */
    private const IP_MULTIPLIER = 4;

    /** New accounts allowed from one IP per hour. */
    private const MAX_REGISTRATIONS_PER_HOUR = 5;

    /** Sentinel email used to count registrations without an extra table. */
    private const REGISTER_MARKER = '__register__';

    public static function tooManyAttempts(string $email, string $ip): bool
    {
        return self::failuresForEmail($email) >= LOGIN_MAX_ATTEMPTS
            || self::failuresForIp($ip) >= (LOGIN_MAX_ATTEMPTS * self::IP_MULTIPLIER);
    }

    public static function failuresForEmail(string $email): int
    {
        // LOGIN_LOCKOUT_SECONDS is an application constant, never user input, so
        // inlining it is safe. It cannot be a bound parameter because MySQL will
        // not accept a string placeholder inside an INTERVAL expression reliably.
        $sql = "SELECT COUNT(*) FROM login_attempts
                WHERE email = :email
                  AND successful = 0
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL " . (int) LOGIN_LOCKOUT_SECONDS . " SECOND)";

        return (int) Database::getInstance()
            ->query($sql, [':email' => self::normalise($email)])
            ->fetchColumn();
    }

    public static function failuresForIp(string $ip): int
    {
        $sql = "SELECT COUNT(*) FROM login_attempts
                WHERE ip_address = INET6_ATON(:ip)
                  AND email <> '" . self::REGISTER_MARKER . "'
                  AND successful = 0
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL " . (int) LOGIN_LOCKOUT_SECONDS . " SECOND)";

        return (int) Database::getInstance()
            ->query($sql, [':ip' => $ip])
            ->fetchColumn();
    }

    /**
     * Seconds until this account may try again — lets the UI show a real
     * countdown instead of a misleading "invalid password" message.
     */
    public static function secondsUntilRetry(string $email): int
    {
        $sql = "SELECT TIMESTAMPDIFF(SECOND, NOW(),
                       DATE_ADD(MIN(attempted_at), INTERVAL " . (int) LOGIN_LOCKOUT_SECONDS . " SECOND))
                FROM login_attempts
                WHERE email = :email
                  AND successful = 0
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL " . (int) LOGIN_LOCKOUT_SECONDS . " SECOND)";

        $seconds = Database::getInstance()
            ->query($sql, [':email' => self::normalise($email)])
            ->fetchColumn();

        return max(0, (int) $seconds);
    }

    public static function hit(string $email, string $ip, bool $successful = false): void
    {
        $sql = "INSERT INTO login_attempts (email, ip_address, successful)
                VALUES (:email, INET6_ATON(:ip), :successful)";

        Database::getInstance()->query($sql, [
            ':email'      => self::normalise($email),
            ':ip'         => $ip,
            ':successful' => $successful ? 1 : 0,
        ]);

        if ($successful) {
            self::clear($email, $ip);
        }
    }

    /**
     * Clear the counter for THIS account only (AND, not OR).
     * A successful login must never unlock other accounts sharing the IP.
     */
    public static function clear(string $email, string $ip = ''): void
    {
        Database::getInstance()->query(
            "DELETE FROM login_attempts WHERE email = :email AND successful = 0",
            [':email' => self::normalise($email)]
        );
    }

    /**
     * Throttles account creation so a script cannot mass-register guests.
     * Records the attempt as a side effect when it is allowed.
     */
    public static function tooManyRegistrations(string $ip): bool
    {
        $sql = "SELECT COUNT(*) FROM login_attempts
                WHERE email = :marker
                  AND ip_address = INET6_ATON(:ip)
                  AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";

        $count = (int) Database::getInstance()
            ->query($sql, [':marker' => self::REGISTER_MARKER, ':ip' => $ip])
            ->fetchColumn();

        if ($count >= self::MAX_REGISTRATIONS_PER_HOUR) {
            return true;
        }

        Database::getInstance()->query(
            "INSERT INTO login_attempts (email, ip_address, successful)
             VALUES (:marker, INET6_ATON(:ip), 1)",
            [':marker' => self::REGISTER_MARKER, ':ip' => $ip]
        );

        return false;
    }

    /** Housekeeping so the table does not grow without bound. */
    public static function purgeOld(): void
    {
        Database::getInstance()->query(
            "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    private static function normalise(string $email): string
    {
        return strtolower(trim($email));
    }
}
