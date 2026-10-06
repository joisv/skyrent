<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Affiliate>
 */
class AffiliateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->city() . ' Branch';
        return [
            'name' => $name,
            'code' => strtoupper($this->faker->unique()->bothify('???')),
            'slug' => \Illuminate\Support\Str::slug($name),
        ];
    }
}
