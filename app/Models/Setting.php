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
     * Shared GM key for in-room claim and (transitional) private lobby listing.
     * A non-empty admin-saved value wins; otherwise the env/config fallback is used.
     */
    public static function gamemasterSecret(): string
    {
        $fromSettings = trim((string) (static::getValue('gamemaster_secret') ?? ''));

        return $fromSettings !== ''
            ? $fromSettings
            : (string) config('games.gamemaster_secret');
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
