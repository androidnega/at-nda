<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores uploaded mobile-app builds. Admins manage these from the
 * dashboard; the Flutter app polls /api/app/latest on launch to
 * detect when a new build is available and prompt the user to
 * download.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_releases')) {
            return;
        }

        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            // Right now we only ship Android, but pin the column so
            // adding iOS later is purely additive — no schema migration
            // needed beyond storing 'ios' values.
            $table->string('platform', 16)->default('android');
            $table->string('version_name', 32);
            $table->unsignedInteger('version_code');
            // APK lives on the public disk under apps/android/* so we
            // can serve it via a signed-or-direct route. Path is
            // relative to the disk root, not URL-encoded.
            $table->string('apk_path');
            $table->unsignedBigInteger('apk_size_bytes')->nullable();
            // SHA-256 of the uploaded APK so the mobile app can
            // optionally verify integrity after download.
            $table->char('apk_sha256', 64)->nullable();
            $table->text('release_notes')->nullable();
            // is_published gates whether the release is visible to
            // students at all (download page + API). Admins can
            // upload silently first and flip the toggle when ready.
            $table->boolean('is_published')->default(false);
            // is_required forces the Flutter app to block until the
            // user updates. Used for breaking-change rollouts.
            $table->boolean('is_required')->default(false);
            // Anything strictly below this version code is told to
            // update on next launch even if a non-required release
            // is published. Nullable = no hard floor.
            $table->unsignedInteger('min_supported_version_code')->nullable();
            // Audit trail: which admin uploaded this build.
            $table->unsignedBigInteger('released_by_admin_id')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            // Each (platform, version_code) is unique so we never
            // accidentally ship two different APKs as the same
            // version.
            $table->unique(['platform', 'version_code']);
            $table->index(['platform', 'is_published', 'version_code'], 'idx_latest_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
