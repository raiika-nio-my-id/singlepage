<?php

namespace App\Services;

use App\Enums\ProfileStatus;
use App\Enums\UserRole;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminClientService
{
    public function __construct(private readonly SubdomainService $subdomains) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CompanyProfile
    {
        $subdomain = $this->subdomains->validateAvailable((string) $data['subdomain']);

        return DB::transaction(function () use ($data, $subdomain): CompanyProfile {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => filled($data['password'] ?? null)
                    ? $data['password']
                    : Str::password(24),
                'role' => UserRole::Client,
            ]);

            $profile = $user->companyProfile()->create([
                'subdomain' => $subdomain,
                'html' => $data['html'],
                'status' => $this->statusFrom($data['status'] ?? null) ?? ProfileStatus::Draft,
                'expires_at' => $data['expires_at'] ?? now()->addYears((int) config('singlepage.subscription_years', 1))->toDateString(),
                'hide_footer' => (bool) ($data['hide_footer'] ?? false),
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->syncAliases($profile, Arr::get($data, 'aliases'));

            return $profile->load(['user', 'aliases']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyProfile $profile, array $data): CompanyProfile
    {
        return DB::transaction(function () use ($profile, $data): CompanyProfile {
            $profile->loadMissing(['user', 'aliases']);

            $payload = [];
            $formerSubdomain = $profile->subdomain;

            if (array_key_exists('html', $data)) {
                $payload['html'] = $data['html'];
            }

            if (array_key_exists('status', $data)) {
                $payload['status'] = $this->statusFrom($data['status']) ?? $profile->status;
            }

            if (array_key_exists('expires_at', $data)) {
                $payload['expires_at'] = $data['expires_at'];
            }

            if (array_key_exists('hide_footer', $data)) {
                $payload['hide_footer'] = (bool) $data['hide_footer'];
            }

            if (array_key_exists('is_pinned', $data)) {
                $payload['is_pinned'] = (bool) $data['is_pinned'];
            }

            if (array_key_exists('sort_order', $data)) {
                $payload['sort_order'] = (int) $data['sort_order'];
            }

            if (array_key_exists('subdomain', $data)) {
                $payload['subdomain'] = $this->subdomains->preparePrimaryChange(
                    $profile,
                    (string) $data['subdomain'],
                );
            }

            if ($payload !== []) {
                $profile->update($payload);
            }

            if (array_key_exists('subdomain', $payload)) {
                $this->subdomains->rememberFormerPrimary($profile, $formerSubdomain);
            }

            $credentials = [];

            if (array_key_exists('name', $data)) {
                $credentials['name'] = $data['name'];
            }

            if (array_key_exists('email', $data)) {
                $credentials['email'] = $data['email'];
            }

            if (array_key_exists('password', $data)) {
                $credentials['password'] = $data['password'];
            }

            if ($credentials !== [] && $profile->user) {
                $profile->user->update($credentials);
            }

            if (array_key_exists('aliases', $data)) {
                $this->syncAliases($profile, $data['aliases']);
            }

            return $profile->refresh()->load(['user', 'aliases']);
        });
    }

    public function findBySubdomain(string $subdomain): CompanyProfile
    {
        $profile = CompanyProfile::query()
            ->where('subdomain', $this->subdomains->normalize($subdomain))
            ->first();

        abort_unless($profile, 404, 'Client not found.');

        return $profile;
    }

    /**
     * @param  list<string>|null  $aliases
     */
    private function syncAliases(CompanyProfile $profile, mixed $aliases): void
    {
        if ($aliases === null) {
            return;
        }

        if (! is_array($aliases)) {
            throw ValidationException::withMessages([
                'aliases' => 'Aliases must be an array of subdomains.',
            ]);
        }

        $normalized = [];

        foreach ($aliases as $alias) {
            try {
                $normalized[] = $this->subdomains->validateAvailableAlias((string) $alias, $profile);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'aliases' => $exception->errors()['alias'] ?? ['This alias is invalid or already in use.'],
                ]);
            }
        }

        $normalized = array_values(array_unique($normalized));

        $existing = $profile->aliases();

        if ($normalized !== []) {
            $existing->whereNotIn('subdomain', $normalized);
        }

        $existing->delete();

        foreach ($normalized as $subdomain) {
            $profile->aliases()->firstOrCreate(['subdomain' => $subdomain]);
        }
    }

    private function statusFrom(mixed $status): ?ProfileStatus
    {
        if ($status instanceof ProfileStatus) {
            return $status;
        }

        if (is_string($status)) {
            return ProfileStatus::tryFrom($status);
        }

        return null;
    }
}
