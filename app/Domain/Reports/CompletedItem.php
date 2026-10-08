<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;

final readonly class CompletedItem
{
    public function __construct(public TaskRef $task, public CarbonImmutable $date) {}
}
