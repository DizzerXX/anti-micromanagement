<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_there_is_no_registration_page(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_user_seeder_creates_the_user_from_config(): void
    {
        config(['ledger.user.email' => 'owner@example.test', 'ledger.user.password' => 'secret-pass']);

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'owner@example.test')->count());
        $this->post('/login', [])->assertStatus(405); // Filament login is a Livewire form, not a POST route.
    }

    public function test_today_shows_the_mockup_content(): void
    {
        $this->signInOnMockupDay();

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['THU 8 OCT 2026 · WEEK 41', 'Today', 'Log update'])
            ->assertSee('This week, 5–11 Oct, compared with last week')
            ->assertSeeInOrder(['Completed', '7', '▲ 2 more than last week'])
            ->assertSee('▼ 1 fewer than last week')
            ->assertSee('Same as last week')
            ->assertSeeInOrder(['Needs an update', 'XLSX export class', '5 days ago', 'Reverse proxy for Nextcloud', '4 days ago', 'Invoice PDF layout', '3 days ago'])
            ->assertSeeInOrder(['Logged today', '3 updates', '10:42', 'Weekly diff query', '09:15', 'dompdf report template', '08:50', 'Backup rotation script'])
            ->assertSeeInOrder(['Blocked', 'Docker image', '3 days', 'Waiting on base image decision'])
            ->assertSeeInOrder(['Due and overdue', 'Router firmware update', '2d overdue', 'Report engine tests', '1d overdue', 'Invoice PDF layout', 'Fri 9 Oct', 'Stale detection rule'])
            ->assertSeeInOrder(['Last report', 'Weekly · 28 Sep – 4 Oct', 'Generated Mon 5 Oct, 09:02']);
    }

    public function test_sidebar_has_main_items_pinned_projects_and_settings(): void
    {
        $this->signInOnMockupDay();

        $this->get('/')
            ->assertSeeInOrder(['Today', 'Projects', 'Reports', 'Pinned projects', 'Report Generator', '9 open', 'Home Lab', '5 open', 'Client portal', '3 open', 'Settings'])
            ->assertSee('Search tasks and projects');
    }

    public function test_today_first_run_empty_state(): void
    {
        $this->signInOnMockupDay(withSampleData: false);

        $this->get('/')
            ->assertOk()
            ->assertSee('Start with a project')
            ->assertSee('Projects hold modules and tasks. Your first report appears once you have logged a few updates.')
            ->assertSee('Create project');
    }

    public function test_project_page_matches_the_mockup_structure(): void
    {
        $this->signInOnMockupDay();
        $project = Project::query()->where('name', 'Report Generator')->firstOrFail();

        $this->get("/projects/{$project->id}")
            ->assertOk()
            ->assertSeeInOrder(['Projects', 'Report Generator', '4 modules', '· 15 tasks', 'last update today at', '10:42', 'Edit project', 'New task'])
            ->assertSeeInOrder(['Where it stands', '6 of 15 done', '▲ 3 this week'])
            ->assertSee('15 tasks: 6 done, 4 in progress, 1 blocked, 4 to do', false)
            ->assertSeeInOrder(['Tasks', '15', 'Modules', '4', 'Activity'])
            ->assertSeeInOrder(['Data model', '3 of 3 done · collapsed', 'Report engine', '2 of 5 done', 'Weekly diff query', 'Today 10:42'])
            ->assertSeeInOrder(['Report engine tests', 'Wed 7 Oct · 1d late'])
            ->assertSeeInOrder(['XLSX export class', '5 days ago', 'Sun 11 Oct'])
            ->assertSeeInOrder(['Deploy', '0 of 3 done', 'Docker image', 'Waiting on base image decision'])
            ->assertDontSee('task_events table'); // inside the collapsed group
    }

    public function test_other_pages_render(): void
    {
        $this->signInOnMockupDay();
        $project = Project::query()->firstOrFail();

        $this->get('/projects')->assertOk()->assertSee('Report Generator')->assertSee('New project');
        $this->get('/projects/create')->assertOk();
        $this->get("/projects/{$project->id}/edit")->assertOk();
        $this->get("/projects/{$project->id}?tab=modules")->assertOk()->assertSee('Data model');
        $this->get("/projects/{$project->id}?tab=activity")->assertOk()->assertSee('Handles the Monday–Sunday boundary');
        $this->get('/reports')->assertOk()->assertSee('New report')->assertSee('Generate report');
        $this->get('/reports?tab=history')->assertOk()->assertSee('Weekly · 28 Sep – 4 Oct 2026');
        $this->get('/settings')->assertOk()->assertSee('Week starts on')->assertSee('Stale after');
        $this->get('/tasks')->assertOk()->assertSee('Router firmware update');
    }
}
