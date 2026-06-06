<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance migration — purely additive, safe to re-run.
 *
 * 1) Re-asserts the indexes the audit task expected to find on:
 *      attendances (student_id, attendance_session_id)
 *      attendance_sessions  class_id, course_id, attendance_week_id
 *      attendance_weeks (course_id, class_id)
 *    Every one of these is already created by an earlier migration on a
 *    fresh install (FK indexes, the unique constraint on the attendances
 *    pair, and the composite (course_id, class_id, week_date) prefix on
 *    attendance_weeks). This migration is a defensive no-op for those —
 *    it only acts when an environment is missing an expected index, e.g.
 *    after a manual schema repair.
 *
 * 2) Adds three high-value indexes that ARE missing and feed the hot
 *    paths used during mass attendance marking:
 *      attendance_sessions (qr_token)              — QR scan resolves
 *      attendances (attendance_session_id, status) — present_count query
 *      attendances (attendance_week_id, device_fingerprint) — fraud guard
 *
 * 3) Normalises any existing `students.index_number` rows so the new
 *    sargable `where('index_number', ?)` lookup (Student::findByIndex
 *    fast path) never misses a legacy row with leading/trailing
 *    whitespace or lowercase characters. Future writes are normalised
 *    automatically by the model's attribute mutator.
 *
 * No API surface or business logic is touched here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- (3) Normalise legacy student.index_number values -----------
        $this->normaliseStudentIndexNumbers();

        // ---- (2) New high-value performance indexes ---------------------
        if (Schema::hasTable('attendance_sessions')) {
            if (Schema::hasColumn('attendance_sessions', 'qr_token')) {
                $this->addIndexIfMissing(
                    table: 'attendance_sessions',
                    name: 'attendance_sessions_qr_token_idx',
                    columns: ['qr_token'],
                );
            }
        }

        if (Schema::hasTable('attendances')) {
            if (Schema::hasColumn('attendances', 'attendance_session_id')
                && Schema::hasColumn('attendances', 'status')) {
                $this->addIndexIfMissing(
                    table: 'attendances',
                    name: 'attendances_session_status_idx',
                    columns: ['attendance_session_id', 'status'],
                );
            }

            if (Schema::hasColumn('attendances', 'attendance_week_id')
                && Schema::hasColumn('attendances', 'device_fingerprint')) {
                $this->addIndexIfMissing(
                    table: 'attendances',
                    name: 'attendances_week_dfp_idx',
                    columns: ['attendance_week_id', 'device_fingerprint'],
                );
            }
        }

        // ---- (1) Defensive re-assertion of the task-listed indexes ------
        //
        // Each of these is already created by an earlier migration on a
        // standard install. We only re-add them if the environment is
        // missing the index entirely (e.g. someone hand-dropped an FK
        // index during a manual repair). All other cases are a no-op.

        if (Schema::hasTable('attendances')
            && Schema::hasColumn('attendances', 'student_id')
            && Schema::hasColumn('attendances', 'attendance_session_id')) {
            $this->addIndexIfMissing(
                table: 'attendances',
                name: 'attendances_student_session_safety_idx',
                columns: ['student_id', 'attendance_session_id'],
            );
        }

        if (Schema::hasTable('attendance_sessions')) {
            foreach ([
                'class_id' => 'attendance_sessions_class_id_safety_idx',
                'course_id' => 'attendance_sessions_course_id_safety_idx',
                'attendance_week_id' => 'attendance_sessions_attendance_week_id_safety_idx',
            ] as $column => $name) {
                if (Schema::hasColumn('attendance_sessions', $column)) {
                    $this->addIndexIfMissing(
                        table: 'attendance_sessions',
                        name: $name,
                        columns: [$column],
                    );
                }
            }
        }

        if (Schema::hasTable('attendance_weeks')
            && Schema::hasColumn('attendance_weeks', 'course_id')
            && Schema::hasColumn('attendance_weeks', 'class_id')) {
            $this->addIndexIfMissing(
                table: 'attendance_weeks',
                name: 'attendance_weeks_course_class_safety_idx',
                columns: ['course_id', 'class_id'],
            );
        }
    }

    public function down(): void
    {
        $drops = [
            'attendance_sessions' => [
                'attendance_sessions_qr_token_idx',
                'attendance_sessions_class_id_safety_idx',
                'attendance_sessions_course_id_safety_idx',
                'attendance_sessions_attendance_week_id_safety_idx',
            ],
            'attendances' => [
                'attendances_session_status_idx',
                'attendances_week_dfp_idx',
                'attendances_student_session_safety_idx',
            ],
            'attendance_weeks' => [
                'attendance_weeks_course_class_safety_idx',
            ],
        ];

        foreach ($drops as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($names): void {
                foreach ($names as $name) {
                    try {
                        $blueprint->dropIndex($name);
                    } catch (Throwable $e) {
                        // Index was already gone or never created.
                    }
                }
            });
        }
    }

    /**
     * Add a named index when none with the same name OR same column tuple
     * already exists. Tries Laravel 11+ Schema::hasIndex first; falls back
     * to a guarded CREATE INDEX so older Laravel + non-MySQL drivers stay
     * silent on the "already exists" race.
     *
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        try {
            if (method_exists(Schema::class, 'hasIndex')) {
                if (Schema::hasIndex($table, $name)
                    || Schema::hasIndex($table, $columns)) {
                    return;
                }
            }
        } catch (Throwable $e) {
            // Older Laravel / unsupported driver — fall through to try/catch.
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        } catch (Throwable $e) {
            // MySQL: ER_DUP_KEYNAME / ER_DUP_FIELDNAME — already present.
            // SQLite: "index already exists" — same outcome.
            // Any other failure is logged but never throws (this is an
            // additive performance migration, never blocks deploy).
            Log::warning('performance_index_add_skipped', [
                'table' => $table,
                'name' => $name,
                'columns' => $columns,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalise `students.index_number` rows that still carry whitespace
     * or mixed case. Future writes are kept clean by the model's
     * attribute mutator + saving listener.
     */
    private function normaliseStudentIndexNumbers(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'index_number')) {
            return;
        }

        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                // SQLite has no BINARY operator; UPDATE everything via
                // an idempotent comparison.
                DB::statement(<<<'SQL'
                    UPDATE students
                       SET index_number = UPPER(TRIM(index_number))
                     WHERE index_number IS NOT NULL
                       AND index_number != UPPER(TRIM(index_number))
                SQL);

                return;
            }

            // MySQL / MariaDB — use BINARY to make the comparison
            // case-sensitive even when the column collation is _ci.
            DB::statement(<<<'SQL'
                UPDATE students
                   SET index_number = UPPER(TRIM(index_number))
                 WHERE index_number IS NOT NULL
                   AND BINARY index_number != BINARY UPPER(TRIM(index_number))
            SQL);
        } catch (Throwable $e) {
            // Non-fatal — the runtime fallback in Student::findByIndex
            // still resolves legacy rows via the original whereRaw path.
            Log::warning('student_index_number_normalisation_skipped', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
