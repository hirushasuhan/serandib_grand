<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Contracts\Payable;
use App\Contracts\Reportable;

class Invoice extends Model implements Payable, Reportable
{
    protected static string $table = 'invoices';
    protected static array  $fillable = [
        'invoice_no', 'booking_id', 'issued_by', 'issued_at', 'subtotal',
        'discount', 'service_rate', 'service_amt', 'tax_rate', 'tax_amount', 'grand_total'
    ];

    public function booking(): ?Booking
    {
        return Booking::find((int)$this->booking_id);
    }

    public function items(): array
    {
        $sql = "SELECT * FROM invoice_items WHERE invoice_id = :id ORDER BY id ASC";
        return Database::getInstance()->query($sql, [':id' => $this->id])->fetchAll();
    }

    public function amountDue(): float
    {
        $b = $this->booking();
        return $b ? $b->balanceDue() : (float)$this->grand_total;
    }

    public function applyPayment(Payment $p): void
    {
        // Payment recording logic is handled in PaymentService
    }

    public function toReportRow(): array
    {
        return [
            $this->invoice_no,
            $this->booking()?->booking_ref ?? 'N/A',
            $this->issued_at,
            money($this->subtotal),
            money($this->tax_amount),
            money($this->grand_total)
        ];
    }

    public function reportHeadings(): array
    {
        return ['Invoice No', 'Booking Ref', 'Issued At', 'Subtotal', 'Tax Amount', 'Grand Total'];
    }

    public function rules(): array
    {
        return [
            'booking_id'  => 'required|integer|exists:bookings,id',
            'grand_total' => 'required|numeric|min:0'
        ];
    }
}
