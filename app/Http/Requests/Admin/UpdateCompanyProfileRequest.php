<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProfileStatus;
use App\Models\CompanyProfile;
use App\Rules\Subdomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->profile();

        return $profile instanceof CompanyProfile
            && $this->user()?->can('update', $profile);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $profile = $this->profile();

        $rules = [
            'html' => ['required', 'string'],
            'status' => ['nullable', Rule::enum(ProfileStatus::class)],
            'admin_alias' => ['nullable', 'string', 'max:120'],
        ];

        if ($profile && $this->user()?->can('updateSubdomain', $profile)) {
            $rules['subdomain'] = ['required', 'string', 'max:63', new Subdomain];
        }

        if ($profile && $this->user()?->can('updateExpiration', $profile)) {
            $rules['expires_at'] = ['required', 'date'];
        }

        if ($profile && $this->user()?->can('updateCredentials', $profile)) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($profile->user_id),
            ];
            $rules['password'] = ['nullable', 'string', Password::defaults()];
        }

        return $rules;
    }

    public function published(): bool
    {
        return $this->input('status') === ProfileStatus::Published->value;
    }

    public function profile(): ?CompanyProfile
    {
        $profile = $this->route('companyProfile');

        if ($profile instanceof CompanyProfile) {
            return $profile;
        }

        return $this->user()?->isClient() ? $this->user()->companyProfile : null;
    }
}
