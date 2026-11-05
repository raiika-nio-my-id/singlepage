<?php

namespace App\Models;

use App\Enums\ProfileStatus;
use App\Services\GameHub;
use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'subdomain', 'html', 'status', 'expires_at', 'hide_footer', 'sort_order', 'is_pinned'])]
class CompanyProfile extends Model
{
    /** @use HasFactory<CompanyProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'expires_at' => 'date',
            'hide_footer' => 'boolean',
            'is_pinned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CompanyProfileAlias::class);
    }

    public function isPublished(): bool
    {
        return $this->status === ProfileStatus::Published;
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon
            && $this->expires_at->endOfDay()->isPast();
    }

    public function isLive(): bool
    {
        return $this->isPublished() && ! $this->isExpired();
    }

    public static function publicUrlFor(string $subdomain): string
    {
        if (GameHub::isSubdomain($subdomain)) {
            return GameHub::publicUrl();
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return sprintf('%s://%s.%s', $scheme, $subdomain, config('singlepage.base_domain'));
    }

    public function publicUrl(): string
    {
        return static::publicUrlFor($this->subdomain);
    }

    /**
     * Host, plus path for GameHub, without a scheme. Safe to show in the admin.
     */
    public function publicLabel(): string
    {
        $parts = parse_url($this->publicUrl());
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';

        if ($path === '' || $path === '/') {
            return $host;
        }

        return $host.rtrim($path, '/');
    }

    /**
     * @param  Builder<CompanyProfile>  $query
     * @return Builder<CompanyProfile>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProfileStatus::Published);
    }

    /**
     * @param  Builder<CompanyProfile>  $query
     * @return Builder<CompanyProfile>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->published()
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            });
    }
}
