<?php
declare(strict_types=1);

namespace App\Contracts;

interface Reportable
{
    public function toReportRow(): array;
    public function reportHeadings(): array;
}
