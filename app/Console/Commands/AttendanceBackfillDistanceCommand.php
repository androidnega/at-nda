<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Support\AttendanceLocation;
use App\Support\SchemaFeatures;
use Illuminate\Console\Command;

/**
 * One-shot backfill for attendances rows that have lat / lng but were
 * written before distance_from_anchor existed (or before the web flow
 * was fixed to persist it). Owner decision D4.
 *
 * Behaviour:
 *   - Reads only rows where lat IS NOT NULL AND lng IS NOT NULL
 *     AND distance_from_anchor IS NULL.
 *   - Uses chunkById(500) so the live MySQL connection on cPanel
 *     never sees a "WHERE id > X LIMIT 500" with more than 500 rows
 *     in flight at once. Default chunk size is overridable.
 *   - Joins the session lazily (cached per id) to avoid N+1 fetches
 *     when many marks share the same session.
 *   - Skips rows whose session has no anchor (online / qr / wifi) —
 *     distance has no meaning there.
 *   - --dry-run reports counts without writing anything.
 *
 * Examples:
 *   php artisan attendance:backfill-distance
 *   php artisan attendance:backfill-distance --dry-run
 *   php artisan attendance:backfill-distance --chunk=200
 */
class AttendanceBackfillDistanceCommand extends Command
{
    protected $signature = 'attendance:backfill-distance
                            {--dry-run : Report what would change without writing}
                            {--chunk=500 : Rows per chunkById batch}';

    protected $description = 'Backfill attendances.distance_from_anchor for legacy rows that have GPS but no precomputed distance.';

    public function handle(): int
    {
        if (! SchemaFeatures::hasAttendancesDistanceFromAnchor()) {
            $this->error('The attendances.distance_from_anchor column does not exist yet. Run "php artisan migrate" first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, min(2000, (int) $this->option('chunk')));

        $this->line('');
        $this->info($dryRun
            ? '🔍 Dry run — no rows will be updated.'
            : '🧮 Backfilling distance_from_anchor for legacy attendance rows…');
        $this->line("   chunk size: {$chunk}");

        $totalCandidates = Attendance::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereNull('distance_from_anchor')
            ->count();

        if ($totalCandidates === 0) {
            $this->info('Nothing to backfill — every GPS row already has distance_from_anchor.');

            return self::SUCCESS;
        }

        $this->line("   candidate rows: {$totalCandidates}");

        $bar = $this->output->createProgressBar($totalCandidates);
        $bar->start();

        $sessionCache = [];   // id => ['lat' => ?, 'lng' => ?] | false (no anchor)
        $updated = 0;
        $skippedNoAnchor = 0;
        $skippedNoCoords = 0;
        $failed = 0;

        Attendance::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereNull('distance_from_anchor')
            ->select(['id', 'attendance_session_id', 'lat', 'lng'])
            ->chunkById($chunk, function ($rows) use (
                &$sessionCache,
                &$updated,
                &$skippedNoAnchor,
                &$skippedNoCoords,
                &$failed,
                $dryRun,
                $bar
            ) {
                foreach ($rows as $row) {
                    $bar->advance();

                    if ($row->attendance_session_id === null) {
                        $skippedNoAnchor++;

                        continue;
                    }

                    $sessionId = (int) $row->attendance_session_id;
                    if (! array_key_exists($sessionId, $sessionCache)) {
                        $sess = AttendanceSession::query()
                            ->select(['id', 'location_lat', 'location_lng'])
                            ->find($sessionId);
                        $sessionCache[$sessionId] = (! $sess || $sess->location_lat === null || $sess->location_lng === null)
                            ? false
                            : ['lat' => (float) $sess->location_lat, 'lng' => (float) $sess->location_lng];
                    }

                    $anchor = $sessionCache[$sessionId];
                    if ($anchor === false) {
                        $skippedNoAnchor++;

                        continue;
                    }

                    if (! is_numeric($row->lat) || ! is_numeric($row->lng)) {
                        $skippedNoCoords++;

                        continue;
                    }

                    $distance = AttendanceLocation::storableMeters(
                        AttendanceLocation::distanceMeters(
                            $anchor['lat'],
                            $anchor['lng'],
                            (float) $row->lat,
                            (float) $row->lng
                        )
                    );

                    if ($dryRun) {
                        $updated++;

                        continue;
                    }

                    try {
                        // Direct query-builder update keeps model events
                        // off the hot path — backfill must not retrigger
                        // risk scoring or live caches.
                        Attendance::query()
                            ->whereKey($row->id)
                            ->update(['distance_from_anchor' => $distance]);
                        $updated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->line('');
                        $this->error("Row #{$row->id}: ".$e->getMessage());
                    }
                }
            }, 'id');

        $bar->finish();
        $this->line('');
        $this->line('');
        $this->info('Done.');
        $this->table(
            ['Outcome', 'Rows'],
            [
                [$dryRun ? 'Would be updated' : 'Updated', $updated],
                ['Skipped (session has no anchor)', $skippedNoAnchor],
                ['Skipped (invalid coordinates)', $skippedNoCoords],
                ['Failed', $failed],
            ]
        );

        return self::SUCCESS;
    }
}
