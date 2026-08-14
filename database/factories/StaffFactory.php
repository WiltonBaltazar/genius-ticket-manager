<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(StaffRole::cases())->value,
            'email_verified_at' => now(),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => StaffRole::SuperAdmin->value]);
    }

    public function eventManager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => StaffRole::EventManager->value]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => ['role' => StaffRole::Support->value]);
    }

    public function gateOperator(): static
    {
        return $this->state(fn (array $attributes) => ['role' => StaffRole::GateOperator->value]);
    }
}
