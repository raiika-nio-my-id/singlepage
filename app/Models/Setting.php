<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * @return list<string>
     */
    public static function reservedSubdomains(): array
    {
        $fromSettings = Collection::make(preg_split('/\R/', static::getValue('reserved_subdomains', '') ?? ''))
            ->map(fn (string $value): string => strtolower(trim($value)))
            ->filter()
            ->unique()
            ->values();

        return Collection::make(config('singlepage.reserved_subdomains', []))
            ->map(fn (string $value): string => strtolower(trim($value)))
            ->merge($fromSettings)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
