<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use App\Models\Setting;
use App\Rules\Subdomain;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubdomainService
{
    public function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Validate and return a normalised primary subdomain.
     * Checks format, reserved names, and uniqueness across both primary
     * subdomains and alias subdomains.
     *
     * @param  int|null  $ignoreProfileId  Exclude this profile's own primary subdomain from uniqueness checks (for updates).
     */
    public function validateAvailable(string $value, ?int $ignoreProfileId = null): string
    {
        $value = $this->normalize($value);

        Validator::make(
            ['subdomain' => $value],
            ['subdomain' => ['required', 'string', 'max:63', new Subdomain]],
        )->validate();

        if (in_array($value, Setting::reservedSubdomains(), true)) {
            throw ValidationException::withMessages([
                'subdomain' => 'This subdomain is reserved.',
            ]);
        }

        $taken = CompanyProfile::query()
            ->where('subdomain', $value)
            ->when($ignoreProfileId, fn ($query) => $query->where('id', '!=', $ignoreProfileId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'subdomain' => 'This subdomain is already in use.',
            ]);
        }

        // Also block a primary subdomain that's already claimed as an alias.
        $aliasTaken = CompanyProfileAlias::query()
            ->where('subdomain', $value)
            ->exists();

        if ($aliasTaken) {
            throw ValidationException::withMessages([
                'subdomain' => 'This subdomain is already in use.',
            ]);
        }

        return $value;
    }

    /**
     * Validate and return a normalised alias subdomain for the given profile.
     * Checks format, reserved names, and uniqueness across primary and alias
     * subdomains (globally).
     *
     * @param  int|null  $ignoreAliasId  Exclude this alias row from the alias uniqueness check (for updates).
     */
    public function validateAvailableAlias(string $value, CompanyProfile $forProfile, ?int $ignoreAliasId = null): string
    {
        $value = $this->normalize($value);

        Validator::make(
            ['alias' => $value],
            ['alias' => ['required', 'string', 'max:63', new Subdomain]],
        )->validate();

        if (in_array($value, Setting::reservedSubdomains(), true)) {
            throw ValidationException::withMessages([
                'alias' => 'This subdomain is reserved.',
            ]);
        }

        // Must not conflict with any primary subdomain.
        if (CompanyProfile::query()->where('subdomain', $value)->exists()) {
            throw ValidationException::withMessages([
                'alias' => 'This subdomain is already in use.',
            ]);
        }

        // Must not conflict with any other alias.
        if (CompanyProfileAlias::query()
            ->where('subdomain', $value)
            ->when($ignoreAliasId, fn ($q) => $q->where('id', '!=', $ignoreAliasId))
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'alias' => 'This subdomain is already in use.',
            ]);
        }

        return $value;
    }
}
