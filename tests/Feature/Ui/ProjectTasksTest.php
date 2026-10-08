<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Enums\TaskStatus;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Livewire\ProjectModules;
use App\Livewire\ProjectTasks;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectTasksTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInOnMockupDay();
        $this->project = Project::query()->where('name', 'Report Generator')->firstOrFail();
    }

    public function test_done_groups_start_collapsed_and_toggle(): void
    {
        $module = Module::query()->where('name', 'Data model')->firstOrFail();

        Livewire::test(ProjectTasks::class, ['projectId' => $this->project->id])
            ->assertSee('3 of 3 done · collapsed')
            ->assertDontSee('Seed realistic sample data')
            ->call('toggleGroup', 'm'.$module->id)
            ->assertSee('Seed realistic sample data')
            ->assertDontSee('3 of 3 done · collapsed');
    }

    public function test_filters_and_flat_list(): void
    {
        Livewire::test(ProjectTasks::class, ['projectId' => $this->project->id])
            ->set('statusFilter', 'blocked')
            ->assertSee('Docker image')
            ->assertDontSee('Compose file')
            ->set('statusFilter', '')
            ->set('search', 'pdf')
            ->assertSee('dompdf report template')
            ->assertSee('PDF download route')
            ->assertDontSee('Docker image')
            ->set('search', '')
            ->set('groupByModule', false)
            ->assertDontSee('collapsed')
            ->assertSee('Seed realistic sample data');
    }

    public function test_new_task_from_the_page_header(): void
    {
        $module = Module::query()->where('name', 'Deploy')->firstOrFail();

        Livewire::test(ViewProject::class, ['record' => $this->project->id])
            ->callAction('newTask', ['title' => 'Write the README', 'module_id' => $module->id, 'due_date' => '2026-10-12'])
            ->assertHasNoActionErrors()
            ->assertDispatched('tasks-changed');

        $task = Task::query()->where('title', 'Write the README')->firstOrFail();
        $this->assertSame(TaskStatus::Todo, $task->current_status);
        $this->assertSame($module->id, $task->module_id);
        $this->assertSame(1, $task->events()->count());
    }

    public function test_edit_move_and_delete_row_actions(): void
    {
        $task = Task::query()->where('title', 'Compose file')->firstOrFail();
        $export = Module::query()->where('name', 'Export')->firstOrFail();

        $component = Livewire::test(ProjectTasks::class, ['projectId' => $this->project->id]);

        $component->callAction('editTask', ['title' => 'Compose file v2', 'module_id' => $task->module_id, 'due_date' => '2026-10-20'], ['task' => $task->id]);
        $this->assertSame('Compose file v2', $task->refresh()->title);
        $this->assertSame('2026-10-20', $task->due_date->toDateString());

        $component->callAction('moveTask', ['module_id' => $export->id], ['task' => $task->id]);
        $this->assertSame($export->id, $task->refresh()->module_id);

        $component->callAction('moveTask', ['module_id' => null], ['task' => $task->id]);
        $this->assertNull($task->refresh()->module_id);
        $component->assertSee('No module');

        $events = $task->events()->count();
        $component->callAction('deleteTask', arguments: ['task' => $task->id]);
        $this->assertSoftDeleted($task);
        $this->assertSame($events, TaskEvent::query()->where('task_id', $task->id)->count(), 'history is kept');
        $component->assertDontSee('Compose file v2');
    }

    public function test_modules_tab_creates_and_deletes_modules(): void
    {
        Livewire::test(ProjectModules::class, ['projectId' => $this->project->id])
            ->assertCanSeeTableRecords(Module::query()->where('project_id', $this->project->id)->get())
            ->callTableAction('create', data: ['name' => 'Docs'])
            ->assertHasNoTableActionErrors();

        $docs = Module::query()->where('name', 'Docs')->firstOrFail();
        $this->assertSame($this->project->id, $docs->project_id);
        $this->assertSame(5, $docs->position);

        $deploy = Module::query()->where('name', 'Deploy')->firstOrFail();
        Livewire::test(ProjectModules::class, ['projectId' => $this->project->id])
            ->callTableAction('delete', $deploy);

        $this->assertModelMissing($deploy);
        $this->assertSame(3, Task::query()->where('project_id', $this->project->id)->whereNull('module_id')->count());
    }

    public function test_module_names_are_unique_within_a_project(): void
    {
        Livewire::test(ProjectModules::class, ['projectId' => $this->project->id])
            ->callTableAction('create', data: ['name' => 'Export'])
            ->assertHasTableActionErrors(['name' => 'unique']);
    }
}
