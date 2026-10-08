<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\InvalidTaskUpdate;
use App\Models\Module;
use App\Models\Task;
use Carbon\CarbonInterface;

/**
 * Title, module and due date of a task. Status is never written here; that
 * belongs to TaskStatusService.
 */
class TaskDetailsService
{
    public function update(Task $task, string $title, ?Module $module, ?CarbonInterface $dueDate): Task
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 200) {
            throw new InvalidTaskUpdate('Enter a title of 1 to 200 characters.', 'title');
        }

        $this->guardModule($task, $module);

        $task->forceFill([
            'title' => $title,
            'module_id' => $module?->id,
            'due_date' => $dueDate?->toDateString(),
        ])->save();

        return $task;
    }

    public function move(Task $task, ?Module $module): Task
    {
        $this->guardModule($task, $module);

        $task->forceFill(['module_id' => $module?->id])->save();

        return $task;
    }

    /**
     * Soft delete: the task leaves lists and new reports, its events stay.
     * Generated report files are not touched.
     */
    public function delete(Task $task): void
    {
        $task->delete();
    }

    private function guardModule(Task $task, ?Module $module): void
    {
        if ($module !== null && $module->project_id !== $task->project_id) {
            throw new InvalidTaskUpdate('Pick a module from this project.', 'module_id');
        }
    }
}
