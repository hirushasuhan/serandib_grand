<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\AuditLog;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Setting;
use PDO;
use RuntimeException;

final class InvoiceService
{
    public function generate(Booking $booking, int $staffUserId): Invoice
    {
        // Return existing invoice if already generated
        $existing = $booking->invoice();
        if ($existing) {
            return $existing;
        }

        return Database::getInstance()->transaction(function (PDO $pdo) use ($booking, $staffUserId) {

            // Sequential invoice number generation inside transaction
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_no LIKE :prefix");
            $stmt->execute([':prefix' => "INV-{$year}-%"]);
            $seq = (int)$stmt->fetchColumn() + 1;
            $invoiceNo = sprintf("INV-%s-%06d", $year, $seq);

            $taxRate     = (float)Setting::get('tax_rate', '8.00');
            $serviceRate = (float)Setting::get('service_charge', '10.00');

            $subtotal     = (float)$booking->subtotal;
            $discount     = (float)$booking->discount;
            $serviceAmt   = (float)$booking->service_charge;
            $taxAmount    = (float)$booking->tax_amount;
            $grandTotal   = (float)$booking->total_amount;

            // Include extra charges in total calculation if present
            $extras = $booking->extraCharges();
            $extraTotal = 0.00;
            foreach ($extras as $extra) {
                $extraTotal += (float)$extra->amount;
            }

            if ($extraTotal > 0) {
                $subtotal   += $extraTotal;
                $taxable     = max(0.00, $subtotal - $discount);
                $serviceAmt  = round(($taxable * $serviceRate) / 100, 2);
                $taxAmount   = round(($taxable * $taxRate) / 100, 2);
                $grandTotal  = round($taxable + $serviceAmt + $taxAmount, 2);

                // Update booking total amount to reflect extra charges
                $booking->update(['total_amount' => $grandTotal, 'subtotal' => $subtotal]);
            }

            $invStmt = $pdo->prepare("
                INSERT INTO invoices (
                    invoice_no, booking_id, issued_by, issued_at, subtotal,
                    discount, service_rate, service_amt, tax_rate, tax_amount, grand_total
                ) VALUES (
                    :invoice_no, :booking_id, :issued_by, NOW(), :subtotal,
                    :discount, :service_rate, :service_amt, :tax_rate, :tax_amount, :grand_total
                )
            ");

            $invStmt->execute([
                ':invoice_no'   => $invoiceNo,
                ':booking_id'   => $booking->id,
                ':issued_by'    => $staffUserId,
                ':subtotal'     => $subtotal,
                ':discount'     => $discount,
                ':service_rate' => $serviceRate,
                ':service_amt'  => $serviceAmt,
                ':tax_rate'     => $taxRate,
                ':tax_amount'   => $taxAmount,
                ':grand_total'  => $grandTotal,
            ]);

            $invoiceId = (int)$pdo->lastInsertId();

            // Insert snapshot invoice line items
            $itemStmt = $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, description, qty, unit_price, line_total)
                VALUES (:invoice_id, :desc, :qty, :unit_price, :line_total)
            ");

            // Main Room Charge item
            $roomName = $booking->room()?->roomType()?->name ?? 'Room Charge';
            $itemStmt->execute([
                ':invoice_id' => $invoiceId,
                ':desc'       => "{$roomName} - {$booking->nights} Nights ({$booking->check_in} to {$booking->check_out})",
                ':qty'        => $booking->nights,
                ':unit_price' => $booking->room_rate,
                ':line_total' => $booking->subtotal
            ]);

            // Extra charge items
            foreach ($extras as $extra) {
                $itemStmt->execute([
                    ':invoice_id' => $invoiceId,
                    ':desc'       => $extra->description,
                    ':qty'        => $extra->qty,
                    ':unit_price' => $extra->unit_price,
                    ':line_total' => $extra->amount
                ]);
            }

            AuditLog::record('invoice.generated', 'invoices', $invoiceId, "Invoice {$invoiceNo} issued for booking {$booking->booking_ref}");

            return Invoice::find($invoiceId);
        });
    }
}
