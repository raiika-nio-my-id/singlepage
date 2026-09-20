<?php

namespace Database\Factories;

use App\Enums\ProfileStatus;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProfile>
 */
class CompanyProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $subdomain = fake()->unique()->slug(2);

        return [
            'user_id' => User::factory(),
            'subdomain' => substr($subdomain, 0, 63),
            'html' => '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'.e($name).'</title></head><body><h1>'.e($name).'</h1></body></html>',
            'status' => ProfileStatus::Draft,
            'expires_at' => now()->addYear()->toDateString(),
            'hide_footer' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProfileStatus::Published,
        ]);
    }

    public function hiddenFooter(): static
    {
        return $this->state(fn (array $attributes) => [
            'hide_footer' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay()->toDateString(),
        ]);
    }
}
