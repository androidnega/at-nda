import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:path/path.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sqflite/sqflite.dart';

import '../models/attendance_record.dart';
import '../models/student.dart';

/// Local storage: SQLite on mobile, SharedPreferences on web (sqflite doesn't support web).
class OfflineService {
  static Database? _db;
  static const String _studentKey = 'current_student';
  static const String _apiPasswordKey = 'laravel_api_session_password';
  static const FlutterSecureStorage _secureStorage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static Future<Database?> get _database async {
    if (kIsWeb) return null;
    _db ??= await _initDb();
    return _db;
  }

  static Future<Database> _initDb() async {
    final path = join(await getDatabasesPath(), 'attendance_offline.db');
    return openDatabase(
      path,
      version: 9,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE students(
            id INTEGER PRIMARY KEY CHECK (id = 1),
            server_id INTEGER,
            index_number TEXT NOT NULL,
            name TEXT NOT NULL,
            first_name TEXT,
            last_name TEXT,
            profile_image TEXT,
            face_descriptor TEXT,
            phone_number TEXT,
            bound_ip TEXT,
            email TEXT,
            class_name TEXT,
            faculty TEXT,
            department TEXT,
            level TEXT,
            password_hash TEXT,
            is_class_rep INTEGER DEFAULT 0,
            rep_roles TEXT
          )
        ''');
        await db.execute('''
          CREATE TABLE attendance(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_index TEXT NOT NULL,
            session_id INTEGER,
            course_id INTEGER NOT NULL,
            week_id INTEGER NOT NULL,
            lat REAL NOT NULL,
            lng REAL NOT NULL,
            qr_code TEXT,
            face_descriptor TEXT,
            device_ip TEXT,
            timestamp TEXT NOT NULL,
            synced INTEGER DEFAULT 0
          )
        ''');
        await db.execute('''
          CREATE TABLE attendance_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            index_number TEXT NOT NULL,
            course_code TEXT,
            session_id INTEGER,
            marked_at TEXT NOT NULL,
            synced INTEGER DEFAULT 1
          )
        ''');
        await db.execute('''
          CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            email TEXT,
            student_id TEXT,
            profile_picture TEXT,
            updated_at TEXT
          )
        ''');
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        if (oldVersion < 2) {
          await db.execute('''
            CREATE TABLE IF NOT EXISTS students(
              id INTEGER PRIMARY KEY CHECK (id = 1),
              index_number TEXT NOT NULL,
              name TEXT NOT NULL,
              profile_image TEXT,
              face_descriptor TEXT,
              phone_number TEXT,
              bound_ip TEXT
            )
          ''');
        }
        if (oldVersion < 3) {
          await db.execute('ALTER TABLE students ADD COLUMN email TEXT');
          await db.execute('ALTER TABLE students ADD COLUMN class_name TEXT');
          await db.execute('ALTER TABLE students ADD COLUMN faculty TEXT');
          await db.execute('ALTER TABLE students ADD COLUMN department TEXT');
          await db.execute('ALTER TABLE students ADD COLUMN level TEXT');
          await db.execute('ALTER TABLE students ADD COLUMN password_hash TEXT');
          try {
            await db.execute('ALTER TABLE attendance ADD COLUMN face_descriptor TEXT');
          } catch (_) {}
          try {
            await db.execute('ALTER TABLE attendance ADD COLUMN device_ip TEXT');
          } catch (_) {}
        }
        if (oldVersion < 4) {
          try {
            await db.execute('ALTER TABLE students ADD COLUMN first_name TEXT');
          } catch (_) {}
          try {
            await db.execute('ALTER TABLE students ADD COLUMN last_name TEXT');
          } catch (_) {}
        }
        if (oldVersion < 5) {
          await db.execute('''
            CREATE TABLE IF NOT EXISTS attendance_log (
              id INTEGER PRIMARY KEY AUTOINCREMENT,
              index_number TEXT NOT NULL,
              course_code TEXT,
              session_id INTEGER,
              marked_at TEXT NOT NULL,
              synced INTEGER DEFAULT 1
            )
          ''');
        }
        if (oldVersion < 6) {
          try {
            await db.execute(
              'ALTER TABLE attendance ADD COLUMN session_id INTEGER',
            );
          } catch (_) {}
        }
        if (oldVersion < 7) {
          await db.execute('''
            CREATE TABLE IF NOT EXISTS users (
              id INTEGER PRIMARY KEY,
              name TEXT,
              email TEXT,
              student_id TEXT,
              profile_picture TEXT,
              updated_at TEXT
            )
          ''');
        }
        if (oldVersion < 8) {
          try {
            await db.execute(
              'ALTER TABLE students ADD COLUMN is_class_rep INTEGER DEFAULT 0',
            );
          } catch (_) {}
          try {
            await db.execute('ALTER TABLE students ADD COLUMN rep_roles TEXT');
          } catch (_) {}
        }
        if (oldVersion < 9) {
          try {
            await db.execute('ALTER TABLE students ADD COLUMN server_id INTEGER');
          } catch (_) {}
        }
      },
    );
  }

  static Future<void> _upsertUsersRow(Database database, Student student) async {
    await database.insert(
      'users',
      {
        'id': 1,
        'name': student.name,
        'email': student.email,
        'student_id': student.indexNumber,
        'profile_picture':
            student.profileImage.trim().isEmpty ? null : student.profileImage,
        'updated_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  static Student? _studentFromUsersRow(Map<String, Object?> m) {
    final sid = m['student_id'] as String?;
    if (sid == null || sid.isEmpty) return null;
    return Student(
      indexNumber: sid,
      name: (m['name'] as String?)?.trim().isNotEmpty == true
          ? (m['name'] as String).trim()
          : 'Student',
      profileImage: (m['profile_picture'] as String?) ?? '',
      email: m['email'] as String?,
    );
  }

  // --- Student (current logged-in) ---
  static Future<Student?> getCurrentStudent() async {
    if (kIsWeb) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final json = prefs.getString(_studentKey);
        if (json == null) return null;
        final map = jsonDecode(json) as Map<String, dynamic>;
        return Student.fromJson(map);
      } catch (_) {
        return null;
      }
    }
    final database = await _database;
    if (database == null) return null;
    var maps = await database.query('students', limit: 1);
    if (maps.isEmpty) {
      final um = await database.query('users', limit: 1);
      if (um.isEmpty) return null;
      return _studentFromUsersRow(um.first);
    }
    final m = maps.first;
    return Student(
      serverId: m['server_id'] as int?,
      indexNumber: m['index_number'] as String,
      name: m['name'] as String,
      firstName: m['first_name'] as String?,
      lastName: m['last_name'] as String?,
      profileImage: (m['profile_image'] as String?) ?? '',
      faceDescriptor: m['face_descriptor'] != null
          ? List<double>.from(
              (jsonDecode(m['face_descriptor'] as String) as List)
                  .map((e) => (e as num).toDouble()))
          : null,
      phoneNumber: m['phone_number'] as String?,
      boundIp: m['bound_ip'] as String?,
      email: m['email'] as String?,
      className: m['class_name'] as String?,
      faculty: m['faculty'] as String?,
      department: m['department'] as String?,
      level: m['level'] as String?,
      isClassRep: (m['is_class_rep'] as int?) == 1,
      repRoles: _repRolesFromSqlite(m['rep_roles'] as String?),
    );
  }

  static List<RepRoleEntry> _repRolesFromSqlite(String? raw) {
    if (raw == null || raw.isEmpty) return const [];
    try {
      final list = jsonDecode(raw) as List;
      return list.map(RepRoleEntry.fromJson).toList();
    } catch (_) {
      return const [];
    }
  }

  static Future<String?> getPasswordHash() async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString('${_studentKey}_password');
    }
    final database = await _database;
    if (database == null) return null;
    final maps = await database.query('students', columns: ['password_hash'], limit: 1);
    return maps.isNotEmpty ? maps.first['password_hash'] as String? : null;
  }

  static Future<void> setPasswordHash(String hash) async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('${_studentKey}_password', hash);
      return;
    }
    final database = await _database;
    if (database != null) {
      await database.update('students', {'password_hash': hash}, where: 'id = 1');
    }
  }

  /// Plain password for Laravel endpoints that require `index_number` + `password` (not stored as API hash).
  static Future<void> setApiSessionPassword(String? plain) async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      if (plain == null || plain.isEmpty) {
        await prefs.remove('${_studentKey}_api_password');
      } else {
        await prefs.setString('${_studentKey}_api_password', plain);
      }
      return;
    }
    if (plain == null || plain.isEmpty) {
      await _secureStorage.delete(key: _apiPasswordKey);
    } else {
      await _secureStorage.write(key: _apiPasswordKey, value: plain);
    }
  }

  static Future<String?> getApiSessionPassword() async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString('${_studentKey}_api_password');
    }
    return _secureStorage.read(key: _apiPasswordKey);
  }

  static Future<void> setCurrentStudent(Student student) async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_studentKey, jsonEncode(student.toJson()));
      return;
    }
    final database = await _database;
    if (database == null) return;
    await database.insert(
      'students',
      {
        'id': 1,
        'server_id': student.serverId,
        'index_number': student.indexNumber,
        'name': student.name,
        'first_name': student.firstName,
        'last_name': student.lastName,
        'profile_image': student.profileImage,
        'face_descriptor': student.faceDescriptor != null
            ? jsonEncode(student.faceDescriptor)
            : null,
        'phone_number': student.phoneNumber,
        'bound_ip': student.boundIp,
        'email': student.email,
        'class_name': student.className,
        'faculty': student.faculty,
        'department': student.department,
        'level': student.level,
        'is_class_rep': student.isClassRep ? 1 : 0,
        'rep_roles': student.repRoles.isEmpty
            ? null
            : jsonEncode(student.repRoles.map((e) => e.toJson()).toList()),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    try {
      await _upsertUsersRow(database, student);
    } catch (_) {}
  }

  static Future<void> clearCurrentStudent() async {
    await setApiSessionPassword(null);
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_studentKey);
      await prefs.remove('${_studentKey}_password');
      return;
    }
    final database = await _database;
    if (database != null) {
      await database.delete('students');
      try {
        await database.delete('users');
      } catch (_) {}
    }
  }

  // --- Attendance queue (web: no-op, no offline queue) ---
  static Future<int> insert(AttendanceRecord record, {String? deviceIp}) async {
    final database = await _database;
    if (database == null) return 0;
    final map = {
      'student_index': record.studentIndex,
      if (record.sessionId != null) 'session_id': record.sessionId,
      'course_id': record.courseId,
      'week_id': record.weekId,
      'lat': record.lat,
      'lng': record.lng,
      'qr_code': record.qrCode,
      'face_descriptor': record.faceDescriptor != null
          ? jsonEncode(record.faceDescriptor)
          : null,
      'device_ip': deviceIp,
      'timestamp': record.timestamp,
      'synced': 0,
    };
    return await database.insert('attendance', map);
  }

  static Future<List<AttendanceRecord>> getPendingRecords() async {
    final database = await _database;
    if (database == null) return [];
    final maps = await database.query(
      'attendance',
      where: 'synced = ?',
      whereArgs: [0],
    );
    return maps.map((m) => AttendanceRecord.fromMap(m)).toList();
  }

  /// Unsynced rows in the offline attendance queue (for UI banners).
  static Future<int> getPendingAttendanceCount() async {
    if (kIsWeb) return 0;
    final database = await _database;
    if (database == null) return 0;
    final r = await database.rawQuery(
      'SELECT COUNT(*) as c FROM attendance WHERE synced = 0',
    );
    if (r.isEmpty) return 0;
    final n = r.first['c'];
    if (n is int) return n;
    if (n is num) return n.toInt();
    return 0;
  }

  /// Marks per [course_code] from dashboard logs (synced marks only).
  static Future<Map<String, int>> countMarksByCourseCode(
    String indexNumber,
  ) async {
    final logs = await getAllAttendanceLogsForIndex(indexNumber);
    final map = <String, int>{};
    for (final r in logs) {
      final c = r['course_code']?.toString().trim();
      final key = (c == null || c.isEmpty) ? 'Other' : c;
      map[key] = (map[key] ?? 0) + 1;
    }
    return map;
  }

  static Future<List<AttendanceRecord>> getAllRecords() async {
    final database = await _database;
    if (database == null) return [];
    final maps = await database.query(
      'attendance',
      orderBy: 'timestamp DESC',
    );
    return maps.map((m) => AttendanceRecord.fromMap(m)).toList();
  }

  static Future<void> markSynced(int id) async {
    final database = await _database;
    if (database == null) return;
    await database.update(
      'attendance',
      {'synced': 1},
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  // --- Dashboard: today's marks (separate from sync queue `attendance` table) ---

  static const String _webLogPrefix = 'attendance_log_entries_';

  /// Save a successful mark for dashboard (API success or after queue sync).
  static Future<void> saveAttendanceLogMark({
    required String indexNumber,
    String? courseCode,
    int? sessionId,
    required String markedAt,
    int synced = 1,
  }) async {
    final row = {
      'index_number': indexNumber,
      'course_code': courseCode,
      'session_id': sessionId,
      'marked_at': markedAt,
      'synced': synced,
    };
    if (kIsWeb) {
      await _appendWebLog(indexNumber, row);
      return;
    }
    final database = await _database;
    if (database == null) return;
    await database.insert('attendance_log', row);
  }

  /// After a queued row syncs, add a dashboard entry.
  static Future<void> saveAttendanceLogFromSyncedRecord(
    AttendanceRecord record,
  ) {
    return saveAttendanceLogMark(
      indexNumber: record.studentIndex,
      courseCode: 'Course #${record.courseId}',
      sessionId: record.sessionId,
      markedAt: record.timestamp,
      synced: 1,
    );
  }

  static Future<void> _appendWebLog(
    String indexNumber,
    Map<String, dynamic> row,
  ) async {
    final prefs = await SharedPreferences.getInstance();
    final key = '$_webLogPrefix$indexNumber';
    final raw = prefs.getString(key);
    final list = <Map<String, dynamic>>[];
    if (raw != null) {
      try {
        final decoded = jsonDecode(raw) as List<dynamic>;
        for (final e in decoded) {
          if (e is Map<String, dynamic>) list.add(e);
        }
      } catch (_) {}
    }
    list.add(Map<String, dynamic>.from(row));
    await prefs.setString(key, jsonEncode(list));
  }

  /// Rows for [indexNumber] where [marked_at] is today (local).
  static Future<List<Map<String, dynamic>>> getTodayAttendanceLogs(
    String indexNumber,
  ) async {
    final today = _todayDateString();
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString('$_webLogPrefix$indexNumber');
      if (raw == null) return [];
      try {
        final decoded = jsonDecode(raw) as List<dynamic>;
        return decoded
            .whereType<Map<String, dynamic>>()
            .where((m) {
              final at = m['marked_at']?.toString() ?? '';
              return at.startsWith(today);
            })
            .toList();
      } catch (_) {
        return [];
      }
    }
    final database = await _database;
    if (database == null) return [];
    return database.query(
      'attendance_log',
      where: 'index_number = ? AND marked_at LIKE ?',
      whereArgs: [indexNumber, '$today%'],
      orderBy: 'marked_at DESC',
    );
  }

  static String _todayDateString() {
    final n = DateTime.now();
    final y = n.year.toString().padLeft(4, '0');
    final mo = n.month.toString().padLeft(2, '0');
    final d = n.day.toString().padLeft(2, '0');
    return '$y-$mo-$d';
  }

  /// Whether [indexNumber] already has a log for [sessionId] today (re-mark guard).
  static Future<bool> hasMarkedSessionToday({
    required String indexNumber,
    int? sessionId,
  }) async {
    if (sessionId == null) return false;
    final today = _todayDateString();
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString('$_webLogPrefix$indexNumber');
      if (raw == null) return false;
      try {
        final decoded = jsonDecode(raw) as List<dynamic>;
        for (final e in decoded) {
          if (e is! Map<String, dynamic>) continue;
          final at = e['marked_at']?.toString() ?? '';
          final sid = e['session_id'];
          final sidInt = sid is int ? sid : (sid is num ? sid.toInt() : int.tryParse('$sid'));
          if (!at.startsWith(today)) continue;
          if (sidInt == sessionId) return true;
        }
      } catch (_) {}
      return false;
    }
    final database = await _database;
    if (database == null) return false;
    final rows = await database.query(
      'attendance_log',
      where: 'index_number = ? AND session_id = ? AND marked_at LIKE ?',
      whereArgs: [indexNumber, sessionId, '$today%'],
      limit: 1,
    );
    return rows.isNotEmpty;
  }

  /// All dashboard marks for history (newest first).
  static Future<List<Map<String, dynamic>>> getAllAttendanceLogsForIndex(
    String indexNumber,
  ) async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString('$_webLogPrefix$indexNumber');
      if (raw == null) return [];
      try {
        final decoded = jsonDecode(raw) as List<dynamic>;
        final list = decoded.whereType<Map<String, dynamic>>().toList();
        list.sort((a, b) {
          final ta = a['marked_at']?.toString() ?? '';
          final tb = b['marked_at']?.toString() ?? '';
          return tb.compareTo(ta);
        });
        return list;
      } catch (_) {
        return [];
      }
    }
    final database = await _database;
    if (database == null) return [];
    return database.query(
      'attendance_log',
      where: 'index_number = ?',
      whereArgs: [indexNumber],
      orderBy: 'marked_at DESC',
    );
  }
}
