/// One row from `POST /api/rep/courses` (`courses` array).
class RepCourse {
  final int courseId;
  final String courseName;
  final String? courseCode;
  final int? classId;
  final String repRole;
  final bool canOpenSession;
  final bool hasSchedule;
  final Map<String, dynamic>? activeSession;

  RepCourse({
    required this.courseId,
    required this.courseName,
    this.courseCode,
    this.classId,
    required this.repRole,
    required this.canOpenSession,
    required this.hasSchedule,
    this.activeSession,
  });

  factory RepCourse.fromJson(Map<String, dynamic> j) {
    Map<String, dynamic>? active;
    final a = j['active_session'];
    if (a is Map) {
      active = Map<String, dynamic>.from(a);
    }
    return RepCourse(
      courseId: int.tryParse('${j['course_id']}') ?? 0,
      courseName: j['course_name']?.toString() ?? 'Course',
      courseCode: j['course_code']?.toString(),
      classId: int.tryParse('${j['class_id']}'),
      repRole: j['rep_role']?.toString() ?? 'assist',
      canOpenSession: j['can_open_session'] == true,
      hasSchedule: j['has_schedule'] == true,
      activeSession: active,
    );
  }

  bool get isMainRep => repRole == 'rep';

  String get roleLabel => isMainRep ? 'Main rep' : 'Assist rep';

  int? get activeSessionId {
    final s = activeSession;
    if (s == null) return null;
    final id = s['id'];
    if (id is int) return id;
    if (id is num) return id.toInt();
    return int.tryParse(id?.toString() ?? '');
  }

  String? get qrToken {
    final s = activeSession;
    if (s == null) return null;
    final t = s['qr_token']?.toString();
    if (t == null || t.isEmpty) return null;
    return t;
  }
}
