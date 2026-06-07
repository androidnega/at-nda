<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Student>
 *
 * Defaults emit a fully-populated student row (first_name + last_name) so
 * the Student::creating event leaves the names intact (the event clears
 * the profile fields when ALL name parts are null, modelling the
 * index-only self-registration flow).
 *
 * The default password is bcrypt-of-'password' (cached across factory
 * instantiations), which exercises the PasswordPolicy::matches()
 * bcrypt path in P1.T19 / P1.T20.
 *
 * index_number is generated unique via faker; the Student::saving event
 * uppercases it automatically, so callers may pass mixed-case overrides
 * without breaking the unique index.
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /** Shared bcrypt cost: hashed once per process to keep test runs fast. */
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'index_number' => 'BC/STU/'.now()->format('y').'/'
                .fake()->unique()->numerify('####'),
            'first_name'   => fake()->firstName(),
            'last_name'    => fake()->lastName(),
            'password'     => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Helper state: detach the student from any class so tests can
     * assert lookup-by-index behaviour without a class context.
     */
    public function withoutClass(): static
    {
        return $this->state(fn () => ['class_id' => null]);
    }
}
