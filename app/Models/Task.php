<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LocalDate;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property ?int $module_id
 * @property string $title
 * @property ?Carbon $due_date
 * @property TaskStatus $current_status
 * @property Carbon $last_logged_for
 * @property-read Project $project
 * @property-read ?Module $module
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Deleting hides the task everywhere but keeps its events, so history
     * stays append-only (CLAUDE.md, non-negotiable 1).
     */
    use SoftDeletes;

    /**
     * `current_status` and `last_logged_for` are caches written only by
     * TaskStatusService, so they are not fillable.
     */
    protected $fillable = ['project_id', 'module_id', 'title', 'due_date'];

    protected function casts(): array
    {
        return [
            'due_date' => LocalDate::class,
            'current_status' => TaskStatus::class,
            'last_logged_for' => LocalDate::class,
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Events in replay order (SPEC §4.5).
     *
     * @return HasMany<TaskEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class)
            ->orderBy('logged_for')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Latest non-creation event, for "last update" columns.
     *
     * @return HasOne<TaskEvent, $this>
     */
    public function latestUpdate(): HasOne
    {
        return $this->hasOne(TaskEvent::class)
            ->ofMany(['logged_for' => 'max', 'id' => 'max'], function ($query): void {
                $query->whereNotNull('from_status');
            });
    }

    /**
     * Latest → blocked event, for the blocker note.
     *
     * @return HasOne<TaskEvent, $this>
     */
    public function latestBlock(): HasOne
    {
        return $this->hasOne(TaskEvent::class)
            ->ofMany(['logged_for' => 'max', 'id' => 'max'], function ($query): void {
                $query->where('to_status', TaskStatus::Blocked->value)
                    ->where(fn ($q) => $q->whereNull('from_status')->orWhere('from_status', '!=', TaskStatus::Blocked->value));
            });
    }

    /**
     * "Project › Module", or just the project when there is no module.
     */
    public function path(): string
    {
        return $this->module
            ? $this->project->name.' › '.$this->module->name
            : $this->project->name;
    }
}
