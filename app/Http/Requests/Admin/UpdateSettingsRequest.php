<?php

namespace App\Http\Requests\Admin;

use App\Rules\Subdomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSettings', \App\Models\CompanyProfile::class) ?? false;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'reserved_subdomains' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->reservedLines() as $line) {
                $rule = new Subdomain;
                $rule->validate('reserved_subdomains', $line, function (string $message) use ($validator, $line): void {
                    $validator->errors()->add('reserved_subdomains', "Invalid reserved subdomain: {$line}. {$message}");
                });
            }
        });
    }

    /**
     * @return list<string>
     */
    public function reservedLines(): array
    {
        return Collection::make(preg_split('/\R/', (string) $this->input('reserved_subdomains', '')))
            ->map(fn (string $line): string => strtolower(trim($line)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
