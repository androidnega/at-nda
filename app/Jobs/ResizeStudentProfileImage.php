<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Optimises a freshly uploaded student profile image off the request
 * thread.
 *
 * Flow (wired up in P1.T16):
 *   1. Controller calls Student::saveProfileImageFromUpload(...)
 *      or Student::saveProfileImageFromBase64(...).
 *   2. That method writes the raw bytes to
 *        storage/app/private/tmp-profile/<student_id>-<uuid>
 *      and dispatches this job.
 *   3. The queue worker picks the row up, runs the GD resize loop,
 *      persists the optimized image to
 *        storage/app/public/students/<id>_<uniqid>.<ext>
 *      via Student::saveProfileImageFromRawBytes(), then deletes the
 *      temp file.
 *
 * Failure semantics:
 *   - The temp file is removed on every terminal outcome (success,
 *     missing student, missing temp file, empty payload, resize
 *     exception). Orphan files only persist when the worker process
 *     itself dies between read and cleanup; a Phase 8 prune job
 *     will sweep those up.
 *   - On a resize-pipeline exception the student keeps the OLD
 *     profile image. We do NOT clear profile_image on failure.
 *   - $tries = 3 — Laravel retries transient failures (e.g. disk
 *     contention, momentary DB hiccup) before banking the job in
 *     `failed_jobs`. After the third attempt the temp file is gone
 *     and the previous avatar is unchanged.
 */
class ResizeStudentProfileImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Max attempts before the job lands in `failed_jobs`. The resize
     * itself is deterministic; retries cover transient I/O / DB
     * blips on shared hosting.
     */
    public int $tries = 3;

    /**
     * Hard ceiling on a single job's wall-clock time (seconds).
     * Enforced by Laravel via pcntl_alarm(); independent of PHP's
     * max_execution_time. A real 1280×1280 resize completes in
     * < 3 s on cPanel CPUs, so 60 s is the panic ceiling for
     * malformed / pathological inputs.
     */
    public int $timeout = 60;

    public function __construct(
        public readonly int $studentId,
        public readonly string $tempRelativePath,
    ) {
    }

    public function handle(): void
    {
        $student = Student::find($this->studentId);
        if ($student === null) {
            Log::warning('ResizeStudentProfileImage: student not found', [
                'student_id' => $this->studentId,
                'temp' => $this->tempRelativePath,
            ]);
            $this->cleanupTemp();

            return;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($this->tempRelativePath)) {
            Log::warning('ResizeStudentProfileImage: temp file missing', [
                'student_id' => $this->studentId,
                'temp' => $this->tempRelativePath,
            ]);

            return;
        }

        try {
            $raw = $disk->get($this->tempRelativePath);
        } catch (\Throwable $e) {
            Log::error('ResizeStudentProfileImage: failed to read temp file', [
                'student_id' => $this->studentId,
                'temp' => $this->tempRelativePath,
                'error' => $e->getMessage(),
            ]);
            $this->cleanupTemp();

            return;
        }

        if (! is_string($raw) || $raw === '') {
            Log::warning('ResizeStudentProfileImage: empty temp file', [
                'student_id' => $this->studentId,
                'temp' => $this->tempRelativePath,
            ]);
            $this->cleanupTemp();

            return;
        }

        $ok = false;
        try {
            $ok = $student->saveProfileImageFromRawBytes($raw);
        } catch (\Throwable $e) {
            Log::error('ResizeStudentProfileImage: resize failed', [
                'student_id' => $this->studentId,
                'temp' => $this->tempRelativePath,
                'error' => $e->getMessage(),
            ]);
            $this->cleanupTemp();

            return;
        }

        if ($ok) {
            try {
                $student->save();
            } catch (\Throwable $e) {
                Log::error('ResizeStudentProfileImage: persist failed', [
                    'student_id' => $this->studentId,
                    'error' => $e->getMessage(),
                ]);
                // Fall through to cleanup — the resized binary already
                // landed on the public disk; only the DB write was
                // lost. A retry will re-resize and re-persist.
            }
        } else {
            Log::warning('ResizeStudentProfileImage: rejected by model guards', [
                'student_id' => $this->studentId,
                'temp' => $this->tempRelativePath,
                'bytes' => strlen($raw),
            ]);
        }

        $this->cleanupTemp();
    }

    /**
     * Called by Laravel when retries are exhausted. We make a final
     * attempt to free the temp file so a hard failure doesn't leak
     * disk space.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('ResizeStudentProfileImage: job failed terminally', [
            'student_id' => $this->studentId,
            'temp' => $this->tempRelativePath,
            'error' => $e->getMessage(),
        ]);
        $this->cleanupTemp();
    }

    private function cleanupTemp(): void
    {
        try {
            $disk = Storage::disk('local');
            if ($disk->exists($this->tempRelativePath)) {
                $disk->delete($this->tempRelativePath);
            }
        } catch (\Throwable $e) {
            // Non-fatal; a Phase 8 cleanup command will sweep any
            // orphans older than 24 h.
            Log::warning('ResizeStudentProfileImage: temp cleanup failed', [
                'student_id' => $this->studentId,
                'temp' => $this->tempRelativePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
