<?php

namespace Database\Factories;

use App\Enums\UserRole;
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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            /*
             * VIEWER por defecto, igual que en la migracion.
             *
             * Un test que olvide declarar el rol debe ejercitar el caso
             * mas restrictivo. Si el valor por defecto fuera ADMIN, un
             * test de autorizacion que se olvidara de asignar un rol
             * pasaria siempre, y el fallo apareceria justo en el test
             * que pretendia comprobar que un VIEWER no puede escribir.
             */
            'role' => UserRole::VIEWER,
        ];
    }

    /**
     * Usuario con verificacion de correo pendiente.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ADMIN,
        ]);
    }

    public function analyst(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ANALYST,
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::VIEWER,
        ]);
    }
}
