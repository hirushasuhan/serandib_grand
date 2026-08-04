<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\User;
use App\Models\Notification;
use App\Exceptions\BookingConflictException;
use PDO;
use RuntimeException;

final class BookingService
{
    public function __construct(
        private AvailabilityService $availability = new AvailabilityService(),
        private PricingService      $pricing = new PricingService()
    ) {}

    public function create(array $data, User $guest, string $source = 'online', ?int $createdBy = null): Booking
    {
        return Database::getInstance()->transaction(function (PDO $pdo) use ($data, $guest, $source, $createdBy) {

            // 1. Lock candidate room row using FOR UPDATE
            $stmt = $pdo->prepare("SELECT id FROM rooms WHERE id = :id AND is_active = 1 FOR UPDATE");
            $stmt->execute([':id' => $data['room_id']]);
            if (!$stmt->fetch()) {
                throw new BookingConflictException('Selected room was not found.');
            }

            // 2. Re-check availability INSIDE transaction lock
            $clash = $pdo->prepare("
                SELECT COUNT(*) FROM bookings
                WHERE room_id = :room_id
                  AND status IN ('pending','confirmed','checked_in')
                  AND check_in < :check_out AND check_out > :check_in");
            $clash->execute([
                ':room_id'   => $data['room_id'],
                ':check_in'  => $data['check_in'],
                ':check_out' => $data['check_out'],
            ]);

            if ((int)$clash->fetchColumn() > 0) {
                throw new BookingConflictException('Sorry, that room has just been reserved for your selected dates. Please select another room.');
            }

            // 3. Calculate pricing server-side
            $quote = $this->pricing->quote(
                (int)$data['room_id'],
                $data['check_in'],
                $data['check_out'],
                (float)($data['discount'] ?? 0.00)
            );

            // 4. Generate a unique Booking Reference.
            //    booking_ref is UNIQUE in the schema, and 4 hex chars only gives
            //    65 536 combinations per day, so we retry rather than let a rare
            //    duplicate-key error kill a legitimate booking.
            $ref = null;
            $refCheck = $pdo->prepare("SELECT 1 FROM bookings WHERE booking_ref = :ref LIMIT 1");

            for ($attempt = 0; $attempt < 8; $attempt++) {
                $candidate = 'HRS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
                $refCheck->execute([':ref' => $candidate]);
                if (!$refCheck->fetchColumn()) {
                    $ref = $candidate;
                    break;
                }
            }

            if ($ref === null) {
                throw new BookingConflictException('Could not allocate a booking reference. Please try again.');
            }

            $status = ($source === 'walk_in') ? BookingStatus::CONFIRMED : BookingStatus::PENDING;

            $insertStmt = $pdo->prepare("
                INSERT INTO bookings (
                    booking_ref, user_id, room_id, check_in, check_out, nights,
                    adults, children, room_rate, subtotal, discount, service_charge,
                    tax_amount, total_amount, status, special_requests, source, created_by
                ) VALUES (
                    :ref, :user_id, :room_id, :check_in, :check_out, :nights,
                    :adults, :children, :room_rate, :subtotal, :discount, :service_charge,
                    :tax_amount, :total_amount, :status, :special_requests, :source, :created_by
                )
            ");

            $insertStmt->execute([
                ':ref'              => $ref,
                ':user_id'          => $guest->id,
                ':room_id'          => $data['room_id'],
                ':check_in'         => $data['check_in'],
                ':check_out'        => $data['check_out'],
                ':nights'           => $quote->nights,
                ':adults'           => $data['adults'] ?? 1,
                ':children'         => $data['children'] ?? 0,
                ':room_rate'        => $quote->room_rate,
                ':subtotal'         => $quote->subtotal,
                ':discount'         => $quote->discount,
                ':service_charge'   => $quote->service_charge,
                ':tax_amount'       => $quote->tax_amount,
                ':total_amount'     => $quote->total_amount,
                ':status'           => $status,
                ':special_requests' => $data['special_requests'] ?? null,
                ':source'           => $source,
                ':created_by'       => $createdBy
            ]);

            $bookingId = (int)$pdo->lastInsertId();
            $booking = Booking::find($bookingId);

            Notification::createForUser(
                $guest->id,
                'Booking Created',
                "Your reservation {$ref} has been placed successfully.",
                'guest/my-bookings.php'
            );

            AuditLog::record('booking.created', 'bookings', $bookingId, "Reference: {$ref}");

            return $booking;
        });
    }

    public function transition(Booking $booking, string $newStatus, ?string $reason = null): bool
    {
        if (!BookingStatus::canTransition($booking->status, $newStatus)) {
            throw new RuntimeException("Cannot transition booking from '{$booking->status}' to '{$newStatus}'.");
        }

        $updateData = ['status' => $newStatus];
        if ($newStatus === BookingStatus::CANCELLED || $newStatus === BookingStatus::REJECTED) {
            $updateData['cancelled_at']  = date('Y-m-d H:i:s');
            $updateData['cancel_reason'] = $reason;
        }

        $result = $booking->update($updateData);

        if ($result) {
            Notification::createForUser(
                (int)$booking->user_id,
                'Booking Status Updated',
                "Your booking {$booking->booking_ref} status is now " . ucfirst(str_replace('_', ' ', $newStatus)) . ".",
                'guest/my-bookings.php'
            );
            AuditLog::record('booking.status_change', 'bookings', (int)$booking->id, "Status changed to {$newStatus}");
        }

        return $result;
    }
}
