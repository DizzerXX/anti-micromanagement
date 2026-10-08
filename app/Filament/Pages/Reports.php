<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $title = 'Reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.reports';
}
