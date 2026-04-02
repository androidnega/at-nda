/// Turns one API `warnings[]` entry into a user-facing line (prefers course-specific copy).
String formatAbsenceWarningLine(Map<String, dynamic> w) {
  final msg = w['message']?.toString().trim();
  if (msg != null && msg.isNotEmpty) return msg;

  final course = (w['course_name'] ?? w['course_title'] ?? w['course'])?.toString().trim();
  final missedRaw = w['missed_count'] ?? w['missed'] ?? w['count'] ?? w['absences'];
  int? missed;
  if (missedRaw is int) {
    missed = missedRaw;
  } else if (missedRaw is num) {
    missed = missedRaw.toInt();
  } else {
    missed = int.tryParse(missedRaw?.toString() ?? '');
  }

  if (course != null && course.isNotEmpty && missed != null && missed > 0) {
    final unit = missed == 1 ? 'class' : 'classes';
    return "You've missed $missed $course $unit";
  }
  if (course != null && course.isNotEmpty) {
    return "Absence notice: $course";
  }
  return w.toString();
}
