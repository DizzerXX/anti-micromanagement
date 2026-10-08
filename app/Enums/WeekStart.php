<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

enum WeekStart: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Carbon day-of-week number (0 = Sunday).
     */
    public function carbonDay(): int
    {
        return match ($this) {
            self::Sunday => CarbonInterface::SUNDAY,
            self::Monday => CarbonInterface::MONDAY,
            self::Tuesday => CarbonInterface::TUESDAY,
            self::Wednesday => CarbonInterface::WEDNESDAY,
            self::Thursday => CarbonInterface::THURSDAY,
            self::Friday => CarbonInterface::FRIDAY,
            self::Saturday => CarbonInterface::SATURDAY,
        };
    }
}
