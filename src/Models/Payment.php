<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Traits\HasTimestamps;
use App\Contracts\Reportable;

class Payment extends Model implements Reportable
{
    use HasTimestamps;

    protected static string $table = 'payments';
    protected static array  $fillable = [
        'booking_id', 'amount', 'method', 'type', 'reference_no', 'note', 'received_by', 'paid_at'
    ];

    public function booking(): ?Booking
    {
        return Booking::find((int)$this->booking_id);
    }

    public function receiver(): ?User
    {
        return User::find((int)$this->received_by);
    }

    public function toReportRow(): array
    {
        return [
            $this->id,
            $this->booking()?->booking_ref ?? 'N/A',
            money($this->amount),
            strtoupper($this->method),
            ucfirst($this->type),
            $this->paid_at
        ];
    }

    public function reportHeadings(): array
    {
        return ['Payment ID', 'Booking Ref', 'Amount', 'Method', 'Type', 'Paid At'];
    }

    public function rules(): array
    {
        return [
            'booking_id' => 'required|integer|exists:bookings,id',
            'amount'     => 'required|numeric',
            'method'     => 'required|in:cash,card_at_hotel,bank_transfer',
            'type'       => 'required|in:payment,refund'
        ];
    }
}
