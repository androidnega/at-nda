import 'package:flutter_test/flutter_test.dart';

import 'package:attendance_app/main.dart';

void main() {
  testWidgets('Login page smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const AttendanceApp());
    expect(find.text('Attendance'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
