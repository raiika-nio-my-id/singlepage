<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use App\Models\CompanyProfileAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfileAlias>
 */
class CompanyProfileAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_profile_id' => CompanyProfile::factory(),
            'subdomain' => fake()->unique()->slug(2),
        ];
    }
}
