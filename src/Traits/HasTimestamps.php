<?php
declare(strict_types=1);

namespace App\Traits;

trait HasTimestamps
{
    public function touchCreated(): void
    {
        $this->created_at = date('Y-m-d H:i:s');
    }

    public function touchUpdated(): void
    {
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
