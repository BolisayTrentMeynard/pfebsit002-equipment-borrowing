import 'package:flutter/material.dart';

/// Status text is always shown (not colour only), so it stays readable for everyone.
class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key});

  final String status;

  static String label(String status) {
    switch (status) {
      case 'FAILED_NEEDS_HUMAN':
        return 'Needs a person';
      case 'HANDLED_MANUALLY':
        return 'Handled';
      case 'SENT_AFTER_RETRY':
        return 'Sent after retry';
      case 'on_loan':
        return 'On loan';
      case 'needs_check':
        return 'Needs check';
      default:
        if (status.isEmpty) return '-';
        final lower = status.toLowerCase();
        return lower[0].toUpperCase() + lower.substring(1);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    Color background;
    Color foreground;
    switch (status) {
      case 'REJECTED':
      case 'FAILED_NEEDS_HUMAN':
      case 'overdue':
        background = scheme.errorContainer;
        foreground = scheme.onErrorContainer;
        break;
      case 'APPROVED':
      case 'RELEASED':
      case 'available':
      case 'SENT':
      case 'SENT_AFTER_RETRY':
        background = scheme.primaryContainer;
        foreground = scheme.onPrimaryContainer;
        break;
      default:
        background = scheme.surfaceContainerHighest;
        foreground = scheme.onSurfaceVariant;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        status == 'overdue' ? 'Overdue' : label(status),
        style: Theme.of(context).textTheme.labelMedium?.copyWith(color: foreground),
      ),
    );
  }
}
