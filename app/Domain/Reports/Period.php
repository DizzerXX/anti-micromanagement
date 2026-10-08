<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Enums\PeriodType;
use App\Enums\WeekStart;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A report period (SPEC §5.1): nominal [start, end] plus the effective end,
 * which is never later than today.
 */
final readonly class Period
{
    public function __construct(
        public PeriodType $type,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $effectiveEnd,
    ) {}

    public static function containing(PeriodType $type, CarbonInterface $date, WeekStart $weekStart, CarbonInterface $today): self
    {
        $day = CarbonImmutable::parse($date->toDateString());

        $start = match ($type) {
            PeriodType::Daily => $day,
            PeriodType::Weekly => $day->subDays(($day->dayOfWeek - $weekStart->carbonDay() + 7) % 7),
        };

        return self::starting($type, $start, $today);
    }

    public static function starting(PeriodType $type, CarbonInterface $start, CarbonInterface $today): self
    {
        $start = CarbonImmutable::parse($start->toDateString());
        $end = $start->addDays($type->lengthInDays() - 1);
        $todayDate = CarbonImmutable::parse($today->toDateString());

        return new self($type, $start, $end, $end->min($todayDate));
    }

    /**
     * Same length, immediately before start, compared in full (SPEC §5.1).
     */
    public function previous(): self
    {
        $start = $this->start->subDays($this->type->lengthInDays());
        $end = $this->start->subDay();

        return new self($this->type, $start, $end, $end);
    }

    public function shift(int $periods, CarbonInterface $today): self
    {
        return self::starting($this->type, $this->start->addDays($periods * $this->type->lengthInDays()), $today);
    }

    public function isPartial(): bool
    {
        return $this->effectiveEnd->lt($this->end);
    }

    /**
     * True when the period has not started yet.
     */
    public function isFuture(): bool
    {
        return $this->effectiveEnd->lt($this->start);
    }

    public function contains(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return $day >= $this->start->toDateString() && $day <= $this->end->toDateString();
    }

    public function key(): string
    {
        return $this->type->value.':'.$this->start->toDateString();
    }
}
