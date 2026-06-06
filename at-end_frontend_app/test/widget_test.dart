import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:attendance_app/main.dart';

void main() {
  testWidgets('App boots without throwing', (WidgetTester tester) async {
    // Root route is LaunchGatePage, which renders a CircularProgressIndicator
    // while it bootstraps from SharedPreferences/SQLite before navigating to
    // the appropriate landing page (intro, login, or role home). The smoke
    // test only verifies the app boots and shows that initial spinner —
    // the deeper navigation is exercised by the platform integration tests.
    await tester.pumpWidget(const AttendanceApp());

    expect(find.byType(MaterialApp), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsWidgets);
  });
}
