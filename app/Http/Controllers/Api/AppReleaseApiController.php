<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile app calls this on launch (and optionally on a timer) to
 * learn whether a newer build is available.
 *
 *   GET /api/app/latest?platform=android&current_version_code=12
 *
 * Auth: none. The endpoint only ever returns metadata about
 * publicly-published releases.
 */
class AppReleaseApiController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $platform = strtolower((string) ($request->query('platform') ?? AppRelease::PLATFORM_ANDROID));
        if (! in_array($platform, [AppRelease::PLATFORM_ANDROID], true)) {
            return ApiEnvelope::error('Unsupported platform.', 422);
        }

        $currentRaw = $request->query('current_version_code');
        $current = is_numeric($currentRaw) ? max(0, (int) $currentRaw) : 0;

        $latest = AppRelease::latestPublishedFor($platform);
        if (! $latest || ! $latest->fileExists()) {
            return ApiEnvelope::success([
                'platform' => $platform,
                'has_release' => false,
                'is_update_available' => false,
                'is_update_required' => false,
            ], 'No published release.');
        }

        $isUpdate = $current < $latest->version_code;
        $forcedByFlag = (bool) $latest->is_required;
        $forcedByFloor = $latest->min_supported_version_code !== null
            && $current > 0
            && $current < (int) $latest->min_supported_version_code;
        $isRequired = $isUpdate && ($forcedByFlag || $forcedByFloor);

        return ApiEnvelope::success([
            'platform' => $platform,
            'has_release' => true,
            'version_name' => $latest->version_name,
            'version_code' => (int) $latest->version_code,
            'release_notes' => $latest->release_notes,
            'apk_size_bytes' => $latest->apk_size_bytes ? (int) $latest->apk_size_bytes : null,
            'apk_sha256' => $latest->apk_sha256,
            'download_url' => $latest->downloadUrl(),
            'web_landing_url' => url('/download'),
            'released_at' => optional($latest->released_at)->toIso8601String(),
            'min_supported_version_code' => $latest->min_supported_version_code !== null
                ? (int) $latest->min_supported_version_code
                : null,
            // current_version_code = 0 means "client didn't tell us",
            // so we report the release as available but never as
            // forced — clients without a version are typically
            // first-time installs or web previews.
            'current_version_code' => $current,
            'is_update_available' => $current > 0 && $isUpdate,
            'is_update_required' => $isRequired,
        ], 'Latest release loaded');
    }
}
