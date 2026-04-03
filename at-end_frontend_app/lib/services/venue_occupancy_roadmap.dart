/// Future feature: show **venue occupancy** while a session is active (room in use for
/// the scheduled window). Needs timetable + session location from the API and a map or
/// list UI in the app. Implement when backend exposes `venue_id` / room + session times.

abstract final class VenueOccupancyRoadmap {
  static const String summary =
      'Planned: live room status during scheduled class times.';
}
