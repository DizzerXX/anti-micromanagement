<?php

declare(strict_types=1);

namespace App\Providers;

use BladeUI\Icons\Factory as IconFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Icons drawn for the design (resources/svg/ledger), used as `ledger-<name>`.
        $this->callAfterResolving(IconFactory::class, function (IconFactory $factory): void {
            $factory->add('ledger', [
                'path' => resource_path('svg/ledger'),
                'prefix' => 'ledger',
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
