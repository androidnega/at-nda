<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\PasswordPolicy;
use Illuminate\Console\Command;

/**
 * php artisan students:rehash-passwords [--chunk=200] [--dry-run]
 *
 * Walks the students table; for each row whose `password` is non-empty
 * and not bcrypt-shaped, rehashes in place. Idempotent: rows already
 * bcrypted are skipped. Prints a final summary that callers can grep
 * for "Remaining non-bcrypt rows: 0".
 *
 * Designed to be safe to run on a live system:
 *  - chunkById() avoids long-running transactions and cursor drift
 *  - saveQuietly() bypasses Student model events (CacheVersions::bump,
 *    index_number re-uppercase, DeletedStudentIndex), so the rehash
 *    causes no cascading side effects
 *  - forceFill() touches ONLY the password column
 */
class RehashStudentPasswords extends Command
{
    protected $signature = 'students:rehash-passwords
        {--chunk=200 : How many rows to process per query}
        {--dry-run  : Inspect counts without writing}';

    protected $description = 'Convert any non-bcrypt student passwords to bcrypt (one-time migration).';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $touched = 0;
        $skipped = 0;
        $blank = 0;

        Student::query()
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($students) use (&$touched, &$skipped, &$blank, $dryRun): void {
                foreach ($students as $student) {
                    $stored = (string) ($student->password ?? '');

                    if ($stored === '') {
                        $blank++;

                        continue;
                    }

                    if (PasswordPolicy::isBcrypt($stored)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $touched++;

                        continue;
                    }

                    $student->forceFill([
                        'password' => PasswordPolicy::rehash($stored),
                    ])->saveQuietly();

                    $touched++;
                }
            });

        $remaining = Student::query()
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->where(function ($q): void {
                $q->where('password', 'not like', '$2y$%')
                    ->where('password', 'not like', '$2a$%');
            })
            ->count();

        $this->newLine();
        $this->info($dryRun ? '[DRY RUN] no rows written.' : 'Rehash complete.');
        $this->table(
            ['Rehashed', 'Already bcrypt', 'Blank/null', 'Remaining non-bcrypt rows'],
            [[$touched, $skipped, $blank, $remaining]],
        );

        return $remaining === 0 ? self::SUCCESS : self::FAILURE;
    }
}
