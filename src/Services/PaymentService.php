<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ExtraCharge;
use App\Models\Notification;
use RuntimeException;

final class PaymentService
{
    public function recordPayment(int $bookingId, float $amount, string $method, int $staffUserId, ?string $refNo = null, ?string $note = null): Payment
    {
        $booking = Booking::find($bookingId);
        if (!$booking) {
            throw new RuntimeException("Booking not found.");
        }

        if ($amount <= 0) {
            throw new RuntimeException("Payment amount must be greater than zero.");
        }

        $payment = Payment::create([
            'booking_id'   => $bookingId,
            'amount'       => $amount,
            'method'       => $method,
            'type'         => 'payment',
            'reference_no' => $refNo,
            'note'         => $note,
            'received_by'  => $staffUserId,
            'paid_at'      => date('Y-m-d H:i:s')
        ]);

        Notification::createForUser(
            (int)$booking->user_id,
            'Payment Received',
            "Payment of " . money($amount) . " received for booking {$booking->booking_ref}.",
            'guest/my-bookings.php'
        );

        AuditLog::record('payment.recorded', 'payments', $payment->id, "Payment " . money($amount) . " via {$method}");
        return $payment;
    }

    public function recordRefund(int $bookingId, float $amount, string $method, int $staffUserId, string $reason): Payment
    {
        $booking = Booking::find($bookingId);
        if (!$booking) {
            throw new RuntimeException("Booking not found.");
        }

        $refundAmount = -abs($amount);

        $payment = Payment::create([
            'booking_id'   => $bookingId,
            'amount'       => $refundAmount,
            'method'       => $method,
            'type'         => 'refund',
            'reference_no' => 'REFUND-' . date('YmdHis'),
            'note'         => $reason,
            'received_by'  => $staffUserId,
            'paid_at'      => date('Y-m-d H:i:s')
        ]);

        AuditLog::record('payment.refund', 'payments', $payment->id, "Refund " . money(abs($amount)) . " for reason: {$reason}");
        return $payment;
    }

    public function addExtraCharge(int $bookingId, string $description, float $qty, float $unitPrice, int $staffUserId): ExtraCharge
    {
        $booking = Booking::find($bookingId);
        if (!$booking) {
            throw new RuntimeException("Booking not found.");
        }

        $amount = round($qty * $unitPrice, 2);

        $extra = ExtraCharge::create([
            'booking_id'  => $bookingId,
            'description' => $description,
            'qty'         => $qty,
            'unit_price'  => $unitPrice,
            'amount'      => $amount,
            'added_by'    => $staffUserId
        ]);

        AuditLog::record('extracharge.added', 'extra_charges', $extra->id, "Added {$description} (" . money($amount) . ")");
        return $extra;
    }

    public function paymentStatus(Booking $booking): string
    {
        $totalPaid = $booking->totalPaid();
        $totalAmount = (float)$booking->total_amount;

        if ($totalPaid <= 0) {
            return 'unpaid';
        } elseif ($totalPaid < $totalAmount) {
            return 'partial';
        } else {
            return 'paid';
        }
    }
}
