<?php
declare(strict_types=1);

/**
 * Public JSON endpoint: live price breakdown for a room and date range.
 *
 * Note this is a DISPLAY quote only. actions/booking/store.php recalculates the
 * total from scratch via PricingService, so a tampered response here cannot
 * change what the guest is actually charged.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Services\PricingService;
use App\Core\Logger;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respondQuote(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$roomId   = (int) ($_GET['room_id'] ?? 0);
$checkIn  = trim((string) ($_GET['check_in'] ?? ''));
$checkOut = trim((string) ($_GET['check_out'] ?? ''));

if ($roomId < 1 || $checkIn === '' || $checkOut === '') {
    respondQuote(['success' => false, 'message' => 'Missing required parameters.'], 400);
}

if (!isValidDate($checkIn) || !isValidDate($checkOut)) {
    respondQuote(['success' => false, 'message' => 'Dates must be in YYYY-MM-DD format.'], 422);
}

if (strtotime($checkOut) <= strtotime($checkIn)) {
    respondQuote(['success' => false, 'message' => 'Check-out must be after check-in.'], 422);
}

if ((strtotime($checkOut) - strtotime($checkIn)) / 86400 > 30) {
    respondQuote(['success' => false, 'message' => 'A single stay cannot exceed 30 nights.'], 422);
}

try {
    $pricing = new PricingService();

    // No discount is accepted from the request — discounts are a staff action
    // that goes through the manager approvals workflow.
    $quote = $pricing->quote($roomId, $checkIn, $checkOut);

    respondQuote([
        'success'            => true,
        'nights'             => $quote->nights,
        'room_rate'          => $quote->room_rate,
        'formatted_rate'     => money($quote->room_rate),
        'subtotal'           => $quote->subtotal,
        'formatted_subtotal' => money($quote->subtotal),
        'service_rate'       => $quote->service_rate,
        'service_charge'     => $quote->service_charge,
        'formatted_service'  => money($quote->service_charge),
        'tax_rate'           => $quote->tax_rate,
        'tax_amount'         => $quote->tax_amount,
        'formatted_tax'      => money($quote->tax_amount),
        'total_amount'       => $quote->total_amount,
        'formatted_total'    => money($quote->total_amount),
    ]);

} catch (\Throwable $e) {
    // Previously `$e->getMessage()` was echoed straight to the browser, leaking
    // internal detail such as "Room type missing for room 14".
    Logger::error($e);
    respondQuote(['success' => false, 'message' => 'Could not calculate a price for those dates.'], 500);
}
