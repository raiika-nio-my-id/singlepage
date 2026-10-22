<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProfileStatus;
use App\Rules\Subdomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\CompanyProfile::class) ?? false;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', Password::defaults()],
            'subdomain' => ['required', 'string', 'max:63', new Subdomain],
            'html' => ['required', 'string'],
            'status' => ['nullable', Rule::enum(ProfileStatus::class)],
            'expires_at' => ['required', 'date'],
        ];
    }

    public function published(): bool
    {
        return $this->input('status') === ProfileStatus::Published->value;
    }
}
