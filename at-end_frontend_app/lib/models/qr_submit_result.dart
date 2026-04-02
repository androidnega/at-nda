/// Result of POST /api/attendance after a QR scan (parsed body + HTTP status).
class QrSubmitResult {
  const QrSubmitResult({
    required this.success,
    this.httpStatus,
    this.message,
  });

  final bool success;
  final int? httpStatus;
  final String? message;

  static QrSubmitResult ok(int status) =>
      QrSubmitResult(success: true, httpStatus: status);

  static QrSubmitResult fail(int? status, String message) =>
      QrSubmitResult(success: false, httpStatus: status, message: message);
}
