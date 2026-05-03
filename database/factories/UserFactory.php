<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            //'account_type' => $this->faker->randomElement(['student', 'organisation', 'mentor', 'internal']),
            'status' => 'active',
            'email_verified_at' => now(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'language_preference' => $this->faker->randomElement(['sk', 'en'])
        ];
    }
}
