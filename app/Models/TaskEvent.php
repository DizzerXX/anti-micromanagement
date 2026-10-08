<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LocalDate;

use App\Enums\TaskStatus;
use Database\Factories\TaskEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history row. Never updated; only TaskStatusService::undo()
 * may delete one.
 *
 * @property int $id
 * @property int $task_id
 * @property ?TaskStatus $from_status
 * @property TaskStatus $to_status
 * @property ?string $note
 * @property \Illuminate\Support\Carbon $logged_for
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Task $task
 */
class TaskEvent extends Model
{
    /** @use HasFactory<TaskEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['task_id', 'from_status', 'to_status', 'note', 'logged_for', 'created_at'];

    protected function casts(): array
    {
        return [
            'from_status' => TaskStatus::class,
            'to_status' => TaskStatus::class,
            'logged_for' => LocalDate::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Task events are append-only.');
        });
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isCreation(): bool
    {
        return $this->from_status === null;
    }

    public function isProgressNote(): bool
    {
        return $this->from_status !== null && $this->from_status === $this->to_status;
    }
}
