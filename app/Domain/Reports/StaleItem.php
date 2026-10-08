<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;

final readonly class StaleItem
{
    public function __construct(
        public TaskRef $task,
        public CarbonImmutable $lastUpdate,
        public int $days,
    ) {}
}
