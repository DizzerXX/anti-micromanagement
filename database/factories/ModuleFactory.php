<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
