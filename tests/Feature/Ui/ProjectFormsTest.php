<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_project_and_land_on_its_page(): void
    {
        $this->signInOnMockupDay(withSampleData: false);

        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'Garden', 'description' => 'Beds and paths', 'is_pinned' => true])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect('/projects/'.Project::query()->sole()->id);

        $this->assertTrue(Project::query()->sole()->is_pinned);
        $this->get('/')->assertSee('Garden')->assertSee('0 open');
    }

    public function test_name_must_be_unique_among_active_projects_only(): void
    {
        $this->signInOnMockupDay(withSampleData: false);
        Project::factory()->create(['name' => 'Garden']);
        Project::factory()->archived()->create(['name' => 'Attic']);

        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'Garden'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);

        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'Attic'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_archive_hides_the_project_from_lists_and_the_sidebar(): void
    {
        $this->signInOnMockupDay();
        $project = Project::query()->where('name', 'Home Lab')->firstOrFail();

        Livewire::test(ListProjects::class)
            ->callTableAction('archive', $project)
            ->assertNotified('Archived.');

        $this->assertTrue($project->refresh()->isArchived());
        Livewire::test(ListProjects::class)->assertCanNotSeeTableRecords([$project]);
        $this->get('/')->assertDontSee('5 open');
    }
}
