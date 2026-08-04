<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Review extends Model
{
    protected static string $table = 'reviews';
    protected static array  $fillable = [
        'booking_id', 'user_id', 'rating', 'comment', 'status', 'moderated_by', 'moderated_at'
    ];

    public function user(): ?User
    {
        return User::find((int)$this->user_id);
    }

    public function booking(): ?Booking
    {
        return Booking::find((int)$this->booking_id);
    }

    public static function approvedReviews(int $limit = 6): array
    {
        $db = \App\Core\Database::getInstance();
        $sql = "SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.status = 'approved' ORDER BY r.created_at DESC LIMIT " . (int)$limit;
        return $db->query($sql)->fetchAll();
    }

    public function rules(): array
    {
        return [
            'booking_id' => 'required|integer|exists:bookings,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'required|min:10|max:1000'
        ];
    }
}
