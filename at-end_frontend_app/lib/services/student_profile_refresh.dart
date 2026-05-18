import '../models/student.dart';
import '../utils/connectivity_util.dart';
import '../utils/login_response_parser.dart';
import 'api_service.dart';
import 'offline_service.dart';

/// Refreshes the signed-in student from `POST /api/me` (same as login payload).
/// Preserves local [Student.faceDescriptor] when the API omits it.
Future<Student?> refreshStudentProfileFromApi(Student current) async {
  if (!await hasInternetConnectivity()) return null;
  final pwd = await OfflineService.getApiSessionPassword();
  if (pwd == null || pwd.isEmpty) return null;
  try {
    final body = await ApiService.me(current.indexNumber, pwd);
    final map = studentMapFromLoginBody(body);
    if (map == null) return null;
    final fresh = Student.fromJson(map);
    final merged = fresh.copyWith(
      faceDescriptor: fresh.faceDescriptor ?? current.faceDescriptor,
      profileImage: '',
    );
    await OfflineService.setCurrentStudent(merged);
    final tokenStr = parseLoginResponseToken(body['token']);
    if (tokenStr != null && tokenStr.isNotEmpty) {
      await OfflineService.setApiSessionToken(tokenStr);
      ApiService.setSessionBearerToken(tokenStr);
    }
    return merged;
  } catch (_) {
    return null;
  }
}
