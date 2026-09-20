<?php

namespace App\Models;

use App\Enums\ProfileStatus;
use Database\Factories\CompanyProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'subdomain', 'html', 'status', 'expires_at', 'hide_footer'])]
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return sprintf('%s://%s.%s', $scheme, $subdomain, config('singlepage.base_domain'));
    }

    public function publicUrl(): string
    {
        return static::publicUrlFor($this->subdomain);
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
