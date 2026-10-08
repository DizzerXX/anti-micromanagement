<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Services\TodayOverview;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

/**
 * Home page (HANDOFF §2, mockups/Today.html).
 */
class Today extends Page
{
    protected static string $routePath = '/';

    protected static ?string $title = 'Today';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.today';

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    #[On('task-logged')]
    public function refreshAfterLog(): void
    {
        // Re-render with fresh numbers.
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $overview = app(TodayOverview::class);

        if (! $overview->hasProjects()) {
            return ['empty' => true, 'createProjectUrl' => ProjectResource::getUrl('create')];
        }

        $week = $overview->week();

        return [
            'empty' => false,
            'today' => CarbonImmutable::today(),
            'week' => $week,
            'needsUpdate' => array_slice($week->stale, 0, TodayOverview::NEEDS_UPDATE_LIMIT),
            'loggedToday' => $overview->loggedToday(),
            'due' => $overview->dueAndOverdue($week),
            'lastReport' => $overview->lastReport(),
        ];
    }
}
