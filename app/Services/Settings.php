<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WeekStart;
use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The two app settings (SPEC §3), stored as key/value rows and cached.
 */
class Settings
{
    private const CACHE_KEY = 'ledger.settings';

    public function __construct(private readonly Cache $cache) {}

    public function weekStart(): WeekStart
    {
        return WeekStart::tryFrom((string) $this->all()['week_start']) ?? WeekStart::Monday;
    }

    public function staleDays(): int
    {
        return max(1, (int) $this->all()['stale_days']);
    }

    public function update(WeekStart $weekStart, int $staleDays): void
    {
        if ($staleDays < 1 || $staleDays > 60) {
            throw new \InvalidArgumentException('Stale days must be between 1 and 60.');
        }

        Setting::query()->updateOrCreate(['key' => 'week_start'], ['value' => $weekStart->value]);
        Setting::query()->updateOrCreate(['key' => 'stale_days'], ['value' => (string) $staleDays]);

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array{week_start: string, stale_days: int|string}
     */
    private function all(): array
    {
        /** @var array{week_start: string, stale_days: int|string} */
        return $this->cache->rememberForever(self::CACHE_KEY, function (): array {
            $stored = Setting::query()->pluck('value', 'key')->all();

            return array_merge(config('ledger.defaults'), $stored);
        });
    }
}
