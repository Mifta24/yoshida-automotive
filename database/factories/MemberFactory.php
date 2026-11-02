<?php

namespace Database\Factories;

use App\Core\Member\Models\Member;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_type' => (new Workshop)->getMorphClass(),
            'tenant_id' => Workshop::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'locale' => null,
            'email_verified_at' => now(),
        ];
    }

    public function forWorkshop(Workshop $workshop): static
    {
        return $this->state(['tenant_type' => $workshop->getMorphClass(), 'tenant_id' => $workshop->id]);
    }

    public function withoutProfile(): static
    {
        return $this->state(['name' => null]);
    }
}
