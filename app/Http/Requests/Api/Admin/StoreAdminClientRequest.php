<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\ProfileStatus;
use App\Rules\Subdomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'subdomain' => ['required', 'string', 'max:63', new Subdomain],
            'html' => ['required', 'string'],
            'status' => ['sometimes', Rule::enum(ProfileStatus::class)],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'hide_footer' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'aliases' => ['sometimes', 'array'],
            'aliases.*' => ['string', 'max:63', new Subdomain],
        ];
    }
}
