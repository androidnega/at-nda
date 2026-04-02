import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/student.dart';
import '../utils/constants.dart';

/// Circle avatar from [Student.profileImage]: network URL, raw base64, or [Icons.person].
class ProfileAvatar extends StatelessWidget {
  const ProfileAvatar({
    super.key,
    required this.student,
    this.radius = 24,
  });

  final Student student;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final apiUri = Uri.parse(Constants.baseUrl);
    final url = student.resolvedNetworkProfileUrl(apiUri) ??
        student.profilePictureUrl;
    if (url != null && url.isNotEmpty) {
      return CircleAvatar(
        radius: radius,
        backgroundImage: NetworkImage(url),
        onBackgroundImageError: (_, __) {},
      );
    }
    final img = student.profileImage;
    if (img.isEmpty) {
      return _iconOnly();
    }
    try {
      final bytes = base64Decode(img);
      return CircleAvatar(
        radius: radius,
        backgroundImage: MemoryImage(bytes),
      );
    } catch (_) {
      return _iconOnly();
    }
  }

  Widget _iconOnly() {
    return CircleAvatar(
      radius: radius,
      child: Icon(Icons.person, size: radius * 1.25),
    );
  }
}
