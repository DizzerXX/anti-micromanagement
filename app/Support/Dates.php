<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Date wording used across screens and files. Stateless.
 */
final class Dates
{
    /** "Thu 8 Oct" */
    public static function day(CarbonInterface $date): string
    {
        return $date->format('D j M');
    }

    /** "8 Oct" */
    public static function short(CarbonInterface $date): string
    {
        return $date->format('j M');
    }

    /** "Thu 8 Oct 2026" */
    public static function full(CarbonInterface $date): string
    {
        return $date->format('D j M Y');
    }

    /**
     * "5 – 11 Oct", "28 Sep – 4 Oct", "29 Dec 2025 – 4 Jan 2026".
     * $year appends the year when both dates share it: "5 – 11 Oct 2026".
     * $tight drops the spaces around the dash: "5–11 Oct".
     */
    public static function range(CarbonInterface $start, CarbonInterface $end, bool $year = false, bool $tight = false): string
    {
        $dash = $tight ? '–' : ' – ';

        if ($start->isSameDay($end)) {
            return $start->format($year ? 'j M Y' : 'j M');
        }

        if ($start->year !== $end->year) {
            return $start->format('j M Y').$dash.$end->format('j M Y');
        }

        $suffix = $year ? ' '.$end->format('Y') : '';

        if ($start->month === $end->month) {
            return $start->format('j').$dash.$end->format('j M').$suffix;
        }

        return $start->format('j M').$dash.$end->format('j M').$suffix;
    }

    /** "Mon 5 – Sun 11 Oct", "Mon 28 Sep – Sun 4 Oct" */
    public static function weekdayRange(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('D j M');
        }

        return $start->month === $end->month && $start->year === $end->year
            ? $start->format('D j').' – '.$end->format('D j M')
            : $start->format('D j M').' – '.$end->format('D j M');
    }

    /** "THU 8 OCT 2026 · WEEK 41" */
    public static function eyebrow(CarbonInterface $date): string
    {
        return mb_strtoupper($date->format('D j M Y')).' · WEEK '.$date->isoWeek();
    }

    /** "Today 10:42", "Yesterday", "Tue 6 Oct" */
    public static function lastUpdate(CarbonInterface $loggedFor, ?CarbonInterface $createdAt, CarbonInterface $today): string
    {
        if ($loggedFor->isSameDay($today)) {
            return $createdAt !== null && $createdAt->isSameDay($today)
                ? 'Today '.$createdAt->format('H:i')
                : 'Today';
        }

        return self::day($loggedFor);
    }

    /** "1 day", "3 days" */
    public static function days(int $days): string
    {
        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    /** "5 days ago", "1 day ago", "today" */
    public static function ago(int $days): string
    {
        return $days === 0 ? 'today' : self::days($days).' ago';
    }
}
