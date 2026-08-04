<?php
declare(strict_types=1);

namespace App\Core;

final class AuditLog
{
    public static function record(string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null): void
    {
        try {
            $userId = Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

            $sql = "INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, user_agent)
                    VALUES (:user_id, :action, :entity, :entity_id, :details, INET6_ATON(:ip), :ua)";

            Database::getInstance()->query($sql, [
                ':user_id'   => $userId,
                ':action'    => $action,
                ':entity'    => $entity,
                ':entity_id' => $entityId,
                ':details'   => $details,
                ':ip'        => $ip,
                ':ua'        => $ua
            ]);
        } catch (\Throwable $e) {
            Logger::error("Audit log failed: " . $e->getMessage());
        }
    }
}
