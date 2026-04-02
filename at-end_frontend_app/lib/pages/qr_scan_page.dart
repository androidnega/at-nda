import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../models/qr_submit_result.dart';
import '../services/signed_qr_service.dart';
import '../services/success_chime.dart';

/// QR: signed Base64 envelope `{ data, sig }` (offline-safe) **or** legacy JSON `{ session_id, token }`.
class QRScanPage extends StatefulWidget {
  const QRScanPage({
    super.key,
    required this.activeSession,
    required this.onSubmitToken,
  });

  /// Current session from API — `id` compared to QR `session_id`.
  final Map<String, dynamic> activeSession;

  /// POST attendance; must complete (with timeout) — no infinite “Verifying…”.
  final Future<QrSubmitResult> Function(String token) onSubmitToken;

  @override
  State<QRScanPage> createState() => _QRScanPageState();
}

class _QRScanPageState extends State<QRScanPage> with TickerProviderStateMixin {
  final MobileScannerController _controller = MobileScannerController();
  late final AnimationController _lineController;
  late final AnimationController _blinkController;

  bool _handled = false;
  bool _validating = false;
  bool _showSuccess = false;

  static const double _boxSize = 250;

  static bool _idsMatch(dynamic qrSessionId, dynamic activeId) {
    if (qrSessionId == null || activeId == null) return false;
    if (qrSessionId is num && activeId is num) {
      return qrSessionId == activeId;
    }
    return qrSessionId.toString() == activeId.toString();
  }

  @override
  void initState() {
    super.initState();
    _lineController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
    )..repeat(reverse: true);
    _blinkController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _lineController.dispose();
    _blinkController.dispose();
    _controller.dispose();
    super.dispose();
  }

  void _showScannerSnack(String message) {
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 80),
        duration: const Duration(seconds: 4),
      ),
    );
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handled || _validating) return;
    final barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;
    final scannedData = barcodes.first.rawValue;
    if (scannedData == null || scannedData.isEmpty || !mounted) return;

    _handled = true;
    HapticFeedback.lightImpact();

    try {
      final SignedQrPayload payload;
      try {
        payload = SignedQrService.parseScan(scannedData);
      } on SignedQrException catch (e) {
        _showScannerSnack(e.message);
        if (mounted) Navigator.of(context).pop<QrSubmitResult>(null);
        return;
      }

      final sessionId = payload.sessionId;
      final token = payload.tokenForApi;

      final activeId = widget.activeSession['id'];
      if (!_idsMatch(sessionId, activeId)) {
        _showScannerSnack('Wrong session QR');
        if (mounted) Navigator.of(context).pop<QrSubmitResult>(null);
        return;
      }

      setState(() => _validating = true);
      final result = await widget.onSubmitToken(token);
      if (!mounted) return;

      setState(() => _validating = false);

      if (result.success) {
        await SuccessChime.play();
        HapticFeedback.mediumImpact();
        setState(() => _showSuccess = true);
        await Future.delayed(const Duration(seconds: 1));
        if (!mounted) return;
        Navigator.of(context).pop<QrSubmitResult>(result);
        return;
      }

      _showScannerSnack(result.message ?? 'Could not submit attendance');
      _handled = false;
    } catch (e) {
      if (mounted) {
        setState(() => _validating = false);
        _showScannerSnack('Invalid QR format');
        _handled = false;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black87,
        foregroundColor: Colors.white,
        iconTheme: const IconThemeData(color: Colors.white),
        titleTextStyle: const TextStyle(
          color: Colors.white,
          fontSize: 20,
          fontWeight: FontWeight.w600,
        ),
        title: const Text('Scan QR code'),
        actions: [
          IconButton(
            icon: const Icon(Icons.flash_on_outlined, color: Colors.white),
            onPressed: () => _controller.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            fit: BoxFit.cover,
          ),
          Center(
            child: SizedBox(
              width: _boxSize,
              height: _boxSize,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Stack(
                  clipBehavior: Clip.hardEdge,
                  children: [
                    Container(
                      width: _boxSize,
                      height: _boxSize,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.green, width: 2),
                      ),
                    ),
                    AnimatedBuilder(
                      animation: _lineController,
                      builder: (context, child) {
                        final t = _lineController.value;
                        final top = t * (_boxSize - 4);
                        return Positioned(
                          left: 0,
                          right: 0,
                          top: top,
                          child: Container(
                            height: 2,
                            color: Colors.greenAccent,
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 50,
            child: AnimatedBuilder(
              animation: _blinkController,
              builder: (context, child) {
                final o = 0.45 + _blinkController.value * 0.55;
                return Opacity(
                  opacity: o,
                  child: const Text(
                    'Scanning...',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                );
              },
            ),
          ),
          if (_validating)
            Positioned.fill(
              child: Container(
                color: Colors.black.withValues(alpha: 0.45),
                child: const Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CircularProgressIndicator(color: Colors.white),
                      SizedBox(height: 16),
                      Text(
                        'Verifying…',
                        style: TextStyle(color: Colors.white, fontSize: 15),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          if (_showSuccess)
            ColoredBox(
              color: Colors.black54,
              child: Center(
                child: Icon(
                  Icons.check_circle,
                  color: Colors.green.shade400,
                  size: 100,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
