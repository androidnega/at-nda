/// Class rep assignment from login payload (`rep_roles`).
class RepRoleEntry {
  final int classId;
  /// Laravel: `rep` (main) or `assist`.
  final String role;

  const RepRoleEntry({required this.classId, required this.role});

  bool get isMainRep => role == 'rep';

  Map<String, dynamic> toJson() => {'class_id': classId, 'role': role};

  factory RepRoleEntry.fromJson(dynamic j) {
    if (j is! Map) {
      return const RepRoleEntry(classId: 0, role: 'assist');
    }
    final m = Map<String, dynamic>.from(j);
    return RepRoleEntry(
      classId: int.tryParse('${m['class_id']}') ?? 0,
      role: m['role']?.toString() ?? 'assist',
    );
  }
}

/// Student model matching Laravel API + local SQLite.
/// Handles API returning int (e.g. index_number as 200) instead of String.
class Student {
  /// Laravel primary key (for `/media/students/{id}/profile-image`).
  final int? serverId;
  final String indexNumber;
  final String name;
  /// When API sends separate fields (Laravel).
  final String? firstName;
  final String? lastName;
  final String profileImage;
  final List<double>? faceDescriptor;
  final String? boundIp;
  final String? phoneNumber;
  final String? email;
  final String? className;
  final String? faculty;
  final String? department;
  final String? level;
  /// Academic semester label from the class (e.g. "2024 · Semester 1").
  final String? semester;
  /// From login: class rep (any role) for mobile rep tools.
  final bool isClassRep;
  final List<RepRoleEntry> repRoles;
  final String role;
  /// Set when logging in as teaching staff via lecturer email/username.
  final int? lecturerId;

  /// Laravel `primary_role`: `student`, `class_rep`, or `lecturer`.
  String get primaryRole => role;

  Student({
    this.serverId,
    required this.indexNumber,
    required this.name,
    this.firstName,
    this.lastName,
    required this.profileImage,
    this.faceDescriptor,
    this.boundIp,
    this.phoneNumber,
    this.email,
    this.className,
    this.faculty,
    this.department,
    this.level,
    this.semester,
    this.isClassRep = false,
    this.repRoles = const [],
    this.role = 'student',
    this.lecturerId,
  });

  static String _str(dynamic v) => v?.toString() ?? '';
  static String? _strOrNull(dynamic v) =>
      v == null ? null : (v is String ? v : v.toString());

  static String _parseIndexNumber(Map<String, dynamic> json) {
    final raw = _strOrNull(json['index_number'])?.trim();
    if (raw != null && raw.isNotEmpty) return raw.toUpperCase();
    final fallback = _strOrNull(json['student_id'])?.trim();
    if (fallback != null && fallback.isNotEmpty) {
      // DB primary keys are numeric — never show them as the student's index.
      if (RegExp(r'[A-Za-z]').hasMatch(fallback)) {
        return fallback.toUpperCase();
      }
    }
    return raw ?? fallback ?? '';
  }

  static String? _sanitizeLevel(String? raw) {
    final l = raw?.trim() ?? '';
    if (l.isEmpty) return null;
    if (RegExp(r'^(100|200|300|400)$').hasMatch(l)) return l;
    return null;
  }

  /// HTTP(S) avatar URL when the API sends a full URL; null for paths / base64.
  String? get profilePictureUrl {
    final p = profileImage.trim();
    if (p.isEmpty) return null;
    if (p.startsWith('http://') || p.startsWith('https://')) return p;
    return null;
  }

  /// Builds a loadable network URL for [NetworkImage].
  ///
  /// Prefer absolute URLs from the API; then `{apiBase}/students/{id}/profile-image`;
  /// then `/storage/...` on the API origin for relative paths.
  String? resolvedNetworkProfileUrl(Uri apiBaseUri) {
    final origin = apiBaseUri.origin;
    final p = profileImage.trim();
    if (p.isEmpty && serverId == null) return null;

    String apiProfileImageUrl() {
      final base = apiBaseUri.toString().replaceAll(RegExp(r'/+$'), '');
      return '$base/students/$serverId/profile-image';
    }

    if (p.startsWith('http://') || p.startsWith('https://')) {
      try {
        final u = Uri.parse(p);
        if (apiBaseUri.host.isNotEmpty &&
            u.host == apiBaseUri.host &&
            apiBaseUri.scheme == 'https' &&
            u.scheme == 'http') {
          return p.replaceFirst(RegExp(r'^http://'), 'https://');
        }
        return p;
      } catch (_) {
        return profilePictureUrl;
      }
    }

    // Only use API image route when we know a path/URL exists; otherwise it 404s
    // (empty profile_image is common for roster rows with only serverId).
    if (serverId != null && p.isNotEmpty) {
      return apiProfileImageUrl();
    }

    if (p.isEmpty) return null;

    if (p.startsWith('https://')) return p;
    if (p.startsWith('http://')) {
      if (apiBaseUri.scheme == 'https' && apiBaseUri.host.isNotEmpty) {
        try {
          final u = Uri.parse(p);
          if (u.host == apiBaseUri.host) {
            return p.replaceFirst(RegExp(r'^http://'), 'https://');
          }
        } catch (_) {}
      }
      return p;
    }
    if (p.startsWith('//')) {
      return '${apiBaseUri.scheme}:$p';
    }
    if (p.startsWith('/')) {
      return '$origin$p';
    }
    // Laravel `public` disk path (e.g. `students/1_abc.jpg`) without leading slash.
    if (p.isNotEmpty && !p.contains('://')) {
      return '$origin/storage/$p';
    }
    return profilePictureUrl;
  }

  /// Rewrites absolute avatar URLs to the API host (fixes APP_URL ≠ device `API_BASE_URL`, e.g. LAN vs production).
  static String alignProfileImageUrlToApiHost(String rawUrl, Uri apiBaseUri) {
    final trimmed = rawUrl.trim();
    final u = Uri.tryParse(trimmed);
    if (u == null || !u.hasScheme || u.host.isEmpty) return trimmed;
    if (apiBaseUri.host.isEmpty) return trimmed;
    if (u.host == apiBaseUri.host && u.scheme == apiBaseUri.scheme) {
      return trimmed;
    }
    return u
        .replace(
          scheme: apiBaseUri.scheme,
          host: apiBaseUri.host,
          port: apiBaseUri.hasPort ? apiBaseUri.port : null,
        )
        .toString();
  }

  /// HTTP(S) URLs to try for avatar bytes (API route first, then resolved / CDN URL).
  List<String> profileImageNetworkUrlCandidates(Uri apiBaseUri) {
    final seen = <String>{};
    final out = <String>[];

    void push(String? raw) {
      var s = raw?.trim() ?? '';
      if (s.isEmpty) return;
      if (s.startsWith('http://') || s.startsWith('https://')) {
        s = alignProfileImageUrlToApiHost(s, apiBaseUri);
      }
      if (!s.startsWith('http://') && !s.startsWith('https://')) return;
      if (seen.add(s)) out.add(s);
    }

    final base = apiBaseUri.toString().replaceAll(RegExp(r'/+$'), '');
    if (serverId != null && profileImage.trim().isNotEmpty) {
      push('$base/students/$serverId/profile-image');
    }
    push(resolvedNetworkProfileUrl(apiBaseUri));
    push(profilePictureUrl);
    return out;
  }

  factory Student.fromJson(Map<String, dynamic> json) {
    final first = _strOrNull(json['first_name']);
    final last = _strOrNull(json['last_name']);
    var name = _str(json['name']);
    if (name.isEmpty) {
      name = '${first ?? ''} ${last ?? ''}'.trim();
    }
    if (name.isEmpty) name = 'Student';
    final pic = _strOrNull(json['profile_picture'])?.trim();
    final img = _str(json['profile_image']);
    final profile = (pic != null && pic.isNotEmpty) ? pic : img;
    final roles = _parseRepRoles(json['rep_roles']);
    final isRep = _parseTruthy(json['is_class_rep']) || roles.isNotEmpty;
    final role = _resolvedRole(json['primary_role'], isRep);
    final sid = json['id'];
    final serverId = sid is int
        ? sid
        : sid is num
            ? sid.toInt()
            : int.tryParse(sid?.toString() ?? '');
    final lid = json['lecturer_id'];
    final lecturerId = lid is int
        ? lid
        : lid is num
            ? lid.toInt()
            : int.tryParse(lid?.toString() ?? '');
    return Student(
        serverId: serverId,
        lecturerId: lecturerId,
        indexNumber: _parseIndexNumber(json),
        name: name,
        firstName: first,
        lastName: last,
        profileImage: profile,
        faceDescriptor: _parseFaceDescriptor(json['face_descriptor']),
        boundIp: _strOrNull(json['bound_ip']),
        phoneNumber: _strOrNull(json['phone_number']),
        email: _strOrNull(json['email']),
        className: _strOrNull(json['class_name']) ?? _strOrNull(json['class']),
        faculty: _strOrNull(json['faculty']),
        department: _strOrNull(json['department']),
        level: _sanitizeLevel(_strOrNull(json['level'])),
        semester: _strOrNull(json['semester']),
        isClassRep: isRep,
        repRoles: roles,
        role: role,
      );
  }

  static String _resolvedRole(dynamic raw, bool isRep) {
    final role = _str(raw).trim().toLowerCase();
    if (role == 'lecturer') return 'lecturer';
    if (role == 'class_rep') return 'class_rep';
    if (role == 'student') return 'student';
    return isRep ? 'class_rep' : 'student';
  }

  static List<RepRoleEntry> _parseRepRoles(dynamic v) {
    if (v is! List) return const [];
    return v.map(RepRoleEntry.fromJson).toList();
  }

  /// API may send bool, int, or string (JSON / proxies).
  static bool _parseTruthy(dynamic v) {
    if (v == true || v == 1) return true;
    if (v is String) {
      final s = v.toLowerCase().trim();
      return s == 'true' || s == '1' || s == 'yes';
    }
    return false;
  }

  /// Class group name with level, e.g. `ITS A - Level 200`.
  String? get classGroupWithLevelLabel {
    final c = className?.trim() ?? '';
    final l = level?.trim() ?? '';
    if (c.isEmpty && l.isEmpty) return null;
    if (c.isEmpty) return 'Level $l';
    if (l.isEmpty) return c;
    return '$c · Level $l';
  }

  /// Line for UI: prefers first + last when present.
  String get displayFirstLastName {
    final f = firstName?.trim() ?? '';
    final l = lastName?.trim() ?? '';
    if (f.isNotEmpty || l.isNotEmpty) return '$f $l'.trim();
    return name;
  }

  /// Home greeting / drawer title: surname when API sends it; else last token of [name].
  String get greetingLastName {
    final l = lastName?.trim() ?? '';
    if (l.isNotEmpty) return l;
    final n = name.trim();
    if (n.isNotEmpty) {
      final parts =
          n.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
      if (parts.isNotEmpty) return parts.last;
    }
    return indexNumber;
  }

  static List<double>? _parseFaceDescriptor(dynamic v) {
    if (v == null) return null;
    if (v is! List) return null;
    try {
      return List<double>.from(v.map((e) => (e is num ? e : num.tryParse(e.toString()) ?? 0).toDouble()));
    } catch (_) {
      return null;
    }
  }

  Map<String, dynamic> toJson() => {
        if (serverId != null) 'id': serverId,
        'index_number': indexNumber,
        'name': name,
        if (firstName != null) 'first_name': firstName,
        if (lastName != null) 'last_name': lastName,
        'profile_image': profileImage,
        'face_descriptor': faceDescriptor,
        'bound_ip': boundIp,
        'phone_number': phoneNumber,
        'email': email,
        'class_name': className,
        'faculty': faculty,
        'department': department,
        'level': level,
        'semester': semester,
        'is_class_rep': isClassRep,
        'rep_roles': repRoles.map((e) => e.toJson()).toList(),
        'primary_role': role,
        if (lecturerId != null) 'lecturer_id': lecturerId,
      };

  Student copyWith({
    int? serverId,
    String? indexNumber,
    String? name,
    String? firstName,
    String? lastName,
    String? profileImage,
    List<double>? faceDescriptor,
    String? boundIp,
    String? phoneNumber,
    String? email,
    String? className,
    String? faculty,
    String? department,
    String? level,
    String? semester,
    bool? isClassRep,
    List<RepRoleEntry>? repRoles,
    String? role,
    int? lecturerId,
  }) =>
      Student(
        serverId: serverId ?? this.serverId,
        lecturerId: lecturerId ?? this.lecturerId,
        indexNumber: indexNumber ?? this.indexNumber,
        name: name ?? this.name,
        firstName: firstName ?? this.firstName,
        lastName: lastName ?? this.lastName,
        profileImage: profileImage ?? this.profileImage,
        faceDescriptor: faceDescriptor ?? this.faceDescriptor,
        boundIp: boundIp ?? this.boundIp,
        phoneNumber: phoneNumber ?? this.phoneNumber,
        email: email ?? this.email,
        className: className ?? this.className,
        faculty: faculty ?? this.faculty,
        department: department ?? this.department,
        level: level ?? this.level,
        semester: semester ?? this.semester,
        isClassRep: isClassRep ?? this.isClassRep,
        repRoles: repRoles ?? this.repRoles,
        role: role ?? this.role,
      );
}
