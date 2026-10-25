<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use App\Models\GameScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameScore>
 */
class GameScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_profile_id' => CompanyProfile::factory(),
            'name' => fake()->unique()->regexify('[A-Za-z]{1,16}'),
            'score' => fake()->numberBetween(0, 1_000_000),
        ];
    }
}
