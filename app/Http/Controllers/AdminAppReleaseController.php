<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Admin-side CRUD for the mobile-app distribution channel.
 * Sits behind the existing `admin` web middleware group; no
 * additional auth.
 */
class AdminAppReleaseController extends Controller
{
    /** Cap APK uploads to 100 MB to match typical Android sizes
     *  without giving someone a free DOS vector. */
    private const MAX_APK_MB = 100;

    public function index(): View
    {
        $releases = AppRelease::query()
            ->orderByDesc('platform')
            ->orderByDesc('version_code')
            ->get();

        return view('admin.app-releases.index', [
            'releases' => $releases,
            'maxUploadMb' => self::MAX_APK_MB,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => 'required|in:'.AppRelease::PLATFORM_ANDROID,
            'version_name' => ['required', 'string', 'max:32', 'regex:/^[0-9A-Za-z.\-_]+$/'],
            'version_code' => 'required|integer|min:1|max:2147483647',
            'apk' => [
                'required',
                'file',
                // dompdf isn't involved here; the mime check is
                // permissive because some browsers report APKs as
                // octet-stream rather than the Android-specific MIME.
                'max:'.(self::MAX_APK_MB * 1024),
            ],
            'release_notes' => 'nullable|string|max:8000',
            'min_supported_version_code' => 'nullable|integer|min:0|max:2147483647',
            'is_published' => 'nullable|boolean',
            'is_required' => 'nullable|boolean',
        ], [
            'apk.max' => 'APK is too large. Max '.self::MAX_APK_MB.' MB.',
            'version_name.regex' => 'Version name may only contain letters, digits, dots, hyphens, and underscores.',
        ]);

        $platform = $validated['platform'];
        $versionCode = (int) $validated['version_code'];

        // Enforce the (platform, version_code) unique constraint at
        // the form level so the user sees a friendly message instead
        // of a SQLSTATE 23000.
        $exists = AppRelease::query()
            ->where('platform', $platform)
            ->where('version_code', $versionCode)
            ->exists();
        if ($exists) {
            return back()
                ->withInput()
                ->withErrors(['version_code' => "Version code {$versionCode} is already uploaded for {$platform}. Bump the code and try again."]);
        }

        $disk = AppRelease::disk();
        $disk->makeDirectory(AppRelease::ANDROID_DIR);

        $uploaded = $request->file('apk');
        $filename = sprintf('%s-%d.apk', $platform, $versionCode);
        $storedPath = $uploaded->storeAs(
            AppRelease::ANDROID_DIR,
            $filename,
            ['disk' => AppRelease::ANDROID_DISK]
        );

        $absolutePath = $disk->path($storedPath);
        $sha = is_readable($absolutePath) ? hash_file('sha256', $absolutePath) : null;

        $release = AppRelease::create([
            'platform' => $platform,
            'version_name' => $validated['version_name'],
            'version_code' => $versionCode,
            'apk_path' => $storedPath,
            'apk_size_bytes' => $uploaded->getSize() ?: null,
            'apk_sha256' => $sha,
            'release_notes' => $validated['release_notes'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'min_supported_version_code' => $validated['min_supported_version_code'] ?? null,
            'released_by_admin_id' => $request->session()->get('admin_id'),
            'released_at' => now(),
        ]);

        return redirect()
            ->route('dashboard.app-releases.index')
            ->with('success', "Release v{$release->version_name} (#{$release->version_code}) uploaded.");
    }

    /**
     * Flip the publish / required toggles in-place. Used by the
     * little switch UI on the releases table.
     */
    public function update(Request $request, AppRelease $release): RedirectResponse
    {
        $validated = $request->validate([
            'is_published' => 'nullable|boolean',
            'is_required' => 'nullable|boolean',
            'release_notes' => 'nullable|string|max:8000',
            'min_supported_version_code' => 'nullable|integer|min:0|max:2147483647',
        ]);

        $release->fill([
            'is_published' => array_key_exists('is_published', $validated)
                ? (bool) $validated['is_published']
                : $release->is_published,
            'is_required' => array_key_exists('is_required', $validated)
                ? (bool) $validated['is_required']
                : $release->is_required,
            'release_notes' => array_key_exists('release_notes', $validated)
                ? $validated['release_notes']
                : $release->release_notes,
            'min_supported_version_code' => array_key_exists('min_supported_version_code', $validated)
                ? $validated['min_supported_version_code']
                : $release->min_supported_version_code,
        ])->save();

        return back()->with('success', "Release v{$release->version_name} updated.");
    }

    public function destroy(AppRelease $release): RedirectResponse
    {
        if ($release->apk_path && AppRelease::disk()->exists($release->apk_path)) {
            try {
                AppRelease::disk()->delete($release->apk_path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $release->delete();

        return back()->with('success', 'Release deleted.');
    }
}
