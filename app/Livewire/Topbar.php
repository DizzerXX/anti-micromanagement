<?php

declare(strict_types=1);

namespace App\Livewire;

use Filament\Livewire\Topbar as FilamentTopbar;
use Illuminate\Contracts\View\View;

/**
 * The bar above page content (mockups: search on the left, account on the
 * right). Reuses Filament's topbar component for the user menu actions.
 */
class Topbar extends FilamentTopbar
{
    public function render(): View
    {
        return view('livewire.topbar');
    }
}
