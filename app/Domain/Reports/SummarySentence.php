<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Enums\PeriodType;

/**
 * Rule-based summary (SPEC §5.8), built only from report numbers. Parts with
 * a zero count are left out.
 */
class SummarySentence
{
    /**
     * @param  list<HeadlineMetric>  $headline
     * @param  list<BlockedItem>  $blocked
     */
    public function format(PeriodType $type, array $headline, array $blocked, int $stale, int $staleDays): string
    {
        $metrics = [];
        foreach ($headline as $metric) {
            $metrics[$metric->key] = $metric;
        }

        $parts = [];

        $completed = $metrics['completed'];
        $delta = $completed->delta();
        $comparison = $delta === 0 ? '' : sprintf(', %d %s than %s', abs($delta), $delta > 0 ? 'more' : 'fewer', $type->previousLabel());

        if ($completed->value > 0) {
            $parts[] = $this->plural($completed->value, 'task').' completed'.$comparison.'.';
        } elseif ($delta !== 0) {
            $parts[] = 'No tasks completed'.$comparison.'.';
        }

        if ($metrics['started']->value > 0) {
            $parts[] = $metrics['started']->value.' started.';
        }

        $blockedCount = $metrics['blocked']->value;
        if ($blockedCount === 1 && isset($blocked[0])) {
            $parts[] = sprintf('1 blocked: %s, %s.', $blocked[0]->task->title, $this->blockedFor($blocked[0]->days));
        } elseif ($blockedCount > 1) {
            $parts[] = $blockedCount.' blocked.';
        }

        $overdue = $metrics['overdue']->value;
        $overduePart = $overdue > 0 ? $overdue.' '.($overdue === 1 ? 'is' : 'are').' overdue' : null;
        $stalePart = $stale > 0
            ? sprintf('%d %s had no update for %s or more', $stale, $stale === 1 ? 'has' : 'have', $this->plural($staleDays, 'day'))
            : null;

        if ($overduePart !== null && $stalePart !== null) {
            $parts[] = "{$overduePart} and {$stalePart}.";
        } elseif ($overduePart !== null || $stalePart !== null) {
            $parts[] = ($overduePart ?? $stalePart).'.';
        }

        return $parts === [] ? ($type === PeriodType::Daily ? 'Nothing moved that day.' : 'Nothing moved that week.') : implode(' ', $parts);
    }

    private function blockedFor(int $days): string
    {
        return $days === 0 ? 'since today' : 'for '.$this->plural($days, 'day');
    }

    private function plural(int $count, string $noun): string
    {
        return $count.' '.$noun.($count === 1 ? '' : 's');
    }
}
