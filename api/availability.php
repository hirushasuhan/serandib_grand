<?php
declare(strict_types=1);

/**
 * Public JSON endpoint: how many rooms of a type are free for a date range?
 *
 * Deliberately anonymous so the home page and rooms page can show live counts
 * before login. Because of that it returns a COUNT only.
 *
 * The previous version returned the full `rooms` array — internal room IDs, room
 * numbers, floor numbers and housekeeping status — to anyone on the internet.
 * That is a map of the hotel's inventory and has no business being public.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Services\AvailabilityService;
use App\Core\Auth;
use App\Core\Logger;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** Emit a JSON response and stop. */
function respondAvailability(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$typeId   = (int) ($_GET['type_id'] ?? 0);
$checkIn  = trim((string) ($_GET['check_in'] ?? ''));
$checkOut = trim((string) ($_GET['check_out'] ?? ''));

if ($typeId < 1 || $checkIn === '' || $checkOut === '') {
    respondAvailability(['success' => false, 'message' => 'Missing parameters.'], 400);
}

if (!isValidDate($checkIn) || !isValidDate($checkOut)) {
    respondAvailability(['success' => false, 'message' => 'Dates must be in YYYY-MM-DD format.'], 422);
}

if (strtotime($checkOut) <= strtotime($checkIn)) {
    respondAvailability(['success' => false, 'message' => 'Check-out must be after check-in.'], 422);
}

if (strtotime($checkIn) < strtotime('today')) {
    respondAvailability(['success' => false, 'message' => 'Check-in cannot be in the past.'], 422);
}

// Cap the window so nobody can request a 10-year range and hammer the database.
if ((strtotime($checkOut) - strtotime($checkIn)) / 86400 > 30) {
    respondAvailability(['success' => false, 'message' => 'A single stay cannot exceed 30 nights.'], 422);
}

try {
    $service = new AvailabilityService();
    $rooms   = $service->availableRooms($typeId, $checkIn, $checkOut);

    $payload = [
        'success'   => true,
        'count'     => count($rooms),
        'available' => count($rooms) > 0,
    ];

    // Only signed-in staff get the actual room list — they need it for the
    // front-desk allocation screens.
    if (Auth::check() && in_array(Auth::role(), ['receptionist', 'manager', 'admin'], true)) {
        $payload['rooms'] = $rooms;
    }

    respondAvailability($payload);

} catch (\Throwable $e) {
    // Never return $e->getMessage(): it can contain SQL and file paths.
    Logger::error($e);
    respondAvailability(['success' => false, 'message' => 'Could not check availability right now.'], 500);
}
