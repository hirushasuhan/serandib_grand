<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Traits\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    protected static string $table = 'rooms';
    protected static array  $fillable = [
        'room_number', 'room_type_id', 'floor', 'status', 'notes', 'is_active'
    ];

    public function roomType(): ?RoomType
    {
        return RoomType::find((int)$this->room_type_id);
    }

    public static function findByNumber(string $number): ?static
    {
        $sql = "SELECT * FROM rooms WHERE room_number = :num LIMIT 1";
        $row = Database::getInstance()->query($sql, [':num' => $number])->fetch();
        return $row ? new static($row) : null;
    }

    public function rules(): array
    {
        return [
            'room_number'  => 'required|min:1|max:10|unique:rooms,room_number,' . ($this->id ?? ''),
            'room_type_id' => 'required|integer|exists:room_types,id',
            'floor'        => 'required|integer|min:0|max:50',
            'status'       => 'required|in:available,occupied,cleaning,maintenance',
        ];
    }
}
