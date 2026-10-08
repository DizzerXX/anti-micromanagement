<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('ledger.user.email')],
            [
                'name' => config('ledger.user.name'),
                'password' => config('ledger.user.password'),
            ],
        );
    }
}
