<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;

final readonly class OverdueItem
{
    public function __construct(
        public TaskRef $task,
        public CarbonImmutable $due,
        public int $daysLate,
    ) {}
}
