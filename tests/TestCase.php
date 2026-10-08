<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SampleDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Freeze the clock on the mockup's "today" and sign in.
     */
    protected function signInOnMockupDay(bool $withSampleData = true): User
    {
        $this->travelTo(CarbonImmutable::parse(SampleDataSeeder::MOCKUP_TODAY.' 15:00'));

        if ($withSampleData) {
            $this->seed(SampleDataSeeder::class);
        }

        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        return $user;
    }
}
