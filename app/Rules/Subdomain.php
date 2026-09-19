<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Subdomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(?=.{1,63}$)[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', strtolower($value))) {
            $fail('The :attribute must be a valid DNS label (letters, numbers, hyphens).');
        }
    }
}
