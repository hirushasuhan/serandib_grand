<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Traits\SoftDeletes;

class RoomType extends Model
{
    use SoftDeletes;

    protected static string $table = 'room_types';
    protected static array  $fillable = [
        'name', 'slug', 'description', 'base_price', 'max_adults',
        'max_children', 'bed_type', 'size_sqft', 'cover_image', 'is_active'
    ];

    public static function findBySlug(string $slug): ?static
    {
        $sql = "SELECT * FROM room_types WHERE slug = :slug AND is_active = 1 LIMIT 1";
        $row = Database::getInstance()->query($sql, [':slug' => $slug])->fetch();
        return $row ? new static($row) : null;
    }

    public function amenities(): array
    {
        $sql = "SELECT a.* FROM amenities a
                JOIN room_type_amenity rta ON rta.amenity_id = a.id
                WHERE rta.room_type_id = :type_id ORDER BY a.name";
        $rows = Database::getInstance()->query($sql, [':type_id' => $this->id])->fetchAll();
        return array_map(fn($row) => new Amenity($row), $rows);
    }

    public function galleryImages(): array
    {
        $sql = "SELECT * FROM room_type_images WHERE room_type_id = :type_id ORDER BY sort_order, id";
        return Database::getInstance()->query($sql, [':type_id' => $this->id])->fetchAll();
    }

    public function syncAmenities(array $amenityIds): void
    {
        $db = Database::getInstance();
        $db->query("DELETE FROM room_type_amenity WHERE room_type_id = :type_id", [':type_id' => $this->id]);
        foreach ($amenityIds as $amenityId) {
            $db->query("INSERT INTO room_type_amenity (room_type_id, amenity_id) VALUES (:t, :a)", [
                ':t' => $this->id,
                ':a' => (int)$amenityId
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|min:3|max:80',
            'base_price' => 'required|numeric|min:0',
            'max_adults' => 'required|integer|min:1|max:10',
            'max_children' => 'integer|min:0|max:6',
            'bed_type'   => 'required|in:single,double,twin,queen,king',
        ];
    }
}
