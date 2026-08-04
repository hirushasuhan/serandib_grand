<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class User extends Model
{
    protected static string $table = 'users';
    protected static array  $fillable = [
        'full_name', 'email', 'phone', 'nic_passport', 'address',
        'password_hash', 'role', 'status', 'last_login_at'
    ];

    public static function findByEmail(string $email): ?static
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $row = Database::getInstance()->query($sql, [':email' => strtolower(trim($email))])->fetch();
        return $row ? new static($row) : null;
    }

    public static function hashPassword(string $plain): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($plain, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 2,
            ]);
        }
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public function verifyPassword(string $plain): bool
    {
        if (!password_verify($plain, $this->password_hash)) {
            return false;
        }

        if (defined('PASSWORD_ARGON2ID') && password_needs_rehash($this->password_hash, PASSWORD_ARGON2ID)) {
            $this->update(['password_hash' => self::hashPassword($plain)]);
        }
        return true;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => date('Y-m-d H:i:s')]);
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|min:3|max:120',
            'email'     => 'required|email|max:160|unique:users,email,' . ($this->id ?? ''),
            'phone'     => 'required|phone',
            'role'      => 'in:guest,receptionist,manager,admin',
            'status'    => 'in:active,suspended',
        ];
    }
}
