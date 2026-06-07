<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 security regression suite.
 *
 * Each test fails fast if any future PR re-opens a per-class endpoint,
 * removes the api-v1-login throttler, leaks PII into the anti-enumeration
 * funnel, or changes the documented error envelope.
 *
 * NB: Two tests deliberately deviate from the master implementation
 * guide (TASK 20):
 *   - test_login_returns_specific_error_codes_per_phase_1_contract
 *     was test_login_returns_generic_invalid_credentials in the guide.
 *     Phase 1 / P1.T03 explicitly preserved the existing Flutter error
 *     envelope (`error_code: student_not_found` + 404 for unknown
 *     index, `message: 'Wrong password'` + 401 for bad password). The
 *     audit's recommendation to collapse both into a generic
 *     `invalid_credentials` was DELIBERATELY NOT applied because it
 *     would have changed the contract Flutter parses to drive the
 *     "set password first" and "removed account" prompts.
 *   - test_me_works_with_credentials_in_body_per_option_b was
 *     test_me_requires_bearer in the guide. Phase 1 / P1.T10 chose
 *     Option B for /api/me: keep it OUTSIDE auth:sanctum so the
 *     existing Flutter cold-start refresh (which posts credentials,
 *     not a Bearer) keeps working. /api/me delegates to /api/login.
 */
class StudentsAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_index_requires_sanctum(): void
    {
        $this->getJson('/api/students?class_id=1')
            ->assertStatus(401);
    }

    public function test_students_lookup_requires_sanctum(): void
    {
        $this->postJson('/api/students/lookup', ['index_number' => 'BC/ITD/24/047'])
            ->assertStatus(401);
    }

    public function test_students_quick_status_works_without_auth_and_redacts_pii(): void
    {
        // Unique REMOTE_ADDR isolates this test's slot in the
        // api-v1-login throttler bucket so later throttle-sensitive
        // tests start from a fresh five-per-minute budget.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10']);

        $class = SchoolClass::factory()->create();
        $student = Student::factory()->create([
            'index_number' => 'BC/ITD/24/047',
            'class_id'     => $class->id,
            'phone_number' => '0244000000',
            'password'     => bcrypt('12345'),
        ]);

        $response = $this->postJson('/api/students/quick-status', [
            'index_number' => $student->index_number,
        ])->assertOk();

        $payload = $response->json();
        $this->assertSame(true, $payload['found']);
        $this->assertSame(true, $payload['has_password']);
        $this->assertArrayNotHasKey('phone_number', $payload);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('faculty', $payload);
    }

    public function test_students_index_returns_only_my_class(): void
    {
        $myClass    = SchoolClass::factory()->create();
        $otherClass = SchoolClass::factory()->create();

        $me = Student::factory()->create([
            'class_id' => $myClass->id,
            'password' => bcrypt('pw'),
        ]);
        Student::factory()->count(3)->create(['class_id' => $myClass->id]);
        Student::factory()->count(5)->create(['class_id' => $otherClass->id]);

        $token = $me->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/students')
            ->assertOk();
        $this->assertCount(4, $response->json()); // me + 3 classmates

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/students?class_id='.$otherClass->id)
            ->assertStatus(403);
    }

    public function test_phone_number_is_hidden_from_regular_student_callers(): void
    {
        $class = SchoolClass::factory()->create();
        $me = Student::factory()->create([
            'class_id' => $class->id,
            'password' => bcrypt('pw'),
        ]);
        Student::factory()->create([
            'class_id'     => $class->id,
            'phone_number' => '0244111222',
        ]);

        $token = $me->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/students')
            ->assertOk();

        foreach ($response->json() as $row) {
            $this->assertArrayNotHasKey('phone_number', $row);
            $this->assertArrayNotHasKey('phone', $row);
        }
    }

    public function test_sessions_active_requires_auth_and_rejects_arbitrary_class_id(): void
    {
        $this->getJson('/api/sessions/active?class_id=1')->assertStatus(401);

        $classA = SchoolClass::factory()->create();
        $classB = SchoolClass::factory()->create();
        $me = Student::factory()->create([
            'class_id' => $classA->id,
            'password' => bcrypt('pw'),
        ]);
        $token = $me->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/sessions/active?class_id='.$classB->id)
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/sessions/active?class_id='.$classA->id)
            ->assertOk();
    }

    public function test_login_is_throttled_to_five_per_minute_per_ip(): void
    {
        // Unique IP — keeps this test isolated from any other test's
        // api-v1-login slots, so the bucket always starts at 5 free.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42']);

        // Use a real student so each failed login returns 401 (wrong
        // password), not 404 (student_not_found). The throttler still
        // counts every attempt regardless of response status; using a
        // real account makes the assertion pinpoint the throttle
        // boundary rather than masking it behind a 404.
        $student = Student::factory()->create([
            'index_number' => 'BC/THROTTLE/24/001',
            'password'     => bcrypt('correct'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'index_number' => $student->index_number,
                'password'     => 'wrong',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'index_number' => $student->index_number,
            'password'     => 'wrong',
        ])->assertStatus(429);
    }

    public function test_login_returns_specific_error_codes_per_phase_1_contract(): void
    {
        // Each sub-call gets its own IP so neither pollutes the
        // throttler bucket nor each other.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.50']);

        // Unknown index: 404 + error_code=student_not_found.
        // (Audit C-02 recommended collapsing to a generic
        // `invalid_credentials`; Phase 1 / P1.T03 kept the existing
        // shape because Flutter drives different UX off this code
        // — see app/Http/Controllers/Api/AuthController::login().)
        $this->postJson('/api/login', [
            'index_number' => 'NOEXIST',
            'password'     => 'wrong',
        ])
            ->assertStatus(404)
            ->assertJsonFragment(['error_code' => 'student_not_found']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.51']);

        // Real account + wrong password: 401 + plain message.
        // PasswordPolicy::matches() is the central predicate (P1.T03).
        Student::factory()->create([
            'index_number' => 'BC/ITD/24/047',
            'password'     => bcrypt('correct'),
        ]);
        $this->postJson('/api/login', [
            'index_number' => 'BC/ITD/24/047',
            'password'     => 'WRONG',
        ])
            ->assertStatus(401)
            ->assertJsonFragment(['message' => 'Wrong password']);
    }

    public function test_me_works_with_credentials_in_body_per_option_b(): void
    {
        // Fresh IP — keeps the 5/min budget clean for /api/me's two
        // calls below (it shares the api-v1-login throttler bucket
        // with /api/login and /api/students/quick-status).
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.60']);

        $student = Student::factory()->create([
            'index_number' => 'BC/ITD/24/050',
            'password'     => bcrypt('pw'),
        ]);

        // Empty body → 422 (Laravel validation: index_number required).
        // Per P1.T10 Option B, /api/me is NOT behind auth:sanctum, so
        // a missing Bearer does NOT yield 401 — the controller simply
        // delegates to AuthController::login() which validates the body.
        $this->postJson('/api/me')->assertStatus(422);

        // Valid credentials → 200 + login payload (Flutter cold-start).
        $this->postJson('/api/me', [
            'index_number' => $student->index_number,
            'password'     => 'pw',
        ])
            ->assertOk()
            ->assertJsonStructure(['index_number', 'token', 'token_type']);
    }

    public function test_removed_is_admin_or_rep_only(): void
    {
        $student = Student::factory()->create(['password' => bcrypt('pw')]);
        $token = $student->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/students/removed')
            ->assertStatus(403);
    }
}
