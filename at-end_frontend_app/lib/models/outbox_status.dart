/// Lifecycle of a row in `attendance_outbox`.
///
/// One-way except `Failed → Pending` (after the back-off window elapses).
/// `Quarantined` and `Rejected` are terminal for the automated retry loop;
/// the user can still manually retry a Failed row from Sync Status.
enum OutboxStatus {
  pending,
  syncing,
  synced,
  failed,
  rejected,
  quarantined;

  String get wireValue {
    switch (this) {
      case OutboxStatus.pending:
        return 'pending';
      case OutboxStatus.syncing:
        return 'syncing';
      case OutboxStatus.synced:
        return 'synced';
      case OutboxStatus.failed:
        return 'failed';
      case OutboxStatus.rejected:
        return 'rejected';
      case OutboxStatus.quarantined:
        return 'quarantined';
    }
  }

  static OutboxStatus fromWire(String? raw) {
    switch (raw?.trim().toLowerCase()) {
      case 'pending':
        return OutboxStatus.pending;
      case 'syncing':
        return OutboxStatus.syncing;
      case 'synced':
        return OutboxStatus.synced;
      case 'failed':
        return OutboxStatus.failed;
      case 'rejected':
        return OutboxStatus.rejected;
      case 'quarantined':
        return OutboxStatus.quarantined;
      default:
        return OutboxStatus.pending;
    }
  }

  /// True for any state the automated retry loop should attempt again.
  bool get isRetryable {
    switch (this) {
      case OutboxStatus.pending:
      case OutboxStatus.failed:
      case OutboxStatus.syncing:
        return true;
      case OutboxStatus.synced:
      case OutboxStatus.rejected:
      case OutboxStatus.quarantined:
        return false;
    }
  }

  /// Terminal states never make further network attempts on their own.
  bool get isTerminal {
    switch (this) {
      case OutboxStatus.synced:
      case OutboxStatus.rejected:
      case OutboxStatus.quarantined:
        return true;
      case OutboxStatus.pending:
      case OutboxStatus.syncing:
      case OutboxStatus.failed:
        return false;
    }
  }
}
