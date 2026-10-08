<?php

declare(strict_types=1);

namespace App\Domain\Reports;

final readonly class ProjectRow
{
    public function __construct(
        public ?int $projectId,
        public string $name,
        public int $completed,
        public int $started,
        public int $blocked,
        public int $open,
        public int $completedDelta,
    ) {}
}
