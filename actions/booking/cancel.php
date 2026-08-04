<?php
declare(strict_types=1);

/**
 * Cancels a booking, or raises a cancellation request for manager approval.
 *
 * The rules enforced here are the ones a tampered request would otherwise skip:
 *   1. A guest may only touch their OWN booking (ownership compared as ints).
 *   2. Only states that are still cancellable may be cancelled — the previous
 *      version pushed ANY non-pending booking to `cancel_requested`, so an
 *      already checked-out or cancelled stay could be re-opened.
 *   3. Duplicate approval rows are not created if the button is clicked twice.
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Database;
use App\Core\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatus;
use App\Services\BookingService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Csrf::verify();
Auth::requireRole(['guest', 'receptionist', 'manager', 'admin']);

$user      = Auth::user();
$isGuest   = $user->role === 'guest';
$returnTo  = $isGuest ? 'guest/my-bookings.php' : 'staff/bookings.php';

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$booking   = $bookingId > 0 ? Booking::find($bookingId) : null;

// A guest asking about someone else's booking gets the same answer as for a
// booking that does not exist — never confirm that the record is real.
if (!$booking || ($isGuest && !$booking->belongsTo((int) $user->id))) {
    if ($isGuest) {
        http_response_code(404);
        require BASE_PATH . '/404.php';
        exit;
    }
    Flash::error('Booking record not found.');
    redirect($returnTo);
}

$status = (string) $booking->status;

// ── Rule 2: is this booking still cancellable at all? ──
$cancellable = [
    BookingStatus::PENDING,
    BookingStatus::CONFIRMED,
];

if (!in_array($status, $cancellable, true)) {
    AuditLog::record(
        'booking.cancel_rejected',
        'bookings',
        (int) $booking->id,
        "Attempted cancel from non-cancellable status '{$status}'"
    );
    Flash::error('This booking can no longer be cancelled (current status: '
        . ucfirst(str_replace('_', ' ', $status)) . ').');
    redirect($returnTo);
}

try {
    $bookingService = new BookingService();

    if ($status === BookingStatus::PENDING) {
        // Not yet confirmed: the guest may withdraw it outright.
        $bookingService->transition($booking, BookingStatus::CANCELLED, $isGuest
            ? 'Cancelled by guest while pending'
            : "Cancelled by {$user->role}");
        Flash::success("Booking {$booking->booking_ref} has been cancelled.");

    } elseif (!$isGuest) {
        // Staff can cancel a confirmed booking directly.
        $bookingService->transition($booking, BookingStatus::CANCELLED,
            "Cancelled at front desk by {$user->full_name}");
        Flash::success("Booking {$booking->booking_ref} has been cancelled.");

    } else {
        // Confirmed guest booking: needs a manager to approve the refund, so
        // route it through the approvals queue inside one transaction.
        Database::getInstance()->transaction(function ($pdo) use ($booking, $user) {

            // Do not stack duplicate requests if the guest double-clicks.
            $dupe = $pdo->prepare(
                "SELECT 1 FROM approvals
                 WHERE booking_id = :b AND type = 'cancellation' AND status = 'pending'
                 LIMIT 1"
            );
            $dupe->execute([':b' => $booking->id]);

            if (!$dupe->fetchColumn()) {
                $insert = $pdo->prepare(
                    "INSERT INTO approvals (booking_id, type, requested_by, status, reason)
                     VALUES (:b, 'cancellation', :u, 'pending', 'Guest requested cancellation')"
                );
                $insert->execute([':b' => $booking->id, ':u' => $user->id]);
            }

            $upd = $pdo->prepare("UPDATE bookings SET status = :st WHERE id = :id");
            $upd->execute([':st' => BookingStatus::CANCEL_REQUESTED, ':id' => $booking->id]);
        });

        AuditLog::record('booking.cancel_requested', 'bookings', (int) $booking->id);
        Flash::info('Your cancellation request has been submitted for manager approval.');
    }

} catch (\Throwable $e) {
    Logger::error($e);
    Flash::error('We could not process that cancellation. Please try again.');
}

redirect($returnTo);
