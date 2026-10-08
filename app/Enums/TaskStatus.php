<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::InProgress => 'In progress',
            self::Blocked => 'Blocked',
            self::Done => 'Done',
        };
    }

    /**
     * Name of the icon in the `ledger` Blade icon set (resources/svg).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Todo => 'status-todo',
            self::InProgress => 'status-progress',
            self::Blocked => 'status-blocked',
            self::Done => 'status-done',
        };
    }

    /**
     * Registered Filament colour and CSS token suffix (`--status-<color>-fg`).
     */
    public function color(): string
    {
        return match ($this) {
            self::Todo => 'todo',
            self::InProgress => 'progress',
            self::Blocked => 'blocked',
            self::Done => 'done',
        };
    }

    /**
     * Allowed transitions (SPEC §4.2). Same status is a progress note.
     */
    public function canTransitionTo(self $to): bool
    {
        if ($to === $this) {
            return true;
        }

        return in_array($to, match ($this) {
            self::Todo => [self::InProgress, self::Done],
            self::InProgress => [self::Blocked, self::Done],
            self::Blocked => [self::InProgress, self::Done],
            self::Done => [self::InProgress],
        }, true);
    }

    /**
     * A note is required for → blocked and for progress notes.
     */
    public function requiresNoteFrom(self $from): bool
    {
        return $this === self::Blocked || $this === $from;
    }

    /**
     * Second half of "Can't go from X to Y. <hint>" (HANDOFF §3: what + how).
     */
    public function transitionHint(self $to): string
    {
        return match (true) {
            $this === self::Todo && $to === self::Blocked => 'Start it first.',
            $this === self::Done && $to === self::Blocked => 'Reopen it as In progress first.',
            $this === self::Done && $to === self::Todo => 'Reopen it as In progress instead.',
            $to === self::Todo => 'Tasks don’t go back to To do. Pick In progress instead.',
            default => 'Pick another status.',
        };
    }
}
