<?php

declare(strict_types=1);

namespace App\Domain\Reports;

final readonly class HeadlineMetric
{
    public function __construct(
        public string $key,
        public string $label,
        public int $value,
        public int $previous,
    ) {}

    public function delta(): int
    {
        return $this->value - $this->previous;
    }

    /**
     * SPEC §5.10: more completed = success; more overdue = danger; else muted.
     */
    public function tone(): string
    {
        return match (true) {
            $this->key === 'completed' && $this->delta() > 0 => 'success',
            $this->key === 'overdue' && $this->delta() > 0 => 'danger',
            default => 'muted',
        };
    }

    public function arrow(): string
    {
        return match ($this->delta() <=> 0) {
            1 => '▲',
            -1 => '▼',
            0 => '=',
        };
    }

    /**
     * "+2", "−1" (true minus sign), "0".
     */
    public function signedDelta(): string
    {
        return self::signed($this->delta());
    }

    public static function signed(int $value): string
    {
        return match ($value <=> 0) {
            1 => '+'.$value,
            -1 => '−'.abs($value),
            0 => '0',
        };
    }
}
