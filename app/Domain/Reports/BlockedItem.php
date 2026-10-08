<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use Carbon\CarbonImmutable;

final readonly class BlockedItem
{
    public function __construct(
        public TaskRef $task,
        public ?string $note,
        public CarbonImmutable $since,
        public int $days,
    ) {}
}
