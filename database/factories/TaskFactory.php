<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a bare task row. Use TaskHistory (database/factories/TaskHistory.php)
 * to create tasks with real event histories through TaskStatusService.
 *
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'module_id' => null,
            'title' => fake()->sentence(3),
            'due_date' => null,
            'current_status' => TaskStatus::Todo,
            'last_logged_for' => now()->toDateString(),
        ];
    }
}
