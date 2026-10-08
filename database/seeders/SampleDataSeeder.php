<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Database\Factories\TaskHistory;
use Illuminate\Database\Seeder;

/**
 * The mockup data from HANDOFF §9. "D" is Thu 8 Oct 2026 in the mockups;
 * every date is shifted so D lands on today.
 */
class SampleDataSeeder extends Seeder
{
    public const MOCKUP_TODAY = '2026-10-08';

    private int $shiftDays = 0;

    public function run(): void
    {
        $this->shiftDays = (int) CarbonImmutable::parse(self::MOCKUP_TODAY)
            ->diffInDays(CarbonImmutable::today(), false);

        $this->reportGenerator();
        $this->homeLab();
        $this->clientPortal();
    }

    /**
     * Shift a mockup date ("2026-09-24", or "D 10:42" for today) to the
     * equivalent real date, keeping any time.
     */
    public function at(string $mockup): string
    {
        if (str_starts_with($mockup, 'D')) {
            return trim(CarbonImmutable::today()->toDateString().substr($mockup, 1));
        }

        $date = CarbonImmutable::parse(substr($mockup, 0, 10))->addDays($this->shiftDays)->toDateString();

        return trim($date.substr($mockup, 10));
    }

    private function reportGenerator(): void
    {
        $project = Project::query()->create(['name' => 'Report Generator', 'is_pinned' => true,
            'description' => 'This app: logs task updates and builds weekly reports.']);
        [$data, $engine, $export, $deploy] = $this->modules($project, ['Data model', 'Report engine', 'Export', 'Deploy']);

        $this->task($project, $data, 'task_events table', [['in_progress', '2026-09-24'], ['done', '2026-09-30']]);
        $this->task($project, $data, 'Status observer writes event', [['in_progress', '2026-09-30'], ['done', '2026-10-02']], created: '2026-09-28');
        $this->task($project, $data, 'Seed realistic sample data', [['done', '2026-09-25']], created: '2026-09-24');

        $this->task($project, $engine, 'Weekly diff query', [
            ['in_progress', '2026-09-25'],
            ['done', 'D 10:42', 'Handles the Monday–Sunday boundary and an unfinished current week.'],
        ], created: '2026-09-24');
        $this->task($project, $engine, 'Period window helper', [['in_progress', '2026-09-25'], ['done', '2026-10-06']], created: '2026-09-24', due: '2026-10-01');
        $this->task($project, $engine, 'Stale detection rule', [['in_progress', '2026-10-07']], created: '2026-09-28', due: '2026-10-09');
        $this->task($project, $engine, 'Report engine tests', [
            ['in_progress', '2026-10-01'],
            ['blocked', '2026-10-02', 'Waiting on the period helper'],
            ['in_progress', '2026-10-06'],
        ], created: '2026-09-28', due: '2026-10-07');
        $this->task($project, $engine, 'Reopen detection', [], created: '2026-09-28', due: '2026-10-11');

        $this->task($project, $export, 'dompdf report template', [['in_progress', 'D 09:15']], created: '2026-09-28');
        $this->task($project, $export, 'XLSX export class', [
            ['in_progress', '2026-09-30'],
            ['in_progress', '2026-10-03', 'Sheets done, dates still strings'],
        ], created: '2026-09-28', due: '2026-10-11');
        $this->task($project, $export, 'PDF download route', [['done', '2026-10-06']], created: '2026-09-28');
        $this->task($project, $export, 'Report history page', [], created: '2026-09-28');

        $this->task($project, $deploy, 'Docker image', [
            ['in_progress', '2026-09-25'],
            ['blocked', '2026-10-05', 'Waiting on base image decision: PHP-FPM + Nginx vs single FrankenPHP container.'],
        ], created: '2026-09-24');
        $this->task($project, $deploy, 'Compose file', [], created: '2026-09-24');
        $this->task($project, $deploy, 'Back up the SQLite file', [], created: '2026-09-24');
    }

    private function homeLab(): void
    {
        $project = Project::query()->create(['name' => 'Home Lab', 'is_pinned' => true]);
        [$net, $storage, $power] = $this->modules($project, ['Networking', 'Storage', 'Power']);

        $this->task($project, $net, 'Reverse proxy for Nextcloud', [
            ['in_progress', '2026-09-28'],
            ['in_progress', '2026-10-04', 'Certs issued, routing next'],
        ], created: '2026-09-22');
        $this->task($project, $net, 'VLAN for IoT devices', [['in_progress', '2026-09-24'], ['done', '2026-10-06']], created: '2026-09-22');
        $this->task($project, $net, 'Wireguard config', [['in_progress', '2026-10-06']], created: '2026-09-22');
        $this->task($project, $net, 'Router firmware update', [], created: '2026-09-22', due: '2026-10-06');
        $this->task($project, $net, 'Pi-hole upgrade', [], created: '2026-09-22');

        $this->task($project, $storage, 'Backup rotation script', [['in_progress', '2026-09-26'], ['done', 'D 08:50']], created: '2026-09-22');
        $this->task($project, $storage, 'Nightly backup check', [
            ['in_progress', '2026-09-29'],
            ['done', '2026-10-02'],
            ['in_progress', '2026-10-07'],
        ], created: '2026-09-22');

        $this->task($project, $power, 'UPS shutdown hook', [['in_progress', '2026-09-26'], ['done', '2026-10-07']], created: '2026-09-22');
    }

    private function clientPortal(): void
    {
        $project = Project::query()->create(['name' => 'Client portal', 'is_pinned' => true]);
        [$auth, $billing] = $this->modules($project, ['Auth', 'Billing']);

        $this->task($project, $auth, 'Project setup', [['done', '2026-09-29']], created: '2026-09-21');
        $this->task($project, $auth, 'Auth scaffolding', [['done', '2026-10-01']], created: '2026-09-21');
        $this->task($project, $auth, 'Login page', [['in_progress', '2026-09-25'], ['done', '2026-10-05']], created: '2026-09-21');
        $this->task($project, $auth, 'Password reset flow', [], created: '2026-09-21');

        $this->task($project, $billing, 'Invoice PDF layout', [['in_progress', '2026-10-05']], created: '2026-09-21', due: '2026-10-09');
        $this->task($project, $billing, 'Payment history table', [], created: '2026-09-21');
    }

    /**
     * @param  list<string>  $names
     * @return list<Module>
     */
    private function modules(Project $project, array $names): array
    {
        return array_map(
            fn (string $name, int $i): Module => Module::query()->create(['project_id' => $project->id, 'name' => $name, 'position' => $i + 1]),
            $names,
            array_keys($names),
        );
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: string}>  $steps
     */
    private function task(Project $project, Module $module, string $title, array $steps, ?string $created = null, ?string $due = null): void
    {
        TaskHistory::make(
            $project,
            $title,
            array_map(fn (array $step): array => [$step[0], $this->at($step[1]), $step[2] ?? null], $steps),
            module: $module,
            createdOn: $created !== null ? $this->at($created) : null,
            due: $due !== null ? $this->at($due) : null,
        );
    }
}
