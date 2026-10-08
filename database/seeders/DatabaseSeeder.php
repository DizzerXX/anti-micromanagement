<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);

        if (! app()->environment('production') || (bool) env('LEDGER_SEED_SAMPLE_DATA', false)) {
            $this->call(SampleDataSeeder::class);
        }
    }
}
