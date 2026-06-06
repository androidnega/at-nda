<?php

namespace App\Services;

use App\Mail\StudentPasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\Student;
use App\Support\MailRuntimeConfig;
use App\Support\SchemaFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StudentPasswordResetService
{
    public const CODE_TTL_MINUTES = 15;
    public const MAX_ATTEMPTS = 5;
    public const REQUEST_COOLDOWN_SECONDS = 60;

    /**
     * Generate a fresh code for this student and email it. Returns null on
     * success, or an error string the caller can show to the user.
     */
    public function issueCode(Student $student, Request $request): ?string
    {
        if (! SchemaFeatures::hasPasswordResetCodes()) {
            return 'Password reset is not yet available on this server. Please contact your admin.';
        }
        if (! SchemaFeatures::hasStudentsEmail()) {
            return 'Email-based password reset is not available yet. Please contact your class rep.';
        }
        $email = trim((string) ($student->email ?? ''));
        if ($email === '') {
            return 'No email is on file for your account. Ask your class rep or admin to add one.';
        }

        if (! MailRuntimeConfig::isConfigured() && (string) config('mail.default') === 'log') {
            // Still allow in dev (log driver) so QA can copy the code from logs.
            Log::info('StudentPasswordReset: mail not configured; codes will be written to the log channel.');
        }

        // Throttle: don't let a student spam codes.
        $latest = PasswordResetCode::query()
            ->where('index_number', $student->index_number)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();
        if ($latest && $latest->created_at && $latest->created_at->diffInSeconds(now()) < self::REQUEST_COOLDOWN_SECONDS) {
            $wait = self::REQUEST_COOLDOWN_SECONDS - (int) $latest->created_at->diffInSeconds(now());

            return 'Please wait '.max(5, $wait).'s before requesting another code.';
        }

        // Invalidate older live codes for this student so only the newest one works.
        PasswordResetCode::query()
            ->where('index_number', $student->index_number)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);

        PasswordResetCode::create([
            'index_number' => $student->index_number,
            'email' => $email,
            'code_hash' => Hash::make($code),
            'request_ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 480),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        try {
            Mail::to($email)->send(new StudentPasswordResetCodeMail($student, $code, self::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            Log::error('StudentPasswordReset: mail send failed', [
                'index' => $student->index_number,
                'error' => $e->getMessage(),
            ]);

            return 'We could not send the reset email right now. Please try again in a moment.';
        }

        return null;
    }

    /**
     * Verify the code and update the student's password. Returns null on
     * success, an error string otherwise. On success the code row is marked
     * consumed and remaining codes for the student are invalidated.
     */
    public function consumeCode(string $indexNumber, string $code, string $newPassword): ?string
    {
        if (! SchemaFeatures::hasPasswordResetCodes()) {
            return 'Password reset is not yet available on this server.';
        }

        $indexNumber = trim($indexNumber);
        $code = trim($code);
        if ($indexNumber === '' || $code === '') {
            return 'Please enter your index number and the 6-digit code.';
        }

        $row = PasswordResetCode::query()
            ->where('index_number', $indexNumber)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            return 'No active reset code for that index number. Request a new code.';
        }

        if ($row->isExpired()) {
            $row->update(['consumed_at' => now()]);

            return 'This reset code has expired. Request a new one.';
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            $row->update(['consumed_at' => now()]);

            return 'Too many wrong attempts. Request a new code.';
        }

        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');

            return 'That code is not correct.';
        }

        $student = Student::query()->where('index_number', $indexNumber)->first();
        if (! $student) {
            return 'We could not find your student account.';
        }

        $student->update(['password' => Hash::make($newPassword)]);
        $row->update(['consumed_at' => now()]);

        return null;
    }

    /**
     * Send a branded test message to verify the SMTP credentials. Used
     * by the super-admin settings page; the HTML template lives at
     * resources/views/emails/smtp-test.blade.php and matches the
     * styling of the password-reset email.
     */
    public function sendTestEmail(string $toEmail): ?string
    {
        $appName = (string) config('app.name', 'a-tenda');
        try {
            Mail::send('emails.smtp-test', ['appName' => $appName], function ($message) use ($toEmail, $appName) {
                $message->to($toEmail)->subject($appName.' SMTP test');
            });

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public static function nextRetryAt(?Carbon $lastIssuedAt): ?Carbon
    {
        if (! $lastIssuedAt) {
            return null;
        }

        return $lastIssuedAt->copy()->addSeconds(self::REQUEST_COOLDOWN_SECONDS);
    }
}
