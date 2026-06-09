<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Student;
use App\Services\AttendanceSessionSummaryService;
use App\Support\AttendanceLocation;
use App\Support\AttendanceSessionClassScope;
use App\Support\LecturerAccess;
use App\Support\RepCourseAccess;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Historical attendance map.
 *
 * One controller serves three audiences:
 *
 *   - admin     → all rows (no class / course scope filter)
 *   - lecturer  → rows for courses the lecturer is assigned to
 *   - rep       → rows for the rep's classes (scopeAttendanceMarksForClasses)
 *
 * The page shell ({@see repView} / {@see adminLecturerView}) renders
 * INSTANTLY — only layout + summary skeleton + map container. Markers
 * are fetched asynchronously by {@see markers}, scoped to the current
 * Leaflet viewport. The browser does the rendering, clustering, and
 * filter-UI work; the server only ships small JSON. This is the entire
 * point of the redesign per the owner spec (items 5, 6, 7, 8, 14).
 *
 * All endpoints share the same audience resolver, the same scoped
 * query base, and the same minimum-payload format.
 */
class AttendanceMapController extends Controller
{
    /**
     * Maximum markers returned in a single /markers call. Keeps the
     * per-request work bounded regardless of the viewport size. The
     * UI hints to the user when the cap is hit and asks them to zoom
     * in for a complete view.
     */
    private const MAX_MARKERS = 500;

    /** Maximum filter dropdown rows (per kind). */
    private const FILTER_LIMIT = 200;

    // ────────────────────────────────────────────────────────────
    // VIEW SHELLS
    // ────────────────────────────────────────────────────────────

    /**
     * GET /dashboard/attendance-map  (rep)
     *
     * Lives inside the classrep route group; the EnsureClassRep
     * middleware has already verified the caller. We only render the
     * shell — no markers, no aggregate queries. Markers are pulled
     * in by the browser after first paint.
     */
    public function repView(Request $request): View|Response
    {
        $student = $this->requireRep($request);
        if ($student instanceof Response) {
            return $student;
        }

        return view('dashboard.attendance-map', [
            'audience' => 'rep',
            'layoutFile' => 'layouts.classrep',
            'pageTitle' => 'Attendance map',
            'apiRoutes' => $this->apiRouteNames(),
        ]);
    }

    /**
     * GET /dashboard/attendance-map  (admin OR lecturer)
     *
     * Behind the `admin` middleware (= admin OR lecturer). The
     * controller asks LecturerAccess for the session role: if a
     * lecturer is signed in we render the lecturer-scoped UI; if an
     * admin is signed in we render the full-scope UI. Both use the
     * shared admin layout for consistency.
     */
    public function adminLecturerView(Request $request): View|RedirectResponse
    {
        $audience = $this->resolveStaffAudience($request);
        if ($audience === null) {
            return redirect()->route('dashboard.dashboard')->with('error', 'Sign in to view the attendance map.');
        }

        return view('dashboard.attendance-map', [
            'audience' => $audience,
            'layoutFile' => 'layouts.admin',
            'pageTitle' => 'Attendance map',
            'apiRoutes' => $this->apiRouteNames(),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // JSON ENDPOINTS
    // ────────────────────────────────────────────────────────────

    /**
     * GET /dashboard/attendance-map/markers
     *
     * Returns minimal marker payload for the visible viewport (north /
     * south / east / west bounding box) optionally narrowed by filters.
     * Capped at MAX_MARKERS — the UI tells the user to zoom in if hit.
     */
    public function markers(Request $request): JsonResponse
    {
        $audienceCtx = $this->resolveAudienceContext($request);
        if ($audienceCtx === null) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        // NOTE: lat/lng viewport bounds are NOT validated with
        // between:-90,90 / between:-180,180 because Leaflet at low
        // zooms (or with worldCopyJump-style wrapping) cheerfully
        // returns 180.0001 / -240. We clamp to safe ranges inside
        // the controller instead, so the request never 422s on the
        // user just because their map wrapped a world copy.
        $validated = $request->validate([
            'session_id' => 'nullable|integer',
            'course_id' => 'nullable|integer',
            'student_id' => 'nullable|integer',
            'mode' => 'nullable|string|in:location,hybrid',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'north' => 'nullable|numeric',
            'south' => 'nullable|numeric',
            'east' => 'nullable|numeric',
            'west' => 'nullable|numeric',
        ]);

        try {
            $query = $this->baseMarkerQuery($audienceCtx);

            if (! empty($validated['session_id'])) {
                $query->where('attendances.attendance_session_id', (int) $validated['session_id']);
            }
            if (! empty($validated['course_id'])) {
                $query->where('attendances.course_id', (int) $validated['course_id']);
            }
            if (! empty($validated['student_id'])) {
                $query->where('attendances.student_id', (int) $validated['student_id']);
            }
            if (! empty($validated['date_from'])) {
                $query->where('attendances.attendance_time', '>=', Carbon::parse($validated['date_from'])->startOfDay());
            }
            if (! empty($validated['date_to'])) {
                $query->where('attendances.attendance_time', '<=', Carbon::parse($validated['date_to'])->endOfDay());
            }
            if (! empty($validated['mode'])) {
                $query->whereHas('attendanceSession', fn (Builder $s) => $s->where('mode', $validated['mode']));
            }

            // Viewport bounding-box filter. The (lat, lng) composite
            // index (migration 2026_06_09_050000) makes this a range
            // scan rather than a full table scan even at 10k+ rows.
            // Inputs are clamped because Leaflet can return bounds
            // outside the [-90, 90] / [-180, 180] world frame.
            $hasViewport = isset($validated['north'], $validated['south'], $validated['east'], $validated['west']);
            if ($hasViewport) {
                $clampLat = fn (float $v) => max(-90.0, min(90.0, $v));
                $clampLng = fn (float $v) => max(-180.0, min(180.0, $v));

                $south = $clampLat((float) $validated['south']);
                $north = $clampLat((float) $validated['north']);
                $west = $clampLng((float) $validated['west']);
                $east = $clampLng((float) $validated['east']);

                $query
                    ->whereBetween('attendances.lat', [min($south, $north), max($south, $north)])
                    ->whereBetween('attendances.lng', [min($west, $east), max($west, $east)]);
            }

            // Pull only the columns we need for the marker payload —
            // no eager-loaded relations, no big student/course
            // objects. Distance + lat/lng come straight from the row.
            $rows = $query
                ->orderByDesc('attendances.attendance_time')
                ->limit(self::MAX_MARKERS)
                ->get([
                    'attendances.id',
                    'attendances.attendance_session_id',
                    'attendances.lat',
                    'attendances.lng',
                    'attendances.distance_from_anchor',
                    'attendances.attendance_time',
                    'attendances.status',
                ]);

            // Build a session → radius lookup once so colorBucket is
            // cheap for every marker (no per-row session fetch).
            $sessionIds = $rows->pluck('attendance_session_id')->filter()->unique()->values();
            $radii = AttendanceSession::query()
                ->whereIn('id', $sessionIds)
                ->get(['id', 'attendance_range_m'])
                ->mapWithKeys(fn ($s) => [(int) $s->id => (int) ($s->attendance_range_m ?? 200)])
                ->all();

            $points = $rows->map(function (Attendance $a) use ($radii) {
                $sessionId = (int) ($a->attendance_session_id ?? 0);
                $distance = $a->distance_from_anchor !== null ? (int) $a->distance_from_anchor : null;
                $radius = $radii[$sessionId] ?? null;

                return [
                    'id' => (int) $a->id,
                    's' => $sessionId,
                    'la' => (float) $a->lat,
                    'lo' => (float) $a->lng,
                    'd' => $distance,
                    'c' => AttendanceLocation::colorBucket($distance, $radius),
                    't' => optional($a->attendance_time)->toIso8601String(),
                ];
            })->values();

            return response()->json([
                'points' => $points,
                'count' => $points->count(),
                'capped' => $points->count() >= self::MAX_MARKERS,
                'limit' => self::MAX_MARKERS,
            ], 200, ['Cache-Control' => 'private, max-age=15']);
        } catch (\Throwable $e) {
            // Surface the real error to laravel.log so we can fix it
            // without the user having to dig through stack traces in
            // their browser console. Return a small JSON body the JS
            // can show meaningfully.
            Log::warning('attendance_map.markers_failed', [
                'audience' => $audienceCtx['role'] ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'error' => 'markers_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/attendance-map/sessions/{session}/summary
     *
     * Returns the cached per-session roll-up. If the cached row is
     * missing or stale we let the service rebuild it on the spot —
     * still cheaper than recomputing every render.
     */
    public function summary(Request $request, AttendanceSession $session): JsonResponse
    {
        $audienceCtx = $this->resolveAudienceContext($request);
        if ($audienceCtx === null) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        if (! $this->audienceCanSeeSession($audienceCtx, $session)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        try {
            $summary = AttendanceSessionSummaryService::getOrRebuild($session);
            $course = $session->course;

            // Anchor + radius only make sense for GPS sessions
            // (location / hybrid). For online / qr / wifi we omit
            // both so the JS knows to skip the circle / pin overlay
            // entirely instead of dropping a "ghost anchor" at (0, 0).
            $isGps = in_array($session->mode, ['location', 'hybrid'], true);
            $anchorLat = ($isGps && $session->location_lat !== null) ? (float) $session->location_lat : null;
            $anchorLng = ($isGps && $session->location_lng !== null) ? (float) $session->location_lng : null;
            $radius = $isGps ? (int) $session->effectiveAttendanceRangeMeters($course) : 0;

            $closest = $summary?->closestStudent;
            $farthest = $summary?->farthestStudent;

            return response()->json([
                'session' => [
                    'id' => (int) $session->id,
                    'mode' => (string) $session->mode,
                    'anchor' => [
                        'lat' => $anchorLat,
                        'lng' => $anchorLng,
                    ],
                    'radius_m' => $radius,
                    'course' => [
                        'id' => $course ? (int) $course->id : null,
                        'name' => $course?->course_name,
                        'code' => $course?->course_code,
                    ],
                    'opened_at' => optional($session->start_time)->toIso8601String(),
                    'closed_at' => optional($session->end_time ?? $session->expires_at)->toIso8601String(),
                    'is_active' => (bool) $session->is_active,
                ],
                'totals' => $summary ? [
                    'attendance_count' => (int) $summary->attendance_count,
                    'present_count' => (int) $summary->present_count,
                    'inside_count' => (int) $summary->inside_count,
                    'edge_count' => (int) $summary->edge_count,
                    'outside_count' => (int) $summary->outside_count,
                    'average_distance' => $summary->average_distance !== null ? (int) $summary->average_distance : null,
                    'minimum_distance' => $summary->minimum_distance !== null ? (int) $summary->minimum_distance : null,
                    'maximum_distance' => $summary->maximum_distance !== null ? (int) $summary->maximum_distance : null,
                ] : null,
                'closest_student' => ($isGps && $closest) ? [
                    'name' => trim((string) ($closest->first_name.' '.$closest->last_name)),
                    'index_number' => (string) $closest->index_number,
                    'distance' => $summary?->minimum_distance !== null ? (int) $summary->minimum_distance : null,
                ] : null,
                'farthest_student' => ($isGps && $farthest) ? [
                    'name' => trim((string) ($farthest->first_name.' '.$farthest->last_name)),
                    'index_number' => (string) $farthest->index_number,
                    'distance' => $summary?->maximum_distance !== null ? (int) $summary->maximum_distance : null,
                ] : null,
                'refreshed_at' => optional($summary?->refreshed_at)->toIso8601String(),
            ], 200, ['Cache-Control' => 'private, max-age=60']);
        } catch (\Throwable $e) {
            Log::warning('attendance_map.summary_failed', [
                'session_id' => (int) $session->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'error' => 'summary_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/attendance-map/markers/{attendance}/details
     *
     * Lazy detail fetch when a marker is clicked. Keeps the initial
     * marker payload tiny (no student name etc.) — only this endpoint
     * touches the students + courses tables.
     */
    public function details(Request $request, Attendance $attendance): JsonResponse
    {
        $audienceCtx = $this->resolveAudienceContext($request);
        if ($audienceCtx === null) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        if (! $this->audienceCanSeeAttendance($audienceCtx, $attendance)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $attendance->loadMissing([
            'student:id,index_number,first_name,last_name',
            'course:id,course_name,course_code',
            'attendanceSession:id,mode,attendance_range_m,location_lat,location_lng',
        ]);

        $student = $attendance->student;
        $course = $attendance->course;
        $session = $attendance->attendanceSession;

        $name = $student ? trim((string) ($student->first_name.' '.$student->last_name)) : 'Student';
        if ($name === '') {
            $name = (string) ($student?->index_number ?? 'Student');
        }

        return response()->json([
            'student_name' => $name,
            'index_number' => (string) ($student?->index_number ?? ''),
            'course' => [
                'name' => (string) ($course?->course_name ?? ''),
                'code' => (string) ($course?->course_code ?? ''),
            ],
            'mode' => (string) ($session?->mode ?? ''),
            'marked_at' => optional($attendance->attendance_time)->toIso8601String(),
            'distance' => $attendance->distance_from_anchor !== null ? (int) $attendance->distance_from_anchor : null,
            'status' => (string) ($attendance->status ?? 'present'),
        ], 200, ['Cache-Control' => 'private, max-age=60']);
    }

    /**
     * GET /dashboard/attendance-map/filters
     *
     * Returns dropdown choices the UI needs to wire the real
     * server-backed filters (course / session / student). Bounded by
     * FILTER_LIMIT per kind — UIs that need more should ship a typeahead.
     */
    public function filters(Request $request): JsonResponse
    {
        $audienceCtx = $this->resolveAudienceContext($request);
        if ($audienceCtx === null) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $courseIds = $audienceCtx['course_ids'] ?? null;

        // Courses
        $coursesQuery = Course::query()->select(['id', 'course_name', 'course_code']);
        if ($courseIds !== null) {
            $coursesQuery->whereIn('id', $courseIds);
        }
        $courses = $coursesQuery
            ->orderBy('course_name')
            ->limit(self::FILTER_LIMIT)
            ->get()
            ->map(fn (Course $c) => [
                'id' => (int) $c->id,
                'label' => $c->course_code ? $c->course_name.' ('.$c->course_code.')' : $c->course_name,
            ]);

        // Sessions — the map only ever plots GPS-anchored marks, so the
        // dropdown is pruned to match:
        //
        //   1) mode must be 'location' or 'hybrid'    (online / qr /
        //      wifi have no anchor and would otherwise clutter the
        //      list with empty entries — owner spec, item 3)
        //   2) attendance_week must not be cancelled  (test sessions
        //      from cancelled weeks linger in attendance_sessions; we
        //      mirror the existing Attendance::activeWeeksOnly()
        //      contract here so the two views agree)
        //   3) at least one attendance row with lat/lng exists       (drops
        //      every session that opened but never received a GPS mark —
        //      e.g. mode-toggle accidents)
        //   4) when several sessions share the same (course, week) we
        //      keep only the latest id, so "I taught Java week 2" shows
        //      up exactly once even if the rep opened the session four
        //      times.
        $sessionsQuery = AttendanceSession::query()
            ->select([
                'attendance_sessions.id',
                'attendance_sessions.course_id',
                'attendance_sessions.attendance_week_id',
                'attendance_sessions.mode',
                'attendance_sessions.start_time',
                'attendance_sessions.end_time',
                'attendance_sessions.attendance_range_m',
            ])
            ->whereIn('attendance_sessions.mode', ['location', 'hybrid'])
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('attendances')
                    ->whereColumn('attendances.attendance_session_id', 'attendance_sessions.id')
                    ->whereNotNull('attendances.lat')
                    ->whereNotNull('attendances.lng');
            })
            // Skip sessions whose week was cancelled. Sessions with no
            // attendance_week_id (older imports, manually-opened
            // sessions) pass through.
            ->where(function ($q) {
                $q->whereNull('attendance_sessions.attendance_week_id')
                    ->orWhereExists(function ($sub) {
                        $sub->select(\DB::raw(1))
                            ->from('attendance_weeks')
                            ->whereColumn('attendance_weeks.id', 'attendance_sessions.attendance_week_id')
                            ->whereNull('attendance_weeks.cancelled_at');
                    });
            })
            ->orderByDesc('attendance_sessions.start_time');

        if ($courseIds !== null) {
            $sessionsQuery->whereIn('attendance_sessions.course_id', $courseIds);
        }
        if (! empty($audienceCtx['class_ids'])) {
            AttendanceSessionClassScope::applyForClasses($sessionsQuery, $audienceCtx['class_ids']);
        }

        // Step 4: per-(course, week) dedupe. We over-fetch a little so the
        // final list after dedupe still has plenty of options, then keep
        // only the highest-id session per (course_id, attendance_week_id)
        // pair. Sessions without a week id are treated as their own group.
        $seenPairs = [];
        $sessions = $sessionsQuery
            ->limit(self::FILTER_LIMIT * 3)
            ->get()
            ->filter(function (AttendanceSession $s) use (&$seenPairs) {
                $key = (int) $s->course_id.':'.($s->attendance_week_id !== null ? (int) $s->attendance_week_id : 'na-'.$s->id);
                if (isset($seenPairs[$key])) {
                    return false;
                }
                $seenPairs[$key] = true;

                return true;
            })
            ->take(self::FILTER_LIMIT)
            ->values()
            ->map(function (AttendanceSession $s) {
                $when = optional($s->start_time)->format('M j, g:i A') ?: '—';

                return [
                    'id' => (int) $s->id,
                    'course_id' => (int) $s->course_id,
                    'label' => '#'.$s->id.' · '.$when.' · '.$s->mode,
                ];
            });

        // Students — scoped to the audience's classes
        $studentsQuery = Student::query()->select(['id', 'index_number', 'first_name', 'last_name', 'class_id']);
        if (! empty($audienceCtx['class_ids'])) {
            $studentsQuery->whereIn('class_id', $audienceCtx['class_ids']);
        } elseif ($audienceCtx['role'] === 'lecturer' && $courseIds !== null) {
            // For lecturers without an explicit class list, derive
            // students from the classes their courses are assigned to.
            $derivedClassIds = $this->classIdsForCourses($courseIds);
            if (! empty($derivedClassIds)) {
                $studentsQuery->whereIn('class_id', $derivedClassIds);
            }
        }
        $students = $studentsQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::FILTER_LIMIT)
            ->get()
            ->map(fn (Student $s) => [
                'id' => (int) $s->id,
                'label' => trim((string) ($s->first_name.' '.$s->last_name)).' · '.$s->index_number,
            ]);

        return response()->json([
            'courses' => $courses,
            'sessions' => $sessions,
            'students' => $students,
        ], 200, ['Cache-Control' => 'private, max-age=60']);
    }

    // ────────────────────────────────────────────────────────────
    // INTERNAL HELPERS
    // ────────────────────────────────────────────────────────────

    /**
     * Shared query base for the markers endpoint. Always:
     *   - skips rows without GPS (the map can't render them)
     *   - skips rows without a session id (legacy/imported data)
     *   - applies the audience scope (admin / lecturer / rep)
     *
     * @param  array{role: string, course_ids: list<int>|null, class_ids: list<int>}  $audienceCtx
     * @return Builder<Attendance>
     */
    private function baseMarkerQuery(array $audienceCtx): Builder
    {
        $query = Attendance::query()
            ->whereNotNull('attendances.attendance_session_id')
            ->whereNotNull('attendances.lat')
            ->whereNotNull('attendances.lng')
            ->activeWeeksOnly();

        $courseIds = $audienceCtx['course_ids'];
        if ($courseIds !== null) {
            if ($courseIds === []) {
                // No accessible courses → return an always-empty query.
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('attendances.course_id', $courseIds);
            }
        }

        $classIds = $audienceCtx['class_ids'];
        if (! empty($classIds)) {
            // Two-sided scope: student belongs to one of the classes AND
            // the underlying session belongs to one of them. Mirrors the
            // pattern used by RepCourseAccess::scopeAttendanceForRep.
            $query->whereHas('student', fn (Builder $s) => $s->whereIn('students.class_id', $classIds));
            AttendanceSessionClassScope::scopeAttendanceMarksForClasses($query, $classIds);
        }

        return $query;
    }

    /**
     * Build the audience scope for the signed-in caller. Returns null
     * if no eligible role is present (signed-out / plain student).
     *
     * Structure:
     *   role       : 'admin' | 'lecturer' | 'rep'
     *   course_ids : null = unbounded; otherwise a concrete allowlist
     *   class_ids  : [] = no class-level filter; otherwise an allowlist
     *
     * @return array{role: string, course_ids: list<int>|null, class_ids: list<int>}|null
     */
    private function resolveAudienceContext(Request $request): ?array
    {
        if ($request->session()->has('admin_id')) {
            return ['role' => 'admin', 'course_ids' => null, 'class_ids' => []];
        }

        if ($request->session()->has('lecturer_id')) {
            $lecturer = LecturerAccess::lecturerFromSession($request);
            if (! $lecturer instanceof Lecturer) {
                return null;
            }
            $courseIds = $lecturer->courses()->pluck('id')->map(fn ($id) => (int) $id)->all();

            return ['role' => 'lecturer', 'course_ids' => $courseIds, 'class_ids' => []];
        }

        if ($request->session()->has('student_id')) {
            $student = Student::find($request->session()->get('student_id'));
            if (! $student || ! $student->isClassRep()) {
                return null;
            }
            $courseIds = RepCourseAccess::coursesQueryForRep($student)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $classIds = $student->repManagedClassIds()
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            return ['role' => 'rep', 'course_ids' => $courseIds, 'class_ids' => $classIds];
        }

        return null;
    }

    /**
     * Distinct class IDs assigned to the given course IDs.
     *
     * @param  list<int>  $courseIds
     * @return list<int>
     */
    private function classIdsForCourses(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }
        $courses = Course::query()->whereIn('id', $courseIds)->get(['id', 'class_id']);
        $ids = [];
        foreach ($courses as $c) {
            foreach (RepCourseAccess::courseClassIdsForAccess($c) as $classId) {
                $ids[$classId] = true;
            }
        }

        return array_keys($ids);
    }

    private function audienceCanSeeSession(array $audienceCtx, AttendanceSession $session): bool
    {
        if ($audienceCtx['role'] === 'admin') {
            return true;
        }
        if ($audienceCtx['course_ids'] !== null && ! in_array((int) $session->course_id, $audienceCtx['course_ids'], true)) {
            return false;
        }
        if (! empty($audienceCtx['class_ids'])) {
            foreach ($audienceCtx['class_ids'] as $classId) {
                if (AttendanceSessionClassScope::sessionBelongsToClass($session, (int) $classId)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private function audienceCanSeeAttendance(array $audienceCtx, Attendance $attendance): bool
    {
        if ($audienceCtx['role'] === 'admin') {
            return true;
        }
        if ($audienceCtx['course_ids'] !== null && ! in_array((int) $attendance->course_id, $audienceCtx['course_ids'], true)) {
            return false;
        }
        if (! empty($audienceCtx['class_ids'])) {
            $studentClassId = (int) ($attendance->student?->class_id ?? 0);
            if ($studentClassId <= 0 || ! in_array($studentClassId, $audienceCtx['class_ids'], true)) {
                return false;
            }
        }

        return true;
    }

    private function requireRep(Request $request): Student|Response
    {
        if ($block = RoleAccess::requireClassRep($request)) {
            return $block;
        }
        $student = Student::find($request->session()->get('student_id'));
        if (! $student) {
            return redirect()->route('student.email-prompt');
        }

        return $student;
    }

    private function resolveStaffAudience(Request $request): ?string
    {
        if ($request->session()->has('admin_id')) {
            return 'admin';
        }
        if ($request->session()->has('lecturer_id')) {
            return 'lecturer';
        }

        return null;
    }

    /**
     * URLs for the JSON endpoints, embedded in the view so the
     * Blade-rendered JS never needs to hard-code paths. The summary +
     * details routes are templates because they take a path parameter;
     * the JS swaps the placeholder for the real id at click time.
     *
     * @return array<string, string>
     */
    private function apiRouteNames(): array
    {
        return [
            'markers' => route('dashboard.attendance-map.markers'),
            'summary_pattern' => route('dashboard.attendance-map.summary', ['session' => '__SESSION__']),
            'details_pattern' => route('dashboard.attendance-map.details', ['attendance' => '__ATTENDANCE__']),
            'filters' => route('dashboard.attendance-map.filters'),
        ];
    }
}
