<?php
declare(strict_types=1);

/**
 * Creates a new online reservation for the logged-in guest.
 *
 * SECURITY: this handler builds its own trusted payload. It never forwards the
 * raw $_POST array into BookingService, because $_POST may contain fields such
 * as `discount`, `total_amount`, `status` or `user_id` that a guest must not be
 * able to influence. All money is recalculated server-side by PricingService.
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Core\Csrf;
use App\Core\Auth;
use App\Core\Validator;
use App\Core\Session;
use App\Core\Flash;
use App\Core\Logger;
use App\Models\Room;
use App\Services\BookingService;
use App\Exceptions\BookingConflictException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Csrf::verify();
Auth::requireRole(['guest']);

$user = Auth::user();

$v = Validator::make($_POST, [
    'room_id'          => 'required|integer|exists:rooms,id',
    'check_in'         => 'required|date_format:Y-m-d|after_or_equal:today|before_days:365',
    'check_out'        => 'required|date_format:Y-m-d|after:check_in|max_nights:30',
    'adults'           => 'required|integer|min:1|max:10',
    'children'         => 'nullable|integer|min:0|max:6',
    'special_requests' => 'nullable|max:500',
]);

if ($v->fails()) {
    Session::put('errors', $v->errors());
    Session::put('_old', $_POST);
    redirect('guest/booking-form.php');
}

$clean = $v->validated();

// ── Business rule: occupancy must fit the room type's declared capacity ──
$room = Room::find((int) $clean['room_id']);
$type = $room?->roomType();

if (!$room || !$type || (int) $room->is_active !== 1) {
    Flash::error('That room is no longer available for booking.');
    redirect('guest/booking-form.php');
}

$adults   = (int) $clean['adults'];
$children = (int) ($clean['children'] ?? 0);

if ($adults > (int) $type->max_adults || $children > (int) $type->max_children) {
    Session::put('errors', [
        'adults' => "This room type allows a maximum of {$type->max_adults} adults and {$type->max_children} children.",
    ]);
    Session::put('_old', $_POST);
    redirect('guest/booking-form.php');
}

// ── Business rule: cap concurrent active reservations per guest (anti-inventory-hoarding) ──
if (\App\Models\Booking::activeCountForUser((int) $user->id) >= 3) {
    Flash::error('You already have 3 active reservations. Please complete or cancel one before booking again.');
    redirect('guest/my-bookings.php');
}

try {
    $bookingService = new BookingService();

    // Only these five trusted fields are passed on. `discount` is deliberately
    // absent so a tampered request can never reduce the price.
    $booking = $bookingService->create([
        'room_id'          => (int) $clean['room_id'],
        'check_in'         => $clean['check_in'],
        'check_out'        => $clean['check_out'],
        'adults'           => $adults,
        'children'         => $children,
        'special_requests' => $clean['special_requests'] ?? null,
    ], $user, 'online');

    Flash::success("Booking placed successfully! Your reference is {$booking->booking_ref}.");
    redirect('guest/my-bookings.php');

} catch (BookingConflictException $e) {
    // Safe to show: these messages are written by us, not by PHP or MySQL.
    Flash::error($e->getMessage());
    redirect('guest/booking-form.php');

} catch (\Throwable $e) {
    // Never expose $e->getMessage() — it can contain SQL, table names and paths.
    Logger::error($e);
    Flash::error('We could not complete your booking right now. Please try again.');
    redirect('guest/booking-form.php');
}
