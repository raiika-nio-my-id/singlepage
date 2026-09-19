<?php

namespace App\Services;

use App\Models\CompanyProfile;
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

        return $value;
    }
}
