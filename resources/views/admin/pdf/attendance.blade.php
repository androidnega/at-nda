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
        .footer-note {
            font-size: 8px;
            color: #78716c;
            padding: 8px 12px 12px 12px;
            border-top: 1px solid #e7e5e4;
        }
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
                <td class="value">
                    {{ $lecturerDisplay }}
                    <span style="display:inline-block;margin-left:6px;padding:2px 6px;border-radius:3px;font-size:9px;font-weight:bold;{{ $lecturerStatus === 'absent' ? 'background:#ffe4e6;color:#be123c;' : 'background:#dcfce7;color:#166534;' }}">
                        Lecturer {{ $lecturerStatus === 'absent' ? 'Absent' : 'Present' }}
                    </span>
                </td>
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
                        <th>Index No.</th>
                        <th>Program</th>
                        @foreach($weeks as $w)
                        <th class="week-col">W{{ $w->week_number }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>LECTURER</strong> - {{ $lecturerDisplay }}</td>
                        <td>Lecturer</td>
                        @foreach($weeks as $w)
                        <td class="week-col">
                            @if(!empty($lecturerWeekStatus[$w->week_number]))
                                <span class="check">&#10003;</span>
                            @else
                                <span class="miss">&times;</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @foreach($attendanceByStudent as $row)
                    <tr>
                        <td>{{ $row['student']->index_number }}</td>
                        <td>{{ $row['student']->getProgramLabel() }}</td>
                        @foreach($weeks as $w)
                        <td class="week-col">
                            @if(isset($row['weeks'][$w->week_number]))
                                @if($row['weeks'][$w->week_number])
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

        <p class="footer-note">
            {{ config('app.name', 'Attendance') }} &mdash; weekly marks indicate recorded attendance for each teaching week.
        </p>
    </div>
</body>
</html>
