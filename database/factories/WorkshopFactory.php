<?php

namespace Database\Factories;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workshop>
 */
class WorkshopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Workshop',
            'slug' => fake()->unique()->slug(2),
            'currency' => 'IDR',
            'default_locale' => 'id',
            'public_status' => 'published',
        ];
    }

    public function draft(): static
    {
        return $this->state(['public_status' => 'draft']);
    }

    public function withLoyalty(): static
    {
        return $this->state(['features' => [Workshop::FEATURE_MEMBER, Workshop::FEATURE_MEMBER_PLUS]]);
    }

    public function withMembers(): static
    {
        return $this->state(['features' => [Workshop::FEATURE_MEMBER]]);
    }
}
