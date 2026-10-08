<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\TaskStatus;

/**
 * A refused log/undo. `field` names the form field the message belongs under.
 */
class InvalidTaskUpdate extends \DomainException
{
    public function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function transition(TaskStatus $from, TaskStatus $to): self
    {
        return new self(
            "Can’t go from {$from->label()} to {$to->label()}. {$from->transitionHint($to)}",
            'status',
        );
    }

    public static function noteRequired(TaskStatus $from, TaskStatus $to): self
    {
        return new self(
            $to === TaskStatus::Blocked && $from !== TaskStatus::Blocked
                ? 'Add a note. Blocked needs one: say what you are waiting on.'
                : 'Add a note. Logging the same status is a progress note, so it needs text.',
            'note',
        );
    }

    public static function noteTooLong(): self
    {
        return new self('Shorten the note to 500 characters or fewer.', 'note');
    }

    public static function futureDate(): self
    {
        return new self('Pick today or an earlier date. Updates can’t be logged for the future.', 'logged_for');
    }

    public static function beforeCreation(string $created): self
    {
        return new self("Pick {$created} or later. The task didn’t exist before then.", 'logged_for');
    }

    public static function undoRefused(): self
    {
        return new self('Can’t undo: newer updates exist or too much time passed.', 'undo');
    }
}
