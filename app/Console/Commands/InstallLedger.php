<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Project;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;

/**
 * Run on every container start: migrate, make sure the user from .env
 * exists, and seed sample data once if asked to.
 */
class InstallLedger extends Command
{
    protected $signature = 'ledger:install {--sample : Seed the sample projects when the database has none}';

    protected $description = 'Migrate the database and create the Ledger user from .env';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--class' => UserSeeder::class, '--force' => true]);

        if ($this->option('sample') && ! Project::query()->exists()) {
            $this->call('db:seed', ['--class' => SampleDataSeeder::class, '--force' => true]);
        }

        $this->info('Ledger is ready. Sign in as '.config('ledger.user.email').'.');

        return self::SUCCESS;
    }
}
