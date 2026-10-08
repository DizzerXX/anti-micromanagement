<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Settings extends Page
{
    protected static ?string $title = 'Settings';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.settings';
}
