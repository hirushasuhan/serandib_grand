<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Setting extends Model
{
    protected static string $table = 'settings';
    protected static string $primaryKey = 'setting_key';
    protected static array  $fillable = ['setting_key', 'setting_value', 'setting_group'];

    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $sql = "SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1";
            $val = Database::getInstance()->query($sql, [':k' => $key])->fetchColumn();

            if ($val !== false) {
                self::$cache[$key] = $val;
                return $val;
            }
        } catch (\Throwable) {
            return $default;
        }

        return $default;
    }

    public static function set(string $key, string $value, string $group = 'general'): void
    {
        $sql = "INSERT INTO settings (setting_key, setting_value, setting_group)
                VALUES (:k, :v, :g)
                ON DUPLICATE KEY UPDATE setting_value = :v2, setting_group = :g2";

        Database::getInstance()->query($sql, [
            ':k'  => $key,
            ':v'  => $value,
            ':g'  => $group,
            ':v2' => $value,
            ':g2' => $group
        ]);

        self::$cache[$key] = $value;
    }

    public function rules(): array
    {
        return [
            'setting_key'   => 'required|max:60',
            'setting_value' => 'required'
        ];
    }
}
