<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        * { box-sizing: border-box; }
        /* Tight, explicit page margins so the .sheet exactly fills the page
           without spilling a stray row onto a new (mostly-empty) page. */
        @page {
            margin: 12mm 10mm 12mm 10mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #292524;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .sheet {
            background: #ffffff;
            border: 1px solid #e7e5e4;
        }
        .banner {
            background: #0b3c98;
            color: #ffffff;
            padding: 14px 16px 16px 16px;
            border-bottom: 4px solid #facc15;
        }
        .banner-flex {
            width: 100%;
            border-collapse: collapse;
        }
        .banner-flex td {
            vertical-align: middle;
        }
        .banner-left {
            width: 72px;
            padding-right: 12px;
        }
        .banner-right {
            text-align: left;
        }
        .banner h1 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.02em;
            color: #ffffff;
        }
        .banner .tag {
            display: inline-block;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #fef08a;
            border: 1px solid rgba(255,255,255,0.35);
            padding: 2px 8px;
            border-radius: 2px;
        }
        .banner .org {
            margin-top: 6px;
            font-size: 9px;
            color: #e0ecff;
            line-height: 1.45;
        }
        .logo {
            width: 52px;
            height: 52px;
            border-radius: 6px;
            border: 1px solid rgba(250, 204, 21, 0.7);
            background: #fff;
            object-fit: cover;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 10px;
        }
        .meta td {
            padding: 8px 12px;
            border-bottom: 1px solid #e7e5e4;
            vertical-align: top;
        }
        .meta tr:nth-child(odd) td {
            background: #f5f5f4;
        }
        .meta tr:nth-child(even) td {
            background: #fafaf9;
        }
        .meta .label {
            width: 28%;
            font-weight: bold;
            color: #0b3c98;
        }
        .meta .value {
            color: #44403c;
        }
        .table-wrap {
            padding: 0;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            /* Fixed layout so dompdf honours the per-column widths we
               set on the <colgroup> below, instead of auto-sizing to
               content and overflowing the page when there are many
               week columns. */
            table-layout: fixed;
        }
        table.grid th {
            background: #0b3c98;
            color: #fef9c3;
            border: 1px solid #082c71;
            padding: 5px 3px;
            text-align: left;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            overflow: hidden;
        }
        table.grid td {
            border: 1px solid #d6d3d1;
            padding: 3px 4px;
            font-size: 9px;
            overflow: hidden;
            word-wrap: break-word;
        }
        table.grid tbody tr:nth-child(even) td {
            background: #fafaf9;
        }
        table.grid tbody tr:nth-child(odd) td {
            background: #ffffff;
        }
        .week-col {
            text-align: center;
            padding: 3px 0;
        }
        /* Repeat <thead> on every printed page — dompdf supports this
           when display: table-header-group is set, and it gives us a
           free per-page banner of W1..Wn (including the amber
           CANCELLED column headers). */
        table.grid thead { display: table-header-group; }
        table.grid tbody { display: table-row-group; }
        /* Present / absent pill badges. dompdf 3.x bundles DejaVu Sans
           which does NOT include the colour emoji ✅ ❌ glyphs (U+2705 /
           U+274C). The bold check (U+2714) and bold cross (U+2718) ARE
           in DejaVu, so we use those wrapped in a small coloured pill to
           give the same "green present / red absent" reading at a
           glance. */
        .mark {
            display: inline-block;
            min-width: 12px;
            padding: 1px 2px;
            font-size: 9px;
            font-weight: bold;
            line-height: 1;
            border-radius: 6px;
            text-align: center;
        }
        .mark-present {
            color: #14532d;
            background: #bbf7d0;
            border: 1px solid #16a34a;
        }
        .mark-absent {
            color: #7f1d1d;
            background: #fecaca;
            border: 1px solid #dc2626;
        }
        .mark-blank {
            color: #a8a29e;
            font-weight: normal;
        }
        .week-cancelled {
            font-size: 8px;
            font-weight: bold;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        /* Small inline pill used to flag class reps inside the Index No. cell. */
        .rep-badge {
            display: inline-block;
            margin-left: 4px;
            padding: 1px 5px 2px 5px;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #ffffff;
            background: #0b3c98;
            border-radius: 3px;
            vertical-align: middle;
        }
        .rep-badge.assist {
            background: #2563eb;
        }
        .rep-badge.main {
            background: #b45309;
            color: #fffbeb;
        }
        /* Cancelled-week column: every cell prints one letter of the
           word CANCELLED, stacked vertically down the column with the
           word centred against the rows — exactly how a lecturer writes
           it in a paper register. dompdf doesn't reliably honour CSS
           writing-mode / transform: rotate(), so the per-row letter
           stack is the most portable way to get that vertical look. */
        td.week-cancelled-cell {
            background: #fff7ed !important;
            color: #b91c1c;
            padding: 4px 2px;
        }
        td.week-cancelled-cell .cancelled-letter {
            display: inline-block;
            font-weight: bold;
            font-size: 11px;
            line-height: 1;
            color: #b91c1c;
            letter-spacing: 0;
        }
        th.week-cancelled-header {
            background: #fed7aa !important;
            color: #7c2d12 !important;
            border-color: #c2410c !important;
        }
        th.week-cancelled-header .week-cancelled {
            color: #7c2d12;
            font-weight: bold;
        }
        .footer-note {
            font-size: 8px;
            color: #78716c;
            padding: 8px 12px 12px 12px;
            border-top: 1px solid #e7e5e4;
        }
        .weeks-summary {
            font-size: 10px;
            color: #44403c;
            margin: 0 0 8px 0;
        }
        .weeks-summary strong {
            color: #1c1917;
        }
        /* Keep each student row intact across page breaks so we don't end up
           with a trailing page that contains nothing but a half-row. */
        .sheet { page-break-after: avoid; }
        table.grid tbody tr { page-break-inside: avoid; }
        .table-wrap { page-break-after: avoid; }
        body { background: #ffffff; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="banner">
            <table class="banner-flex" cellspacing="0">
                <tr>
                    <td class="banner-left">
                        @if(!empty($classLogoDataUri))
                            <img src="{{ $classLogoDataUri }}" alt="Class logo" class="logo">
                        @endif
                    </td>
                    <td class="banner-right">
                        <span class="tag">Attendance register</span>
                        <h1>{{ $institutionName }}</h1>
                        <div class="org">
                            <div>{{ $facultyName }}</div>
                            <div>{{ $departmentName }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="meta" cellspacing="0">
            <tr>
                <td class="label">Lecturer</td>
                <td class="value">{{ $lecturerDisplay }}</td>
            </tr>
            <tr>
                <td class="label">Course</td>
                <td class="value">{{ $courseTitle }}</td>
            </tr>
            <tr>
                <td class="label">Class</td>
                <td class="value">{{ $className }}</td>
            </tr>
            <tr>
                <td class="label">Venue</td>
                <td class="value">{{ $venueDisplay }}</td>
            </tr>
            <tr>
                <td class="label">Generated</td>
                <td class="value">{{ now()->format('M d, Y H:i') }}</td>
            </tr>
        </table>

        @php
            $heldCount = $weeks->filter(fn($w) => $w->id && ! $w->isCancelled())->count();
            $cancelledCount = $weeks->filter(fn($w) => $w->isCancelled())->count();
            $pendingCount = $weeks->filter(fn($w) => ! $w->id)->count();
        @endphp
        <p class="weeks-summary">
            Semester weeks: <strong>{{ $weeks->count() }}</strong>
            · Held: <strong>{{ $heldCount }}</strong>
            @if($cancelledCount > 0)
                · Cancelled: <strong>{{ $cancelledCount }}</strong>
            @endif
            @if($pendingCount > 0)
                · Not held yet: <strong>{{ $pendingCount }}</strong>
            @endif
        </p>

        <div class="table-wrap">
            @php
                // Carve up 100% of the table width between the two
                // fixed columns and the variable-count week columns.
                // The fixed columns are tuned tight against their real
                // content (an index number like BC/ITD/24/025 is ~13
                // chars at 8px so ~25mm) so a 14-column landscape grid
                // sits compactly without horizontal overflow.
                $count = max($weeks->count(), 1);
                if (($orientation ?? 'portrait') === 'landscape') {
                    $hashW = 3; $indexW = 14;
                } else {
                    $hashW = 4; $indexW = 20;
                }
                if ($count > 10) {
                    $indexW = max(12, $indexW - 2);
                }
                $weekTotal = max(100 - ($hashW + $indexW), 30);
                $weekW = round($weekTotal / $count, 2);
            @endphp
            <table class="grid">
                <colgroup>
                    <col style="width: {{ $hashW }}%">
                    <col style="width: {{ $indexW }}%">
                    @foreach($weeks as $w)
                        <col style="width: {{ $weekW }}%">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Index No.</th>
                        @foreach($weeks as $w)
                        <th class="week-col @if($w->isCancelled()) week-cancelled-header @endif">W{{ $w->week_number }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        $repRolesByStudent = $repRolesByStudent ?? [];
                    @endphp
                    @foreach($attendanceByStudent as $idx => $row)
                    @php
                        $studentId = (int) ($row['student']->id ?? 0);
                        $repRole = $repRolesByStudent[$studentId] ?? null;
                    @endphp
                    <tr>
                        <td class="week-col" style="font-weight:bold;">{{ $idx + 1 }}</td>
                        <td>
                            {{ $row['student']->index_number }}
                            @if($repRole === \App\Models\ClassRep::ROLE_REP)
                                <span class="rep-badge main">Rep</span>
                            @elseif($repRole === \App\Models\ClassRep::ROLE_ASSIST)
                                <span class="rep-badge assist">Asst</span>
                            @endif
                        </td>
                        @foreach($weeks as $w)
                        {{-- Cancelled column: each row contributes one letter
                             of CANCELLED, stacked vertically down the column
                             (the controller pre-centred the word against the
                             total row count). Held weeks show a green check
                             pill for present, a red cross pill for absent. --}}
                        <td class="week-col @if($w->isCancelled()) week-cancelled-cell @endif">
                            @if($w->isCancelled())
                                @php $letter = $cancelledLetterByWeekAndRow[$w->week_number][$idx] ?? ''; @endphp
                                @if($letter !== '')
                                    <span class="cancelled-letter">{{ $letter }}</span>
                                @endif
                            @elseif(($row['weeks'][$w->week_number] ?? null) === true)
                                <span class="mark mark-present" aria-label="Present">&#10004;</span>
                            @elseif(($row['weeks'][$w->week_number] ?? null) === false)
                                <span class="mark mark-absent" aria-label="Absent">&#10008;</span>
                            @else
                                <span class="mark-blank" aria-label="Not held yet">&mdash;</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
</body>
</html>
