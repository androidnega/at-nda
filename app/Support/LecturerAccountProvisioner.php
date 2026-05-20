<?php

namespace App\Support;

use App\Models\Lecturer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Provisions a login account on a Lecturer record so it shows up under
 * "User management" (StaffAccountController) right after creation, with a
 * generated username and a one-time temporary password.
 */
final class LecturerAccountProvisioner
{
    /**
     * Ensure the lecturer has a username + password set. Returns the plain
     * temporary password (and resolved username) when a new login was just
     * created, or null when the lecturer already had a password and no new
     * credentials were generated.
     *
     * The returned plain password is intentionally only available here so
     * the admin can surface it once (e.g. via flash message).
     *
     * @return array{username: ?string, password: string}|null
     */
    public static function ensureLogin(Lecturer $lecturer): ?array
    {
        if (! empty($lecturer->password)) {
            return null;
        }

        $hasUsernameColumn = Schema::hasColumn('lecturers', 'username');
        $hasMustChangeColumn = Schema::hasColumn('lecturers', 'must_change_password');

        $username = null;
        if ($hasUsernameColumn) {
            LecturerUsername::assignIfMissing($lecturer);
            $username = $lecturer->username;
        }

        $plainPassword = Str::upper(Str::random(10));
        $lecturer->password = Hash::make($plainPassword);
        if ($hasMustChangeColumn) {
            $lecturer->must_change_password = true;
        }
        $lecturer->save();

        return [
            'username' => $username,
            'password' => $plainPassword,
        ];
    }
}
