<?php
declare(strict_types=1);

namespace App\Models;

final class BookingStatus
{
    public const PENDING          = 'pending';
    public const CONFIRMED        = 'confirmed';
    public const CHECKED_IN       = 'checked_in';
    public const CHECKED_OUT      = 'checked_out';
    public const CANCEL_REQUESTED = 'cancel_requested';
    public const CANCELLED        = 'cancelled';
    public const REJECTED         = 'rejected';
    public const NO_SHOW          = 'no_show';

    private const TRANSITIONS = [
        self::PENDING          => [self::CONFIRMED, self::REJECTED, self::CANCELLED],
        self::CONFIRMED        => [self::CHECKED_IN, self::CANCEL_REQUESTED, self::NO_SHOW],
        self::CANCEL_REQUESTED => [self::CANCELLED, self::CONFIRMED],
        self::CHECKED_IN       => [self::CHECKED_OUT],
        self::CHECKED_OUT      => [],
        self::CANCELLED        => [],
        self::REJECTED         => [],
        self::NO_SHOW          => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
