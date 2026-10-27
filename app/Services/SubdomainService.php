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

        // Block aliases that belong to other profiles. This profile's own
        // aliases can be promoted to primary (the alias row is dropped on save).
        $aliasTaken = CompanyProfileAlias::query()
            ->where('subdomain', $value)
            ->when($ignoreProfileId, fn ($query) => $query->where('company_profile_id', '!=', $ignoreProfileId))
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

        // Must not conflict with another profile's primary. This profile's own
        // primary is allowed so it can be staged as an alias before a swap.
        $foreignPrimary = CompanyProfile::query()
            ->where('subdomain', $value)
            ->where('id', '!=', $forProfile->id)
            ->exists();

        if ($foreignPrimary) {
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

    /**
     * Validate a new primary and drop this profile's matching alias so the
     * name can be promoted without a uniqueness clash.
     */
    public function preparePrimaryChange(CompanyProfile $profile, string $value): string
    {
        $newSubdomain = $this->validateAvailable($value, $profile->id);

        if ($newSubdomain !== $this->normalize($profile->subdomain)) {
            $profile->aliases()->where('subdomain', $newSubdomain)->delete();
        }

        return $newSubdomain;
    }

    /**
     * Keep a former primary as an alias so the old host 301s to the new one.
     */
    public function rememberFormerPrimary(CompanyProfile $profile, string $formerSubdomain): void
    {
        $formerSubdomain = $this->normalize($formerSubdomain);
        $current = $this->normalize($profile->subdomain);

        if ($formerSubdomain === '' || $formerSubdomain === $current) {
            return;
        }

        $profile->aliases()->firstOrCreate(['subdomain' => $formerSubdomain]);
    }
}
