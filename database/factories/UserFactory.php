<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UserFactory — canonical admin test fixture.
 *
 * Default password is the literal string `password` so tests can
 * construct the matching plaintext without re-reading the factory.
 *
 * @extends Factory<\App\Models\User>
 */
final class UserFactory extends Factory
{
    public const DEFAULT_PASSWORD='password';

    protected $model = \App\Models\User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'role' => 'admin',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): self
    {
        return $this->state(static fn (): array => [
            'email_verified_at' => null,
        ]);
    }
}
