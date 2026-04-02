<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Attendance Records</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
      .muted { color: #475569; }
      h1 { font-size: 18px; margin: 0 0 6px; }
      .meta { margin: 0 0 14px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #e2e8f0; padding: 8px; vertical-align: top; }
      th { background: #f8fafc; text-align: left; font-weight: 700; }
      .right { text-align: right; }
    </style>
  </head>
  <body>
    <h1>Attendance Records</h1>
    <div class="meta muted">
      <div><strong>Course:</strong> {{ $course->course_name }}{{ $course->course_code ? ' ('.$course->course_code.')' : '' }}</div>
      <div><strong>Session ID:</strong> {{ $session->id }}</div>
      <div><strong>Generated:</strong> {{ $generated_at->toDateTimeString() }}</div>
      <div><strong>Total records:</strong> {{ count($records) }}</div>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width: 46%;">Student</th>
          <th style="width: 24%;">Index number</th>
          <th style="width: 30%;">Time marked</th>
        </tr>
      </thead>
      <tbody>
        @forelse($records as $r)
          <tr>
            <td>{{ $r['name'] ?? '—' }}</td>
            <td>{{ $r['index_number'] ?? '' }}</td>
            <td>{{ $r['marked_at'] ?? '' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="3" class="muted">No attendance records for this session.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </body>
</html>

