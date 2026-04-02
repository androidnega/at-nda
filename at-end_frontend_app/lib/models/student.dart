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
  /// From login: class rep (any role) for mobile rep tools.
  final bool isClassRep;
  final List<RepRoleEntry> repRoles;

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
    this.isClassRep = false,
    this.repRoles = const [],
  });

  static String _str(dynamic v) => v?.toString() ?? '';
  static String? _strOrNull(dynamic v) =>
      v == null ? null : (v is String ? v : v.toString());

  /// HTTP(S) avatar URL when the API sends a full URL; null for paths / base64.
  String? get profilePictureUrl {
    final p = profileImage.trim();
    if (p.isEmpty) return null;
    if (p.startsWith('http://') || p.startsWith('https://')) return p;
    return null;
  }

  /// Builds a loadable network URL for [NetworkImage].
  ///
  /// When [serverId] is set, prefers `GET /api/students/{id}/profile-image` so Flutter web
  /// receives CORS headers (`api/*`). Plain `/media/...` is often served by nginx/static
  /// and omits `Access-Control-Allow-Origin`.
  String? resolvedNetworkProfileUrl(Uri apiBaseUri) {
    final origin = apiBaseUri.origin;
    final p = profileImage.trim();
    if (p.isEmpty && serverId == null) return null;

    String apiProfileImageUrl() {
      final base = apiBaseUri.toString().replaceAll(RegExp(r'/+$'), '');
      return '$base/students/$serverId/profile-image';
    }

    if (serverId != null) {
      if (p.startsWith('http://') || p.startsWith('https://')) {
        try {
          final u = Uri.parse(p);
          if (u.host != apiBaseUri.host) {
            return p;
          }
        } catch (_) {
          return profilePictureUrl;
        }
      }
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
    return profilePictureUrl;
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
    final sid = json['id'];
    final serverId = sid is int
        ? sid
        : sid is num
            ? sid.toInt()
            : int.tryParse(sid?.toString() ?? '');
    return Student(
        serverId: serverId,
        indexNumber: _str(json['index_number'] ?? json['student_id']),
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
        level: _strOrNull(json['level']),
        isClassRep: isRep,
        repRoles: roles,
      );
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

  /// Line for UI: prefers first + last when present.
  String get displayFirstLastName {
    final f = firstName?.trim() ?? '';
    final l = lastName?.trim() ?? '';
    if (f.isNotEmpty || l.isNotEmpty) return '$f $l'.trim();
    return name;
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
        'is_class_rep': isClassRep,
        'rep_roles': repRoles.map((e) => e.toJson()).toList(),
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
    bool? isClassRep,
    List<RepRoleEntry>? repRoles,
  }) =>
      Student(
        serverId: serverId ?? this.serverId,
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
        isClassRep: isClassRep ?? this.isClassRep,
        repRoles: repRoles ?? this.repRoles,
      );
}
