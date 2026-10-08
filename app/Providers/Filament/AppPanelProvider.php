<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\GlobalSearch\LedgerSearchProvider;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Today;
use App\Filament\Resources\Projects\ProjectResource;
use App\Http\Controllers\ReportDownloadController;
use App\Livewire\Topbar;
use App\Models\Project;
use Filament\Enums\UserMenuPosition;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->login()
            ->brandName('Ledger')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->homeUrl(fn (): string => Today::getUrl())
            ->viteTheme('resources/css/filament/app/theme.css')
            ->font('IBM Plex Sans', provider: LocalFontProvider::class)
            ->monoFont('IBM Plex Mono', provider: LocalFontProvider::class)
            // Values from docs/design/tokens.css (Tokens.html, "Filament mapping").
            // Filament needs real colour values to build its palettes; Blade and
            // CSS use the token custom properties instead.
            ->colors([
                'primary' => Color::Stone,
                'gray' => Color::Stone,
                'todo' => Color::hex('#57554E'),
                'progress' => Color::hex('#1D4FA3'),
                'blocked' => Color::hex('#9A4A00'),
                'done' => Color::hex('#1E6B43'),
                'danger' => Color::hex('#A8261B'),
                'success' => Color::hex('#1E6B43'),
            ])
            ->darkMode()
            ->topbar(false)
            ->userMenu(position: UserMenuPosition::Topbar)
            ->sidebarWidth('15rem')
            ->maxContentWidth(Width::Full)
            ->globalSearch(LedgerSearchProvider::class)
            ->globalSearchKeyBindings(['/'])
            ->globalSearchFieldSuffix('/')
            ->globalSearchDebounce('300ms')
            ->unsavedChangesAlerts(false)
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $this->buildNavigation($builder))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Today::class])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => (string) app(Vite::class)->fonts())
            ->renderHook(PanelsRenderHook::CONTENT_BEFORE, fn (): string => Livewire::mount(Topbar::class))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): View => view('filament.sidebar-footer'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.body-end'))
            ->authenticatedRoutes(function (): void {
                Route::get('reports/{report}/download/{file}', ReportDownloadController::class)
                    ->whereIn('file', ['pdf', 'xlsx'])
                    ->name('reports.download');
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Sidebar (HANDOFF §1): Today, Projects, Reports; "Pinned projects" with
     * open-task counts. Settings sits in the sidebar footer.
     */
    private function buildNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $pinned = Project::query()
            ->active()
            ->where('is_pinned', true)
            ->withCount('openTasks')
            ->orderBy('id')
            ->get();

        return $builder
            ->items([
                NavigationItem::make('Today')
                    ->icon('ledger-calendar')
                    ->url(fn (): string => Today::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs(Today::getRouteName())),
                NavigationItem::make('Projects')
                    ->icon('ledger-folder')
                    ->url(fn (): string => ProjectResource::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs(ProjectResource::getRouteBaseName().'.*')),
                NavigationItem::make('Reports')
                    ->icon('ledger-report')
                    ->url(fn (): string => Reports::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs(Reports::getRouteName())),
            ])
            ->groups($pinned->isEmpty() ? [] : [
                NavigationGroup::make('Pinned projects')
                    ->collapsible(false)
                    ->items($pinned->map(fn (Project $project): NavigationItem => NavigationItem::make($project->name)
                        ->url(ProjectResource::getUrl('view', ['record' => $project]))
                        ->badge($project->open_tasks_count.' open')
                        ->isActiveWhen(fn (): bool => $this->isViewing($project)))
                        ->all()),
            ]);
    }

    private function isViewing(Project $project): bool
    {
        $record = request()->route('record');

        return request()->routeIs(ProjectResource::getRouteBaseName().'.view')
            && (string) ($record instanceof Project ? $record->getKey() : $record) === (string) $project->getKey();
    }
}
