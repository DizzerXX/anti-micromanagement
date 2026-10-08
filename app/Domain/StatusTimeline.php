<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A task's events in replay order (SPEC §4.5) with the questions the report
 * engine and the status service ask of them.
 */
final class StatusTimeline
{
    /** @var list<TaskEvent> */
    private readonly array $events;

    /**
     * @param  iterable<TaskEvent>  $events  any order; sorted here
     */
    public function __construct(iterable $events)
    {
        $list = $events instanceof Collection ? $events->all() : [...$events];

        usort($list, static fn (TaskEvent $a, TaskEvent $b): int => [
            $a->logged_for->toDateString(), $a->created_at->getTimestamp(), $a->id,
        ] <=> [
            $b->logged_for->toDateString(), $b->created_at->getTimestamp(), $b->id,
        ]);

        $this->events = array_values($list);
    }

    public static function forTask(Task $task): self
    {
        return new self(TaskEvent::query()->where('task_id', $task->id)->get());
    }

    /** @return list<TaskEvent> */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Last event with logged_for ≤ $date, or null if the task did not exist.
     */
    public function lastEventOn(CarbonInterface $date): ?TaskEvent
    {
        $day = $date->toDateString();
        $found = null;

        foreach ($this->events as $event) {
            if ($event->logged_for->toDateString() > $day) {
                break;
            }
            $found = $event;
        }

        return $found;
    }

    public function statusAt(CarbonInterface $date): ?TaskStatus
    {
        return $this->lastEventOn($date)?->to_status;
    }

    public function createdOn(): ?CarbonImmutable
    {
        return isset($this->events[0]) ? $this->events[0]->logged_for->toImmutable() : null;
    }

    public function lastLoggedFor(): ?CarbonImmutable
    {
        $last = $this->events[array_key_last($this->events) ?? 0] ?? null;

        return $last?->logged_for->toImmutable();
    }

    /**
     * Events with logged_for in [$start, $end], in replay order.
     *
     * @return list<TaskEvent>
     */
    public function between(CarbonInterface $start, CarbonInterface $end): array
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        return array_values(array_filter(
            $this->events,
            static fn (TaskEvent $e): bool => $e->logged_for->toDateString() >= $from && $e->logged_for->toDateString() <= $to,
        ));
    }

    /**
     * Each event paired with the status replay says it moved from. This is
     * more reliable than `from_status` once backdated rows are inserted.
     *
     * @return list<array{event: TaskEvent, from: ?TaskStatus}>
     */
    public function steps(): array
    {
        $steps = [];
        $previous = null;

        foreach ($this->events as $event) {
            $steps[] = ['event' => $event, 'from' => $previous];
            $previous = $event->to_status;
        }

        return $steps;
    }
}
