<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use App\Support\AppDownloadStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public-facing endpoints for the mobile-app distribution channel:
 *   - /download                              landing page (HTML)
 *   - /download/android/latest.apk           always-latest published APK
 *   - /download/android/{versionCode}.apk    specific build (admin link)
 *
 * Auth: none — students need to be able to grab the APK without
 * signing in. The admin upload flow is what gates which builds
 * become public via the is_published toggle.
 */
class AppReleaseDownloadController extends Controller
{
    public function landing(): \Illuminate\View\View
    {
        $schemaReady = AppRelease::tableExists();
        $latest = $schemaReady
            ? AppRelease::latestPublishedFor(AppRelease::PLATFORM_ANDROID)
            : null;
        // Hide a release whose APK got wiped — show the empty state
        // instead of a 404 link.
        if ($latest && ! $latest->fileExists()) {
            $latest = null;
        }

        return view('public.download', [
            'latest' => $latest,
            'schemaReady' => $schemaReady,
        ]);
    }

    public function downloadLatestAndroid(): Response
    {
        $latest = AppRelease::latestPublishedFor(AppRelease::PLATFORM_ANDROID);
        if (! $latest || ! $latest->fileExists()) {
            abort(404, 'No published Android build available right now.');
        }

        return $this->stream($latest);
    }

    public function downloadVersionedAndroid(int $versionCode): Response
    {
        $release = AppRelease::query()
            ->forPlatform(AppRelease::PLATFORM_ANDROID)
            ->where('version_code', $versionCode)
            ->first();
        if (! $release || ! $release->fileExists()) {
            abort(404);
        }
        // Unpublished builds aren't directly downloadable except for
        // admins (who would normally be authenticated for the
        // dashboard route anyway).
        if (! $release->is_published && ! request()->session()->has('admin_id')) {
            abort(403, 'This build is not published yet.');
        }

        return $this->stream($release);
    }

    private function stream(AppRelease $release): BinaryFileResponse
    {
        AppDownloadStats::increment();

        $abs = AppRelease::disk()->path($release->apk_path);
        $name = "atenda-{$release->platform}-{$release->version_name}.apk";

        return response()->download($abs, $name, [
            'Content-Type' => 'application/vnd.android.package-archive',
            // No caching — we want browsers to always grab the
            // current bytes if a release ever gets re-uploaded.
            'Cache-Control' => 'no-store, max-age=0',
            'X-App-Version-Name' => $release->version_name,
            'X-App-Version-Code' => (string) $release->version_code,
            'X-App-SHA-256' => (string) $release->apk_sha256,
        ]);
    }
}
