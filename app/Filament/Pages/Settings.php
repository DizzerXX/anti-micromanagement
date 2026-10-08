<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\TaskStatus;
use App\Enums\WeekStart;
use App\Services\Settings as SettingsStore;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Settings (Sitemap.html): week start and stale days. Statuses are a fixed
 * set of four and are shown for reference.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static ?string $title = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(SettingsStore $settings): void
    {
        $this->form->fill([
            'week_start' => $settings->weekStart()->value,
            'stale_days' => $settings->staleDays(),
        ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('week_start')
                    ->label('Week starts on')
                    ->belowLabel('Weekly reports and the Today page run from this day through the 7th day.')
                    ->options(collect(WeekStart::cases())->mapWithKeys(fn (WeekStart $d): array => [$d->value => $d->label()])->all())
                    ->selectablePlaceholder(false)
                    ->required(),
                TextInput::make('stale_days')
                    ->label('Stale after')
                    ->belowLabel('In-progress tasks with no update for this many days show under Needs an update and No update for N+ days.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(60)
                    ->suffix('days')
                    ->required()
                    ->extraInputAttributes(['class' => 'ld-mono'])
                    ->validationMessages([
                        'min' => 'Use at least 1 day.',
                        'max' => 'Use 60 days or fewer.',
                        'required' => 'Enter a number of days.',
                    ]),
            ]);
    }

    public function save(SettingsStore $settings): void
    {
        $state = $this->form->getState();

        $settings->update(WeekStart::from($state['week_start']), (int) $state['stale_days']);

        Notification::make()->title('Settings saved.')->body('Reports and the Today page use them from now on.')->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['statuses' => TaskStatus::cases()];
    }
}
