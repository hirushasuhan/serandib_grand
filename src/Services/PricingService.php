<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use DateTime;
use RuntimeException;

final class PricingService
{
    /** Hard ceiling on a single stay, mirrors the max_nights validation rule. */
    private const MAX_NIGHTS = 30;

    public function quote(int $roomId, string $checkIn, string $checkOut, float $discount = 0.00): object
    {
        $room = Room::find($roomId);
        if (!$room) {
            throw new RuntimeException("Room not found for pricing calculation.");
        }

        $type = $room->roomType();
        if (!$type) {
            throw new RuntimeException("Room type missing for room {$roomId}.");
        }

        // Reject malformed dates here too. Without this, `new DateTime('abc')`
        // throws an uncaught exception and the user sees a 500 page.
        $d1 = DateTime::createFromFormat('Y-m-d', $checkIn);
        $d2 = DateTime::createFromFormat('Y-m-d', $checkOut);

        if (!$d1 || !$d2 || $d1->format('Y-m-d') !== $checkIn || $d2->format('Y-m-d') !== $checkOut) {
            throw new RuntimeException('Invalid date supplied to pricing calculation.');
        }
        if ($d2 <= $d1) {
            throw new RuntimeException('Check-out date must be after the check-in date.');
        }

        $nights = (int) $d1->diff($d2)->days;
        if ($nights > self::MAX_NIGHTS) {
            throw new RuntimeException('A single stay cannot exceed ' . self::MAX_NIGHTS . ' nights.');
        }

        $roomRate = (float)$type->base_price;
        $subtotal = round($roomRate * $nights, 2);

        // SECURITY: clamp the discount to [0, subtotal]. Without the upper bound a
        // tampered `discount` value would make taxableAmount 0 and hand out a free stay.
        $discountAmt = min(max(0.00, $discount), $subtotal);
        $taxRate     = (float)Setting::get('tax_rate', '8.00');
        $serviceRate = (float)Setting::get('service_charge', '10.00');

        $taxableAmount = max(0.00, $subtotal - $discountAmt);
        $serviceCharge = round(($taxableAmount * $serviceRate) / 100, 2);
        $taxAmount     = round(($taxableAmount * $taxRate) / 100, 2);

        $totalAmount   = round($taxableAmount + $serviceCharge + $taxAmount, 2);

        return (object)[
            'nights'         => $nights,
            'room_rate'      => $roomRate,
            'subtotal'       => $subtotal,
            'discount'       => $discountAmt,
            'service_rate'   => $serviceRate,
            'service_charge' => $serviceCharge,
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $totalAmount
        ];
    }
}
