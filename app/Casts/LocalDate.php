<?php

declare(strict_types=1);

namespace App\Casts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A calendar date in the app timezone, stored as `Y-m-d` so SQL comparisons
 * against date strings are exact.
 *
 * @implements CastsAttributes<Carbon, CarbonInterface|string>
 */
class LocalDate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse(substr((string) $value, 0, 10))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof CarbonInterface ? $value : CarbonImmutable::parse((string) $value))->toDateString();
    }
}
