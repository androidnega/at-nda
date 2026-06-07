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
        }
        table.grid th {
            background: #0b3c98;
            color: #fef9c3;
            border: 1px solid #082c71;
            padding: 7px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table.grid td {
            border: 1px solid #d6d3d1;
            padding: 5px 6px;
            font-size: 10px;
        }
        table.grid tbody tr:nth-child(even) td {
            background: #fafaf9;
        }
        table.grid tbody tr:nth-child(odd) td {
            background: #ffffff;
        }
        .week-col {
            width: 28px;
            text-align: center;
        }
        .check {
            color: #0b3c98;
            font-weight: bold;
        }
        .miss {
            color: #a16207;
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
        /* Stack the word CANCELLED letter-by-letter so it reads top-to-bottom
           inside narrow week columns. dompdf doesn't reliably honour CSS
           writing-mode / transform: rotate, so this stacked approach is the
           most portable way to get vertical text in the PDF. */
        .week-cancelled-vert {
            display: inline-block;
            font-size: 7px;
            font-weight: bold;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            line-height: 1.05;
            text-align: center;
            padding: 1px 0;
        }
        .week-cancelled-vert span {
            display: block;
        }
        td.week-cancelled-cell {
            background: repeating-linear-gradient(
                45deg,
                #fff7ed,
                #fff7ed 3px,
                #fed7aa 3px,
                #fed7aa 4px
            ) !important;
            color: #a8a29e;
        }
        /* Header for a cancelled week column — same striped background as
           the cells so the whole vertical column reads as one cancelled
           block without us having to repeat the "CANCELLED" label in
           every student row (which used to blow up row heights). */
        th.week-cancelled-header {
            background: repeating-linear-gradient(
                45deg,
                #fef3c7,
                #fef3c7 3px,
                #fde68a 3px,
                #fde68a 4px
            ) !important;
            color: #78350f !important;
        }
        th.week-cancelled-header .week-cancelled {
            color: #b91c1c;
            font-weight: bold;
        }
        td.week-cancelled-cell .week-cancelled-marker {
            color: #b45309;
            font-weight: bold;
            font-size: 8px;
            letter-spacing: 0.08em;
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
        .cancelled-block {
            margin-top: 14px;
            padding: 8px 10px;
            border: 1px solid #fcd34d;
            background: #fffbeb;
            border-radius: 4px;
        }
        .cancelled-block h3 {
            font-size: 10px;
            font-weight: bold;
            color: #92400e;
            margin: 0 0 5px 0;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .cancelled-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .cancelled-list li {
            font-size: 9px;
            color: #422006;
            padding: 3px 0;
            border-top: 1px dotted #fde68a;
        }
        .cancelled-list li:first-child {
            border-top: 0;
        }
        .cancelled-list .cw-label {
            font-weight: bold;
            color: #78350f;
        }
        .cancelled-list .cw-reason {
            color: #4b3104;
            font-style: italic;
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

        <p class="weeks-summary">
            Classes held: <strong>{{ $weeks->reject(fn($w) => $w->isCancelled())->count() }}</strong>
            @if($weeks->filter(fn($w) => $w->isCancelled())->isNotEmpty())
                · Cancelled: <strong>{{ $weeks->filter(fn($w) => $w->isCancelled())->count() }}</strong>
            @endif
            · Total weeks shown: <strong>{{ $weeks->count() }}</strong>
        </p>

        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>Index No.</th>
                        <th>Program</th>
                        @foreach($weeks as $w)
                        <th class="week-col @if($w->isCancelled()) week-cancelled-header @endif">W{{ $w->week_number }}@if($w->isCancelled())<br><span class="week-cancelled">Off</span>@endif</th>
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
                        <td>{{ $row['student']->getProgramLabel() }}</td>
                        @foreach($weeks as $w)
                        {{-- Cancelled weeks: keep the cell deliberately empty (with the
                             striped amber background) so every student row stays the same
                             short height. The "W# / Off" column header already labels the
                             entire column as cancelled, and the Cancelled-weeks block
                             below the table carries the details — so a per-cell CANCELLED
                             label is just visual noise that stretches each row vertically. --}}
                        <td class="week-col @if($w->isCancelled()) week-cancelled-cell @endif">
                            @if($w->isCancelled())
                                <span class="week-cancelled-marker" aria-label="Cancelled">&middot;</span>
                            @elseif(isset($row['weeks'][$w->week_number]))
                                @if($row['weeks'][$w->week_number] === true)
                                    <span class="check">&#10003;</span>
                                @else
                                    <span class="miss">&times;</span>
                                @endif
                            @else
                                <span style="color:#a8a29e;">—</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(!empty($cancelledWeeks) && $cancelledWeeks->isNotEmpty())
            <div class="cancelled-block">
                <h3>Cancelled weeks</h3>
                <ul class="cancelled-list">
                    @foreach($cancelledWeeks as $cw)
                        <li>
                            <span class="cw-label">Week {{ $cw->week_number }}@if($cw->week_date) ({{ $cw->week_date->format('M j, Y') }})@endif:</span>
                            @if($cw->cancellation_note)
                                <span class="cw-reason">"{{ $cw->cancellation_note }}"</span>
                            @else
                                <span class="cw-reason">No reason recorded.</span>
                            @endif
                            @if($cw->cancelled_by)
                                — by {{ $cw->cancelled_by }}
                            @endif
                            @if($cw->cancelled_at)
                                <span style="color:#a16207;">· {{ $cw->cancelled_at->format('M j, Y') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>
</body>
</html>
