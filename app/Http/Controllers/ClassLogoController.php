<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ClassLogoController extends Controller
{
    public function show(SchoolClass $schoolClass): Response
    {
        $path = trim((string) $schoolClass->logo_path);
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
