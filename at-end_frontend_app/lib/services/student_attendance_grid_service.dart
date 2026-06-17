import 'dart:convert';

import 'package:http/http.dart' as http;

import '../utils/constants.dart';
import 'api_service.dart';
import 'offline_service.dart';

/// Live attendance grid from `GET /api/student/attendance-grid` — no local cache.
abstract final class StudentAttendanceGridService {
  static Future<Map<String, dynamic>?> fetchLive() async {
    final student = await OfflineService.getCurrentStudent();
    final password = await OfflineService.getApiSessionPassword();
    if (student == null || password == null || password.isEmpty) {
      return null;
    }

    final uri = Uri.parse('${Constants.baseUrl}/student/attendance-grid')
        .replace(queryParameters: {
      'index_number': student.indexNumber,
      'password': password,
    });

    final res = await http
        .get(uri, headers: ApiService.requestHeaders())
        .timeout(ApiService.httpTimeout);

    if (res.statusCode < 200 || res.statusCode >= 300) return null;

    final body = jsonDecode(res.body);
    if (body is! Map || body['success'] != true || body['data'] is! Map) {
      return null;
    }
    return Map<String, dynamic>.from(body['data'] as Map);
  }
}
