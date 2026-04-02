/// Time-of-day greeting (updates whenever the widget rebuilds, e.g. each second on home).
String getGreeting() {
  final hour = DateTime.now().hour;
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}
