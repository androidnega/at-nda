<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #292524;
            margin: 0;
            padding: 12px;
            background: #fafaf9;
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
            padding: 0 0 8px 0;
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
        }
        .footer-note {
            font-size: 8px;
            color: #78716c;
            padding: 8px 12px 12px 12px;
            border-top: 1px solid #e7e5e4;
        }
        /* Diagonal "AT-ENDA" watermarks rendered on every PDF page.
           dompdf honours position: fixed by repainting the elements on each
           page, so three of these create a stripe of branding without
           obscuring the data underneath. Opacity is intentionally low so the
           grid stays legible when printed. */
        .wm {
            position: fixed;
            left: -10%;
            width: 120%;
            text-align: center;
            transform: rotate(-30deg);
            opacity: 0.07;
            color: #0b3c98;
            font-weight: bold;
            font-size: 78px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            z-index: 0;
        }
        .wm-top    { top: 12%; }
        .wm-mid    { top: 44%; }
        .wm-bot    { top: 76%; }
        .wm-text {
            display: inline-block;
            vertical-align: middle;
        }
        .wm-logo {
            display: inline-block;
            vertical-align: middle;
            width: 64px;
            height: 64px;
            margin-right: 18px;
        }
        /* Make sure the main sheet sits above the watermark layer. */
        .sheet {
            position: relative;
            z-index: 1;
            background: transparent;
        }
        body { background: #ffffff; }
    </style>
</head>
<body>
    @php
        // Inline the brand mark as a data URI so dompdf doesn't have to hit
        // the filesystem on every render. SVG keeps the file size negligible
        // and stays crisp at any zoom.
        $brandSvg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0b3c98"/>
      <stop offset="100%" stop-color="#1d4ed8"/>
    </linearGradient>
  </defs>
  <rect x="4" y="4" width="56" height="56" rx="14" fill="url(#g)"/>
  <path d="M21 35c0-8.8 5.7-14 13.2-14 2.9 0 5.6.8 7.8 2.3l-2.8 4.7c-1.3-.8-2.8-1.3-4.5-1.3-4.1 0-7 3-7 8s2.9 8 7 8c1.7 0 3.3-.5 4.6-1.4l2.8 4.7c-2.2 1.5-4.9 2.4-8 2.4-7.6 0-13.1-5.2-13.1-14.4z" fill="#fff"/>
</svg>';
        $brandDataUri = 'data:image/svg+xml;base64,'.base64_encode($brandSvg);
    @endphp

    {{-- Diagonal brand watermarks. They sit behind the sheet via z-index. --}}
    <div class="wm wm-top">
        <img src="{{ $brandDataUri }}" alt="" class="wm-logo">
        <span class="wm-text">a-tenda</span>
    </div>
    <div class="wm wm-mid">
        <img src="{{ $brandDataUri }}" alt="" class="wm-logo">
        <span class="wm-text">a-tenda</span>
    </div>
    <div class="wm wm-bot">
        <img src="{{ $brandDataUri }}" alt="" class="wm-logo">
        <span class="wm-text">a-tenda</span>
    </div>

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

        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>Index No.</th>
                        <th>Program</th>
                        @foreach($weeks as $w)
                        <th class="week-col">W{{ $w->week_number }}@if($w->isCancelled())<br><span class="week-cancelled">Off</span>@endif</th>
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
                        <td class="week-col @if($w->isCancelled()) week-cancelled-cell @endif">
                            @if($w->isCancelled())
                                <span class="week-cancelled-vert" aria-label="Cancelled">
                                    <span>C</span><span>A</span><span>N</span><span>C</span><span>E</span><span>L</span><span>L</span><span>E</span><span>D</span>
                                </span>
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

        @php
            $repRolesByStudent = $repRolesByStudent ?? [];
            $hasReps = !empty($repRolesByStudent);
        @endphp
        <p class="footer-note">
            {{ config('app.name', 'Attendance') }} &mdash; weekly marks indicate recorded attendance for each teaching week.
            @if($hasReps)
                <span style="margin-left:10px;">Legend: <span class="rep-badge main">Rep</span> class rep &middot; <span class="rep-badge assist">Asst</span> assistant rep.</span>
            @endif
        </p>
    </div>
</body>
</html>
