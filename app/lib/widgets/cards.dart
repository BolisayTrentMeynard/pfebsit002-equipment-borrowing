import 'package:flutter/material.dart';

import 'status_chip.dart';

Map<String, dynamic>? asMap(dynamic v) =>
    v is Map ? Map<String, dynamic>.from(v) : null;

String text(dynamic v, [String fallback = '-']) {
  if (v == null) return fallback;
  final s = v.toString();
  return s.isEmpty ? fallback : s;
}

/// Server dates are ISO 8601 (UTC); show them in the browser's local time.
String fmtDate(dynamic v) {
  if (v is! String) return '-';
  final d = DateTime.tryParse(v);
  if (d == null) return '-';
  final l = d.toLocal();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${l.year}-${two(l.month)}-${two(l.day)} ${two(l.hour)}:${two(l.minute)}';
}

String equipmentName(dynamic equipment) {
  final e = asMap(equipment);
  if (e == null) return 'Unknown item';
  return '${text(e['name'], 'Unknown item')} (${text(e['asset_tag'])})';
}

class _CardShell extends StatelessWidget {
  const _CardShell({
    required this.title,
    required this.chip,
    required this.lines,
    this.onTap,
    this.action,
  });

  final String title;
  final Widget chip;
  final List<String> lines;
  final VoidCallback? onTap;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: Text(title, style: theme.textTheme.titleMedium)),
                const SizedBox(width: 8),
                chip,
              ],
            ),
            const SizedBox(height: 6),
            for (final line in lines)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text(line, style: theme.textTheme.bodyMedium),
              ),
            if (action != null) ...[
              const SizedBox(height: 8),
              action!,
            ],
          ],
        ),
      ),
      ),
    );
  }
}

class RequestCard extends StatelessWidget {
  const RequestCard(this.data, {super.key, this.showBorrower = false, this.onTap});

  final Map<String, dynamic> data;
  final bool showBorrower;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final borrower = asMap(data['borrower']);
    final days = text(data['requested_days']);
    return _CardShell(
      title: equipmentName(data['equipment']),
      onTap: onTap,
      chip: StatusChip(text(data['status'], '')),
      lines: [
        'Reason: ${text(data['reason'])}',
        'Requested for $days day${days == '1' ? '' : 's'}',
        if (showBorrower && borrower != null) 'Borrower: ${text(borrower['name'])}',
        if (data['due_at'] != null) 'Due: ${fmtDate(data['due_at'])}',
      ],
    );
  }
}

class EquipmentCard extends StatelessWidget {
  const EquipmentCard(this.data, {super.key});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    return _CardShell(
      title: text(data['name'], 'Unnamed item'),
      chip: StatusChip(text(data['status'], '')),
      lines: ['Asset tag: ${text(data['asset_tag'])}'],
    );
  }
}

class CustodyCard extends StatelessWidget {
  const CustodyCard(this.data, {super.key, this.onTap});

  final Map<String, dynamic> data;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final borrower = asMap(data['borrower']);
    final overdue = data['overdue'] == true;
    return _CardShell(
      title: equipmentName(data['equipment']),
      onTap: onTap,
      chip: StatusChip(overdue ? 'overdue' : 'RELEASED'),
      lines: [
        'With: ${text(borrower?['name'])}',
        'Due: ${fmtDate(data['due_at'])}',
      ],
    );
  }
}

class ManualHandlingCard extends StatelessWidget {
  const ManualHandlingCard(this.data, {super.key, this.onResolve});

  final Map<String, dynamic> data;
  final VoidCallback? onResolve;

  @override
  Widget build(BuildContext context) {
    final request = asMap(data['borrow_request']);
    final recipient = asMap(data['recipient']);
    return _CardShell(
      title: equipmentName(request?['equipment']),
      chip: StatusChip(text(data['status'], '')),
      lines: [
        'Notification: ${text(data['type'])} to ${text(recipient?['name'])} (${text(recipient?['role'])})',
        'Attempts: ${text(data['attempts'])}',
        'Last error: ${text(data['last_error'])}',
      ],
      action: (onResolve != null && data['status'] == 'FAILED_NEEDS_HUMAN')
          ? Align(
              alignment: Alignment.centerLeft,
              child: FilledButton.tonalIcon(
                onPressed: onResolve,
                icon: const Icon(Icons.task_alt),
                label: const Text('Mark as handled'),
              ),
            )
          : null,
    );
  }
}
