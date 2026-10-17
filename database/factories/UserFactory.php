<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Usuarios inventados para pruebas (nunca datos reales: Ley 25.326).
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'dni' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'apellido' => fake()->lastName(),
            'nombre' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    /** Usuario con un rol: User::factory()->conRol('profesor')->create() */
    public function conRol(string $rol): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($rol));
    }
}
