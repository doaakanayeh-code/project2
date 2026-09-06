<?php

namespace Database\Factories;

use App\Models\ProviderService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderService>
 */
class ProviderServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
   public function definition(): array
{
    return [
        'user_id' => 4, // افترضي وجود مستخدم ID=1
        'service_id' => \App\Models\Service::inRandomOrder()->first()->id,
        'location_id' => \App\Models\Location::inRandomOrder()->first()->id,
        'price' => fake()->randomFloat(2, 1000, 100000),
       
        'description' => fake()->paragraph(),
        'status' => 'active',
        'features' => 'wifi, parking, catering',
    ];
}
}
