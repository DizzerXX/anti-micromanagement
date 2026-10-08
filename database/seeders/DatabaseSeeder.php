<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);

        if (! app()->environment('production') || (bool) config('ledger.seed_sample_data')) {
            $this->call(SampleDataSeeder::class);
        }
    }
}
