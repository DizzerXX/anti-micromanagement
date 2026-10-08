<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }

    public function lengthInDays(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
        };
    }

    /**
     * How the previous period is named in sentences: "2 more than last week".
     */
    public function previousLabel(): string
    {
        return match ($this) {
            self::Daily => 'yesterday',
            self::Weekly => 'last week',
        };
    }

    public function unitLabel(): string
    {
        return match ($this) {
            self::Daily => 'day',
            self::Weekly => 'week',
        };
    }
}
