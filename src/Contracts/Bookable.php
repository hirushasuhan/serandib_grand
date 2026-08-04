<?php
declare(strict_types=1);

namespace App\Contracts;

interface Bookable
{
    public function isAvailableBetween(string $checkIn, string $checkOut): bool;
    public function nightlyRate(): float;
}
