import 'dart:convert';

import '../services/api_service.dart';
import '../utils/session_attendance_payload.dart';

/// Local attendance record for offline storage before syncing to Laravel.
class AttendanceRecord {
  final int? id;
  /// Laravel session PK — always prefer this in POST /api/attendance when set.
  final int? sessionId;
  /// API endpoint path (relative), e.g. `attendance` or `attendance/checkout`.
  final String endpoint;
  final String studentIndex;
  /// From session JSON [course_id] only; 0 = omit in API payload.
  final int courseId;
  /// From session JSON [week_id] only; 0 = omit in API payload.
  final int weekId;
  final double lat;
  final double lng;
  /// Reported horizontal accuracy in meters at capture time (Geolocator).
  /// 0 / negative = unknown; never sent to the server when not finite.
  final double? accuracyMeters;
  final String? qrCode;
  final String timestamp;
  final bool synced;
  final List<double>? faceDescriptor;

  AttendanceRecord({
    this.id,
    this.sessionId,
    this.endpoint = 'attendance',
    required this.studentIndex,
    required this.courseId,
    required this.weekId,
    required this.lat,
    required this.lng,
    this.accuracyMeters,
    this.qrCode,
    required this.timestamp,
    this.synced = false,
    this.faceDescriptor,
  });

  factory AttendanceRecord.fromMap(Map<String, dynamic> map) => AttendanceRecord(
        id: map['id'] as int?,
        sessionId: parseApiInt(map['session_id']),
        endpoint: (map['endpoint']?.toString().trim().isNotEmpty ?? false)
            ? map['endpoint'].toString().trim()
            : 'attendance',
        studentIndex: map['student_index'] as String,
        courseId: parseApiInt(map['course_id']) ?? 0,
        weekId: parseApiInt(map['week_id']) ?? 0,
        lat: (map['lat'] as num).toDouble(),
        lng: (map['lng'] as num).toDouble(),
        accuracyMeters: (map['accuracy'] is num)
            ? (map['accuracy'] as num).toDouble()
            : null,
        qrCode: map['qr_code'] as String?,
        timestamp: map['timestamp'] as String,
        synced: (map['synced'] as int) == 1,
        faceDescriptor: _parseFaceDescriptor(map['face_descriptor']),
      );

  /// For offline SQLite storage
  Map<String, dynamic> toMap() => {
        if (id != null) 'id': id,
        'student_index': studentIndex,
        if (sessionId != null) 'session_id': sessionId,
        'endpoint': endpoint,
        'course_id': courseId,
        'week_id': weekId,
        'lat': lat,
        'lng': lng,
        if (accuracyMeters != null) 'accuracy': accuracyMeters,
        'qr_code': qrCode,
        'timestamp': timestamp,
        'synced': synced ? 1 : 0,
        if (faceDescriptor != null)
          'face_descriptor': faceDescriptor!.map((e) => e).toList(),
      };

  static List<double>? _parseFaceDescriptor(dynamic v) {
    if (v == null) return null;
    if (v is List) {
      return List<double>.from(v.map((e) => (e as num).toDouble()));
    }
    if (v is String) {
      try {
        final list = jsonDecode(v) as List;
        return List<double>.from(list.map((e) => (e as num).toDouble()));
      } catch (_) {}
    }
    return null;
  }

  /// Payload for Laravel POST /api/attendance
  Map<String, dynamic> toApiPayload({
    String? deviceIp,
    String? deviceId,
  }) {
    final sid = sessionId;
    final face =
        ApiService.attachFaceDescriptorToAttendance ? faceDescriptor : null;
    if ((sid != null && sid > 0) || (qrCode?.isNotEmpty ?? false)) {
      return buildAttendancePostBody(
        indexNumber: studentIndex,
        sessionId: (sid != null && sid > 0) ? sid : null,
        courseId: courseId > 0 ? courseId : null,
        weekId: weekId > 0 ? weekId : null,
        lat: lat,
        lng: lng,
        includeLocation: true,
        qrCode: qrCode,
        sessionToken: null,
        faceDescriptor: face,
        deviceIp: deviceIp,
        deviceId: deviceId,
        accuracyMeters: accuracyMeters,
        timestamp: timestamp,
      );
    }
    // Legacy queued row without session_id — still omit zero placeholders.
    final m = <String, dynamic>{
      'index_number': studentIndex,
      'lat': lat,
      'lng': lng,
      'timestamp': timestamp,
      'face_descriptor': face,
      'device_ip': deviceIp,
      'device_id': deviceId,
    };
    if (courseId > 0) m['course_id'] = courseId;
    if (weekId > 0) m['week_id'] = weekId;
    if (qrCode?.isNotEmpty ?? false) m['qr_code'] = qrCode;
    return m;
  }
}
