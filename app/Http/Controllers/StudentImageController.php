<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class StudentImageController extends Controller
{
    /**
     * Tiny transparent PNG — 200 OK so Flutter web / NetworkImage avoid hard 404s
     * when no photo is stored or the file was removed from disk.
     */
    private function placeholderImageResponse(): Response
    {
        static $png = null;
        if ($png === null) {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true) ?: '';
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function show(Student $student): Response
    {
        $path = trim((string) $student->profile_image);
        if ($path === '') {
            return $this->placeholderImageResponse();
        }

        // P1.T16: profile_image temporarily carries a 'pending:' sentinel
        // ('pending:tmp-profile/<id>-<uuid>') while ResizeStudentProfileImage
        // is queued. There is no public-disk file at this address yet, and
        // the staged bytes on the local disk are deliberately not exposed
        // publicly. Serve the 1x1 transparent placeholder PNG (200 OK) so
        // Flutter web / NetworkImage don't flash a broken-image icon while
        // the worker is in flight. When the worker overwrites profile_image
        // with the optimized 'students/...' path and updated_at flips, the
        // ?v=<timestamp> cache buster on the model's profileImageUrl()
        // causes the next render to refetch the real binary.
        if (str_starts_with($path, Student::PENDING_IMAGE_PREFIX)) {
            return $this->placeholderImageResponse();
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return redirect()->away($path);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return $this->placeholderImageResponse();
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => ($disk->mimeType($path) ?: 'application/octet-stream'),
        };

        try {
            $binary = $disk->get($path);
        } catch (\Throwable $e) {
            return $this->placeholderImageResponse();
        }

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
            // Flutter web loads avatars via fetch/CORS; config/cors.php includes media/* as well.
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
