<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Models\Room;
use App\Models\User;
use App\Models\Notification;
use RuntimeException;
use PDO;

final class FrontDeskService
{
    public function checkIn(Booking $booking, ?string $nicPassport = null): bool
    {
        if ($booking->status !== BookingStatus::CONFIRMED) {
            throw new RuntimeException("Only confirmed bookings can be checked in.");
        }

        return Database::getInstance()->transaction(function (PDO $pdo) use ($booking, $nicPassport) {
            // Update guest NIC/Passport if provided
            if ($nicPassport) {
                $guest = User::find((int)$booking->user_id);
                if ($guest) {
                    $guest->update(['nic_passport' => $nicPassport]);
                }
            }

            // Update booking status
            $booking->update([
                'status'        => BookingStatus::CHECKED_IN,
                'checked_in_at' => date('Y-m-d H:i:s')
            ]);

            // Set room status to occupied
            $room = Room::find((int)$booking->room_id);
            if ($room) {
                $room->update(['status' => 'occupied']);
            }

            Notification::createForUser(
                (int)$booking->user_id,
                'Welcome to Serendib Grand!',
                "You have been checked into Room {$room->room_number}.",
                'guest/my-bookings.php'
            );

            AuditLog::record('frontdesk.checkin', 'bookings', (int)$booking->id, "Checked in to Room {$room->room_number}");
            return true;
        });
    }

    public function checkOut(Booking $booking, bool $managerOverride = false): bool
    {
        if ($booking->status !== BookingStatus::CHECKED_IN) {
            throw new RuntimeException("Only checked-in guests can be checked out.");
        }

        $balance = $booking->balanceDue();
        if ($balance > 0.00 && !$managerOverride) {
            throw new RuntimeException("Cannot check out guest with outstanding balance of " . money($balance) . ". Record full payment first.");
        }

        return Database::getInstance()->transaction(function (PDO $pdo) use ($booking, $balance, $managerOverride) {
            $booking->update([
                'status'         => BookingStatus::CHECKED_OUT,
                'checked_out_at' => date('Y-m-d H:i:s')
            ]);

            // Set room status to cleaning
            $room = Room::find((int)$booking->room_id);
            if ($room) {
                $room->update(['status' => 'cleaning']);
            }

            Notification::createForUser(
                (int)$booking->user_id,
                'Thank You for Staying With Us!',
                "Your checkout is complete. We would appreciate your feedback!",
                'guest/review.php?booking_id=' . $booking->id
            );

            $note = "Checked out from Room {$room->room_number}";
            if ($managerOverride && $balance > 0) {
                $note .= " (Manager Override with balance " . money($balance) . ")";
            }

            AuditLog::record('frontdesk.checkout', 'bookings', (int)$booking->id, $note);
            return true;
        });
    }

    public function updateRoomStatus(int $roomId, string $newStatus): bool
    {
        $room = Room::find($roomId);
        if (!$room) {
            throw new RuntimeException("Room not found.");
        }

        $allowed = ['available', 'occupied', 'cleaning', 'maintenance'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new RuntimeException("Invalid room status.");
        }

        $oldStatus = $room->status;
        $res = $room->update(['status' => $newStatus]);
        if ($res) {
            AuditLog::record('room.status_update', 'rooms', $roomId, "Status updated from {$oldStatus} to {$newStatus}");
        }
        return $res;
    }
}
