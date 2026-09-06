<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // التعديل الأساسي هنا: استبدال name بـ username
            'username' => fake()->userName(), 
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(), // أضفت الهاتف لأنه موجود بجدولك
            'email_verified_at' => now(),
            
            // حقول الصور إجبارية بجدولك حالياً، لازم نعطيها قيم وهمية
            'id_img_front' => 'default_id_front.jpg', 
            'id_img_back' => 'default_id_back.jpg',
            
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user', // القيمة الافتراضية
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}