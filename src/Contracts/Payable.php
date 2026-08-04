<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Payment;

interface Payable
{
    public function amountDue(): float;
    public function applyPayment(Payment $payment): void;
}
