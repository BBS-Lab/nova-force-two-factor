<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Workbench\App\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @var class-string<User>
     */
    protected $model = User::class;

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
            'two_factor_enabled' => false,
            // Fresh by default, so a user is not expired unless asked to be.
            'password_changed_at' => now(),
        ];
    }

    /**
     * The admin has fully enrolled in two-factor authentication.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (): array => ['two_factor_enabled' => true]);
    }

    /**
     * The admin's password is past the rotation window (needs changing).
     */
    public function passwordExpired(): static
    {
        return $this->state(fn (): array => ['password_changed_at' => now()->subDays(200)]);
    }
}
