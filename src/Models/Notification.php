<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Notification extends Model
{
    protected static string $table = 'notifications';
    protected static array  $fillable = [
        'user_id', 'title', 'message', 'link', 'is_read'
    ];

    public static function createForUser(int $userId, string $title, string $message, ?string $link = null): self
    {
        return self::create([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'is_read' => 0
        ]);
    }

    public static function unreadCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0";
        return (int)Database::getInstance()->query($sql, [':uid' => $userId])->fetchColumn();
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'title'   => 'required|max:120',
            'message' => 'required|max:255'
        ];
    }
}
