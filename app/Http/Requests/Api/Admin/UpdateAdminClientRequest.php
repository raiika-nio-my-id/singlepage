<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\ProfileStatus;
use App\Models\CompanyProfile;
use App\Rules\Subdomain;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (is_string($this->route('subdomain'))) {
            abort_unless($this->profile() instanceof CompanyProfile, 404, 'Client not found.');
        }

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $profile = $this->profile();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($profile?->user_id),
            ],
            'password' => ['sometimes', 'string', Password::defaults()],
            'subdomain' => ['sometimes', 'string', 'max:63', new Subdomain],
            'html' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(ProfileStatus::class)],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'hide_footer' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:63', new Subdomain],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->safe()->all() === []) {
                    $validator->errors()->add('payload', 'At least one updatable field is required.');
                }
            },
        ];
    }

    public function profile(): ?CompanyProfile
    {
        $profile = $this->route('companyProfile');

        if ($profile instanceof CompanyProfile) {
            return $profile;
        }

        $subdomain = $this->route('subdomain');

        if (! is_string($subdomain) || $subdomain === '') {
            return null;
        }

        return CompanyProfile::query()
            ->where('subdomain', strtolower(trim($subdomain)))
            ->first();
    }
}
