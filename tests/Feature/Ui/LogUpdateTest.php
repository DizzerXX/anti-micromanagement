<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Enums\TaskStatus;
use App\Livewire\LogUpdate;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LogUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function task(string $title): Task
    {
        return Task::query()->where('title', $title)->firstOrFail();
    }

    public function test_opening_from_a_row_prefills_task_and_current_status(): void
    {
        $this->signInOnMockupDay();
        $task = $this->task('Invoice PDF layout');

        Livewire::test(LogUpdate::class)
            ->call('open', $task->id)
            ->assertSet('isOpen', true)
            ->assertSet('taskId', $task->id)
            ->assertSet('status', 'in_progress')
            ->assertSet('loggedFor', '2026-10-08')
            ->assertSee('Log update')
            ->assertSee('Adds one entry to the task’s history')
            ->assertSee('Currently In progress')
            ->assertSee('Client portal › Billing')
            ->assertSee('Backdate it if you forgot to log on the day. Reports use this date.')
            ->assertSee('Save and log another');
    }

    public function test_blocked_needs_a_note_and_says_so(): void
    {
        $this->signInOnMockupDay();
        $task = $this->task('Invoice PDF layout');

        Livewire::test(LogUpdate::class)
            ->call('open', $task->id)
            ->set('status', 'blocked')
            ->assertSee('Required when Blocked')
            ->assertSee('Appears under Blocked in the report. Say what you are waiting on.')
            ->call('save')
            ->assertHasErrors(['note'])
            ->assertSee('Add a note. Blocked needs one: say what you are waiting on.');

        $this->assertSame(TaskStatus::InProgress, $task->refresh()->current_status);
    }

    public function test_saving_writes_an_event_notifies_and_closes(): void
    {
        $this->signInOnMockupDay();
        $task = $this->task('Invoice PDF layout');

        Livewire::test(LogUpdate::class)
            ->call('open', $task->id)
            ->set('status', 'blocked')
            ->set('note', 'Waiting on the client’s logo files and final invoice footer text.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false)
            ->assertDispatched('task-logged')
            ->assertNotified('Logged.');

        $this->assertSame(TaskStatus::Blocked, $task->refresh()->current_status);
        $this->assertSame('Waiting on the client’s logo files and final invoice footer text.', TaskEvent::query()->where('task_id', $task->id)->latest('id')->first()->note);
    }

    public function test_disallowed_transition_shows_what_and_how_at_once(): void
    {
        $this->signInOnMockupDay();
        $task = $this->task('Compose file');

        Livewire::test(LogUpdate::class)
            ->call('open', $task->id)
            ->set('status', 'blocked')
            ->assertHasErrors(['status'])
            ->assertSee('Can’t go from To do to Blocked. Start it first.');
    }

    public function test_same_status_is_a_progress_note_and_needs_text(): void
    {
        $this->signInOnMockupDay();
        $task = $this->task('Wireguard config');

        Livewire::test(LogUpdate::class)
            ->call('open', $task->id)
            ->assertSee('Required for a progress note')
            ->call('save')
            ->assertHasErrors(['note'])
            ->set('note', 'Keys exchanged')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(TaskEvent::query()->where('task_id', $task->id)->latest('id')->first()->isProgressNote());
    }

    public function test_future_date_is_refused_with_a_fix(): void
    {
        $this->signInOnMockupDay();

        Livewire::test(LogUpdate::class)
            ->call('open', $this->task('Wireguard config')->id)
            ->set('status', 'done')
            ->set('loggedFor', '2026-10-09')
            ->call('save')
            ->assertHasErrors(['loggedFor']);
    }

    public function test_backdating_shows_the_status_on_that_date(): void
    {
        $this->signInOnMockupDay();

        Livewire::test(LogUpdate::class)
            ->call('open', $this->task('Weekly diff query')->id)
            ->assertSee('Currently Done')
            ->set('loggedFor', '2026-10-01')
            ->assertSee('On Thu 1 Oct: In progress');
    }

    public function test_save_and_log_another_keeps_the_panel_and_date_and_clears_the_note(): void
    {
        $this->signInOnMockupDay();

        Livewire::test(LogUpdate::class)
            ->call('open', $this->task('Wireguard config')->id)
            ->set('loggedFor', '2026-10-07')
            ->set('note', 'Peers configured')
            ->call('save', true)
            ->assertHasNoErrors()
            ->assertSet('isOpen', true)
            ->assertSet('note', '')
            ->assertSet('taskId', null)
            ->assertSet('loggedFor', '2026-10-07');
    }

    public function test_task_is_required_and_search_finds_by_project(): void
    {
        $this->signInOnMockupDay();

        $component = Livewire::test(LogUpdate::class)
            ->call('open')
            ->set('status', 'done')
            ->call('save')
            ->assertHasErrors(['taskId'])
            ->set('taskSearch', 'Home Lab');

        $titles = $component->instance()->options->pluck('title')->all();
        $this->assertContains('Wireguard config', $titles);
        $this->assertNotContains('Docker image', $titles);
    }

    public function test_recent_tasks_come_first(): void
    {
        $this->signInOnMockupDay();

        $titles = Livewire::test(LogUpdate::class)->call('open')->instance()->options->pluck('title')->take(3)->all();

        $this->assertSame(['Weekly diff query', 'dompdf report template', 'Backup rotation script'], $titles);
    }

    public function test_undo_from_the_toast_removes_the_event(): void
    {
        $this->signInOnMockupDay();
        $task = $this->task('Wireguard config');

        $component = Livewire::test(LogUpdate::class)
            ->call('open', $task->id)
            ->set('status', 'done')
            ->call('save');

        $event = TaskEvent::query()->where('task_id', $task->id)->latest('id')->firstOrFail();
        $component->call('undo', $event->id)->assertNotified('Undone.');

        $this->assertModelMissing($event);
        $this->assertSame(TaskStatus::InProgress, $task->refresh()->current_status);
    }

    public function test_undo_after_the_window_is_refused_with_the_spec_message(): void
    {
        $this->signInOnMockupDay();
        $task = $this->task('Wireguard config');

        $component = Livewire::test(LogUpdate::class)->call('open', $task->id)->set('status', 'done')->call('save');
        $event = TaskEvent::query()->where('task_id', $task->id)->latest('id')->firstOrFail();

        $this->travel(61)->seconds();
        $component->call('undo', $event->id)->assertNotified('Can’t undo: newer updates exist or too much time passed.');

        $this->assertModelExists($event);
    }
}
