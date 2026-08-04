<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Contracts\Reportable;

class Booking extends Model implements Reportable
{
    protected static string $table = 'bookings';
    protected static array  $fillable = [
        'booking_ref', 'user_id', 'room_id', 'check_in', 'check_out', 'nights',
        'adults', 'children', 'room_rate', 'subtotal', 'discount', 'service_charge',
        'tax_amount', 'total_amount', 'status', 'special_requests', 'source',
        'created_by', 'checked_in_at', 'checked_out_at', 'cancelled_at', 'cancel_reason'
    ];

    public function guest(): ?User
    {
        return User::find((int)$this->user_id);
    }

    public function room(): ?Room
    {
        return Room::find((int)$this->room_id);
    }

    public function invoice(): ?Invoice
    {
        $sql = "SELECT * FROM invoices WHERE booking_id = :id LIMIT 1";
        $row = Database::getInstance()->query($sql, [':id' => $this->id])->fetch();
        return $row ? new Invoice($row) : null;
    }

    public function payments(): array
    {
        $sql = "SELECT * FROM payments WHERE booking_id = :id ORDER BY paid_at DESC";
        $rows = Database::getInstance()->query($sql, [':id' => $this->id])->fetchAll();
        return array_map(fn($row) => new Payment($row), $rows);
    }

    public function extraCharges(): array
    {
        $sql = "SELECT * FROM extra_charges WHERE booking_id = :id ORDER BY created_at ASC";
        $rows = Database::getInstance()->query($sql, [':id' => $this->id])->fetchAll();
        return array_map(fn($row) => new ExtraCharge($row), $rows);
    }

    public function review(): ?Review
    {
        $sql = "SELECT * FROM reviews WHERE booking_id = :id LIMIT 1";
        $row = Database::getInstance()->query($sql, [':id' => $this->id])->fetch();
        return $row ? new Review($row) : null;
    }

    public function totalPaid(): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :id";
        return (float)Database::getInstance()->query($sql, [':id' => $this->id])->fetchColumn();
    }

    public function balanceDue(): float
    {
        return max(0.00, (float)$this->total_amount - $this->totalPaid());
    }

    public static function findByRef(string $ref): ?static
    {
        $sql = "SELECT * FROM bookings WHERE booking_ref = :ref LIMIT 1";
        $row = Database::getInstance()->query($sql, [':ref' => strtoupper(trim($ref))])->fetch();
        return $row ? new static($row) : null;
    }

    public static function findForUser(int $bookingId, int $userId): ?static
    {
        $sql = "SELECT * FROM bookings WHERE id = :id AND user_id = :uid LIMIT 1";
        $row = Database::getInstance()->query($sql, [':id' => $bookingId, ':uid' => $userId])->fetch();
        return $row ? new static($row) : null;
    }

    /**
     * How many reservations does this guest currently hold that still occupy
     * inventory? Used to stop one account from hoarding every room.
     */
    public static function activeCountForUser(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM bookings
                WHERE user_id = :uid
                  AND status IN ('pending', 'confirmed', 'checked_in', 'cancel_requested')";
        return (int) Database::getInstance()->query($sql, [':uid' => $userId])->fetchColumn();
    }

    /**
     * Does this guest own this booking? Compared as integers on purpose —
     * `$booking->user_id !== $user->id` silently breaks if either side is a
     * string, so ownership checks must never rely on loose PDO typing.
     */
    public function belongsTo(int $userId): bool
    {
        return (int) $this->user_id === $userId;
    }

    public function toReportRow(): array
    {
        return [
            $this->booking_ref,
            $this->guest()?->full_name ?? 'N/A',
            $this->room()?->room_number ?? 'N/A',
            $this->check_in,
            $this->check_out,
            $this->nights,
            money($this->total_amount),
            ucfirst($this->status)
        ];
    }

    public function reportHeadings(): array
    {
        return ['Reference', 'Guest Name', 'Room No', 'Check-In', 'Check-Out', 'Nights', 'Total Amount', 'Status'];
    }

    public function rules(): array
    {
        return [
            'room_id'   => 'required|integer|exists:rooms,id',
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults'    => 'required|integer|min:1|max:10',
            'children'  => 'integer|min:0|max:6',
        ];
    }
}
