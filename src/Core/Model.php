<?php
declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static array  $fillable   = [];

    public array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public static function find(int|string $id): ?static
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = :id LIMIT 1";
        $row = Database::getInstance()->query($sql, [':id' => $id])->fetch();
        return $row ? new static($row) : null;
    }

    public static function all(array $order = []): array
    {
        $sql = "SELECT * FROM " . static::$table;
        if (!empty($order)) {
            $col = array_key_first($order);
            $dir = strtoupper($order[$col]) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY {$col} {$dir}";
        }
        $rows = Database::getInstance()->query($sql)->fetchAll();
        return array_map(fn($row) => new static($row), $rows);
    }

    public static function where(array $conditions, array $order = []): array
    {
        $whereClause = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            $whereClause[] = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }

        $sql = "SELECT * FROM " . static::$table . " WHERE " . implode(' AND ', $whereClause);
        if (!empty($order)) {
            $col = array_key_first($order);
            $dir = strtoupper($order[$col]) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY {$col} {$dir}";
        }

        $rows = Database::getInstance()->query($sql, $params)->fetchAll();
        return array_map(fn($row) => new static($row), $rows);
    }

    public static function create(array $data): static
    {
        $filtered = [];
        if (!empty(static::$fillable)) {
            foreach (static::$fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = $data[$field];
                }
            }
        } else {
            $filtered = $data;
        }

        $columns = implode(', ', array_keys($filtered));
        $placeholders = ':' . implode(', :', array_keys($filtered));

        $sql = "INSERT INTO " . static::$table . " ({$columns}) VALUES ({$placeholders})";
        
        $params = [];
        foreach ($filtered as $key => $val) {
            $params[":{$key}"] = $val;
        }

        $db = Database::getInstance();
        $db->query($sql, $params);
        $insertId = $db->lastInsertId();

        return static::find((int)$insertId);
    }

    public function update(array $data): bool
    {
        $filtered = [];
        if (!empty(static::$fillable)) {
            foreach (static::$fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = $data[$field];
                }
            }
        } else {
            $filtered = $data;
        }

        if (empty($filtered)) return false;

        $setClause = [];
        $params = [':id' => $this->attributes[static::$primaryKey]];
        foreach ($filtered as $key => $val) {
            $setClause[] = "{$key} = :{$key}";
            $params[":{$key}"] = $val;
            $this->attributes[$key] = $val;
        }

        $sql = "UPDATE " . static::$table . " SET " . implode(', ', $setClause) . " WHERE " . static::$primaryKey . " = :id";
        Database::getInstance()->query($sql, $params);
        return true;
    }

    public function delete(): bool
    {
        $sql = "DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = :id";
        Database::getInstance()->query($sql, [':id' => $this->attributes[static::$primaryKey]]);
        return true;
    }

    abstract public function rules(): array;
}
