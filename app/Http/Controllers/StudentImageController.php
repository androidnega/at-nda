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
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $absolutePath = Storage::disk('public')->path($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => (Storage::disk('public')->mimeType($path) ?: 'application/octet-stream'),
        };

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
