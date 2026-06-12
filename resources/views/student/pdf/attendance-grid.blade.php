<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>My attendance — {{ $student['name'] ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 12mm 10mm 12mm 10mm; }
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
        .banner h1 {
            margin: 4px 0 4px 0;
            font-size: 16px;
            font-weight: bold;
            color: #ffffff;
        }
        .banner .org {
            font-size: 9px;
            color: #e0ecff;
            line-height: 1.45;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .meta td {
            padding: 7px 12px;
            border-bottom: 1px solid #e7e5e4;
            vertical-align: top;
        }
        .meta tr:nth-child(odd) td { background: #f5f5f4; }
        .meta tr:nth-child(even) td { background: #fafaf9; }
        .meta .label {
            width: 28%;
            font-weight: bold;
            color: #0b3c98;
        }
        .summary-strip {
            background: #ecfdf5;
            border-top: 1px solid #a7f3d0;
            border-bottom: 1px solid #a7f3d0;
            padding: 8px 12px;
            font-size: 10px;
            color: #065f46;
        }
        .summary-strip strong { color: #064e3b; font-size: 11px; }
        .course-block {
            border-top: 1px solid #e7e5e4;
            padding: 10px 12px 12px 12px;
            page-break-inside: avoid;
        }
        .course-block:first-of-type { border-top: 0; }
        .course-head {
            margin: 0 0 6px 0;
            font-size: 11px;
            font-weight: bold;
            color: #0b3c98;
        }
        .course-sub {
            font-size: 9px;
            color: #6b7280;
            margin: 0 0 8px 0;
        }
        .course-stats {
            font-size: 9px;
            color: #475569;
            margin: 0 0 6px 0;
        }
        .course-stats strong { color: #0f172a; }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.grid th {
            background: #0b3c98;
            color: #fef9c3;
            border: 1px solid #082c71;
            padding: 4px 0;
            text-align: center;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        table.grid td {
            border: 1px solid #d6d3d1;
            padding: 4px 0;
            text-align: center;
            font-size: 9px;
            background: #ffffff;
        }
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
        td.cell-cancelled {
            background: #fff7ed;
            color: #b91c1c;
            font-weight: bold;
            font-size: 8.5px;
        }
        th.cell-cancelled-head {
            background: #fed7aa !important;
            color: #7c2d12 !important;
            border-color: #c2410c !important;
        }
        .footer-note {
            font-size: 8px;
            color: #78716c;
            padding: 8px 12px 12px 12px;
            border-top: 1px solid #e7e5e4;
        }
        .pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
            background: #e0f2fe;
            color: #075985;
            border: 1px solid #38bdf8;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="banner">
            <span class="tag">My attendance</span>
            <h1>{{ $student['institution_name'] ?? config('app.name') }}</h1>
            <div class="org">
                @if(!empty($student['faculty_name']))<div>{{ $student['faculty_name'] }}</div>@endif
                @if(!empty($student['department_name']))<div>{{ $student['department_name'] }}</div>@endif
            </div>
        </div>

        <table class="meta" cellspacing="0">
            <tr>
                <td class="label">Name</td>
                <td>{{ $student['name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Index No.</td>
                <td>{{ $student['index_number'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Class</td>
                <td>{{ $student['class_name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Generated</td>
                <td>{{ now()->format('M d, Y H:i') }}</td>
            </tr>
        </table>

        <div class="summary-strip">
            Courses: <strong>{{ $summary['course_count'] }}</strong>
            &nbsp;·&nbsp; Classes held: <strong>{{ $summary['classes_held'] }}</strong>
            &nbsp;·&nbsp; Attended: <strong>{{ $summary['classes_attended'] }}</strong>
            @if(($summary['classes_cancelled'] ?? 0) > 0)
                &nbsp;·&nbsp; Cancelled: <strong>{{ $summary['classes_cancelled'] }}</strong>
            @endif
            &nbsp;·&nbsp; Overall: <strong>{{ $summary['percent'] }}%</strong>
        </div>

        @if(empty($courses))
            <div class="footer-note" style="text-align:center; padding: 28px 12px;">
                No courses linked to your class yet. Ask an admin to add your courses.
            </div>
        @else
            @foreach($courses as $course)
                @php
                    $weeks = $course['weeks'] ?? [];
                    // Show at most 14 weeks per row, then wrap into the
                    // next visual row so a 30-week semester still fits
                    // a portrait A4 sheet without overflow.
                    $maxPerRow = 14;
                    $weekChunks = array_chunk($weeks, $maxPerRow);
                @endphp
                <div class="course-block">
                    <p class="course-head">{{ $course['course_name'] }}@if(!empty($course['course_code'])) <span style="color:#475569; font-weight:normal;">— {{ $course['course_code'] }}</span>@endif</p>
                    @if(!empty($course['lecturer_name']) || !empty($course['venue']))
                        <p class="course-sub">
                            @if(!empty($course['lecturer_name'])){{ $course['lecturer_name'] }}@endif
                            @if(!empty($course['lecturer_name']) && !empty($course['venue']))&nbsp;·&nbsp;@endif
                            @if(!empty($course['venue'])){{ $course['venue'] }}@endif
                        </p>
                    @endif

                    <p class="course-stats">
                        <span class="pill">{{ $course['percent'] }}%</span>
                        &nbsp;Attended <strong>{{ $course['present_count'] }}</strong> of <strong>{{ $course['held_count'] }}</strong> classes
                        @if(($course['cancelled_count'] ?? 0) > 0)
                            &nbsp;·&nbsp; {{ $course['cancelled_count'] }} cancelled
                        @endif
                    </p>

                    @if(empty($weeks))
                        <p class="course-sub" style="font-style: italic;">No classes held yet for this course.</p>
                    @else
                        @foreach($weekChunks as $chunk)
                            <table class="grid" style="margin-top:{{ $loop->first ? '0' : '4px' }};">
                                <thead>
                                    <tr>
                                        @foreach($chunk as $w)
                                            <th @if(($w['status'] ?? '') === 'cancelled') class="cell-cancelled-head" @endif>W{{ $w['week_number'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @foreach($chunk as $w)
                                            @php $status = $w['status'] ?? 'absent'; @endphp
                                            @if($status === 'cancelled')
                                                <td class="cell-cancelled">OFF</td>
                                            @elseif($status === 'present')
                                                <td><span class="mark mark-present">&#10004;</span></td>
                                            @else
                                                <td><span class="mark mark-absent">&#10008;</span></td>
                                            @endif
                                        @endforeach
                                    </tr>
                                </tbody>
                            </table>
                        @endforeach
                    @endif
                </div>
            @endforeach
        @endif

        <div class="footer-note">
            <strong>Legend</strong>
            &nbsp;<span class="mark mark-present">&#10004;</span>&nbsp;present
            &nbsp;&nbsp;<span class="mark mark-absent">&#10008;</span>&nbsp;absent
            &nbsp;&nbsp;<span style="background:#fff7ed; color:#b91c1c; padding:1px 4px; border-radius:3px; font-weight:bold;">OFF</span>&nbsp;cancelled
            &nbsp;&nbsp;|&nbsp;&nbsp; Generated automatically &mdash; verified against the attendance ledger.
        </div>
    </div>
</body>
</html>
