<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'is_pinned' => false,
            'archived_at' => null,
        ];
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
