<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ExtraCharge extends Model
{
    protected static string $table = 'extra_charges';
    protected static array  $fillable = [
        'booking_id', 'description', 'qty', 'unit_price', 'amount', 'added_by'
    ];

    public function rules(): array
    {
        return [
            'booking_id'  => 'required|integer|exists:bookings,id',
            'description' => 'required|min:2|max:160',
            'qty'         => 'required|numeric|min:0.1',
            'unit_price'  => 'required|numeric|min:0'
        ];
    }
}
