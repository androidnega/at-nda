import 'package:flutter/material.dart';

import '../models/student.dart';
import '../services/api_service.dart';
import '../services/profile_image_cache.dart';
import '../utils/constants.dart';


/// Circle avatar: cached network download, base64, or placeholder.
class ProfileAvatar extends StatefulWidget {
  const ProfileAvatar({
    super.key,
    required this.student,
    this.radius = 24,
  });

  final Student student;
  final double radius;

  @override
  State<ProfileAvatar> createState() => _ProfileAvatarState();
}

class _ProfileAvatarState extends State<ProfileAvatar> {
  ImageProvider? _provider;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(covariant ProfileAvatar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.student.indexNumber != widget.student.indexNumber ||
        oldWidget.student.profileImage != widget.student.profileImage ||
        oldWidget.student.serverId != widget.student.serverId) {
      _load();
    }
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final p = await ProfileImageCache.instance.resolve(widget.student);
    if (!mounted) return;
    setState(() {
      _provider = p;
      _loading = false;
    });
  }

  String? _networkFallbackUrl() {
    final urls = widget.student
        .profileImageNetworkUrlCandidates(Uri.parse(Constants.baseUrl));
    return urls.isNotEmpty ? urls.first : null;
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.radius;
    final d = r * 2;
    if (_loading) {
      return CircleAvatar(
        radius: r,
        child: Padding(
          padding: EdgeInsets.all(r * 0.35),
          child: const CircularProgressIndicator(strokeWidth: 2),
        ),
      );
    }
    if (_provider != null) {
      return CircleAvatar(
        radius: r,
        backgroundImage: _provider,
        onBackgroundImageError: (_, __) {
          if (mounted) setState(() => _provider = null);
        },
      );
    }
    final fallbackUrl = _networkFallbackUrl();
    if (fallbackUrl != null) {
      return ClipOval(
        child: Image.network(
          fallbackUrl,
          headers: ApiService.profileImageGetHeaders(),
          width: d,
          height: d,
          fit: BoxFit.cover,
          gaplessPlayback: true,
          errorBuilder: (_, __, ___) => CircleAvatar(
            radius: r,
            child: Icon(Icons.person, size: r * 1.25),
          ),
          loadingBuilder: (context, child, progress) {
            if (progress == null) return child;
            return SizedBox(
              width: d,
              height: d,
              child: Center(
                child: SizedBox(
                  width: r,
                  height: r,
                  child: const CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            );
          },
        ),
      );
    }
    return CircleAvatar(
      radius: r,
      child: Icon(Icons.person, size: r * 1.25),
    );
  }
}
