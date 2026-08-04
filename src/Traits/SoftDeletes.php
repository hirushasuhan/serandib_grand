<?php
declare(strict_types=1);

namespace App\Traits;

trait SoftDeletes
{
    public function softDelete(): bool
    {
        return $this->update(['is_active' => 0]);
    }

    /**
     * Undo a softDelete().
     *
     * FIX: nothing in the app could flip is_active back to 1 — a
     * "Deactivate" button existed everywhere softDelete() was used, but no
     * matching "Reactivate" ever did. Once deactivated, a row was as good as
     * gone: still in the database, still occupying its UNIQUE columns
     * (slug, room_number), but permanently invisible and unrecoverable
     * through the UI.
     */
    public function reactivate(): bool
    {
        return $this->update(['is_active' => 1]);
    }

    public static function activeOnly(): array
    {
        return static::where(['is_active' => 1]);
    }

    public static function inactiveOnly(): array
    {
        return static::where(['is_active' => 0]);
    }
}
