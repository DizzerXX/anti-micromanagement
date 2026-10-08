<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportSection: string
{
    case Completed = 'completed';
    case Started = 'started';
    case BlockedAndReopened = 'blocked_reopened';
    case OverdueAndStale = 'overdue_stale';
    case ByProject = 'by_project';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Started => 'Started',
            self::BlockedAndReopened => 'Blocked and reopened',
            self::OverdueAndStale => 'Overdue and stale',
            self::ByProject => 'By project',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $section): string => $section->value, self::cases());
    }
}
