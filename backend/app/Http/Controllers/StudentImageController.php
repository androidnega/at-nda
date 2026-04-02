<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class StudentImageController extends Controller
{
    public function show(Student $student): Response
    {
        $path = trim((string) $student->profile_image);
        if ($path === '') {
            abort(404);
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return redirect()->away($path);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
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
            abort(404);
        }

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
