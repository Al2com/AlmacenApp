<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

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
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'rol' => Rol::OperadorAlmacen,
            'activo' => true,
            'debe_cambiar_password' => false,
        ];
    }

    /**
     * Indicate that the account must change its password on first login.
     */
    public function debeCambiarPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'debe_cambiar_password' => true,
        ]);
    }
}
