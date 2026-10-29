import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../widgets/cards.dart';
import '../widgets/dialogs.dart';
import '../widgets/state_view.dart';
import '../widgets/status_chip.dart';

/// One request with its history, plus the actions allowed for the signed-in role
/// in the current status. The server still checks both the role and the status.
class RequestDetailScreen extends StatefulWidget {
  const RequestDetailScreen({
    super.key,
    required this.api,
    required this.role,
    required this.requestId,
    required this.onChanged,
  });

  final ApiClient api;
  final String role;
  final int requestId;
  final VoidCallback onChanged;

  @override
  State<RequestDetailScreen> createState() => _RequestDetailScreenState();
}

class _RequestDetailScreenState extends State<RequestDetailScreen> {
  late Future<Map<String, dynamic>> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final json = await widget.api.get('/requests/${widget.requestId}');
    final data = json is Map ? asMap(json['data']) : null;
    if (data == null) {
      throw ApiException(ApiErrorKind.server, 'The server gave an unexpected answer.');
    }
    return data;
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _act(String path, {Map<String, dynamic>? body, required String done}) async {
    setState(() => _busy = true);
    try {
      await widget.api.post(path, body: body);
      if (!mounted) return;
      showInfo(context, done);
      widget.onChanged();
      setState(() {
        _busy = false;
        _future = _load();
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      showError(context, e);
    }
  }

  Future<void> _decide(bool approve) async {
    final note = await askNote(
      context,
      title: approve ? 'Approve request' : 'Reject request',
      message: 'Add a note for the borrower (optional).',
      required: false,
      confirmLabel: approve ? 'Approve' : 'Reject',
    );
    if (note == null || !mounted) return;
    await _act(
      '/requests/${widget.requestId}/${approve ? 'approve' : 'reject'}',
      body: note.isEmpty ? null : {'note': note},
      done: approve ? 'Request approved.' : 'Request rejected.',
    );
  }

  Future<void> _release() async {
    final ok = await confirmDialog(
      context,
      title: 'Release item',
      message: 'Hand this item to the borrower? The server sets the due date.',
      confirmLabel: 'Release',
    );
    if (!ok || !mounted) return;
    await _act('/requests/${widget.requestId}/release', done: 'Item released.');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Request details')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return Center(
              child: Semantics(label: 'Loading', child: CircularProgressIndicator()),
            );
          }
          final error = snapshot.error;
          if (error != null) return _errorView(error);
          return _content(context, snapshot.data!);
        },
      ),
    );
  }

  Widget _errorView(Object error) {
    if (error is ApiException) {
      switch (error.kind) {
        case ApiErrorKind.forbidden:
          return const StateView(
            icon: Icons.block,
            title: 'Not allowed',
            message: 'You can only open your own requests.',
          );
        case ApiErrorKind.notFound:
          return const StateView(
            icon: Icons.search_off,
            title: 'Not found',
            message: 'This request does not exist any more.',
          );
        case ApiErrorKind.unauthorized:
          return const StateView(
            icon: Icons.lock_outline,
            title: 'Please sign in again',
            message: 'Your session is no longer valid.',
          );
        default:
          return StateView(
            icon: Icons.error_outline,
            title: 'Could not load the request',
            message: error.message,
            actionLabel: 'Try again',
            onAction: _reload,
          );
      }
    }
    return StateView(
      icon: Icons.error_outline,
      title: 'Something went wrong',
      message: 'Please try again in a moment.',
      actionLabel: 'Try again',
      onAction: _reload,
    );
  }

  static String _inspectionLabel(String value) {
    switch (value) {
      case 'good':
        return 'Good condition';
      case 'damaged':
        return 'Damaged';
      case 'missing_parts':
        return 'Missing parts';
      default:
        return value;
    }
  }

  Widget _row(BuildContext context, String label, String value) {
    final style = Theme.of(context).textTheme.bodyMedium;
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Text.rich(
        TextSpan(
          style: style,
          children: [
            TextSpan(text: '$label: ', style: const TextStyle(fontWeight: FontWeight.w600)),
            TextSpan(text: value),
          ],
        ),
      ),
    );
  }

  Widget _content(BuildContext context, Map<String, dynamic> r) {
    final theme = Theme.of(context);
    final status = text(r['status'], '');
    final borrower = asMap(r['borrower']);
    final history = (r['audit_logs'] is List)
        ? (r['audit_logs'] as List).whereType<Map>().toList()
        : <Map>[];

    final isApprover = widget.role == 'approver';
    final isStaff = widget.role == 'staff';

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 720),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(equipmentName(r['equipment']), style: theme.textTheme.titleLarge),
                ),
                const SizedBox(width: 8),
                StatusChip(status),
              ],
            ),
            const SizedBox(height: 8),
            _row(context, 'Reason', text(r['reason'])),
            _row(context, 'Days requested', text(r['requested_days'])),
            if (borrower != null) _row(context, 'Borrower', text(borrower['name'])),
            if (r['due_at'] != null) _row(context, 'Due', fmtDate(r['due_at'])),
            if (r['decision_note'] != null)
              _row(context, 'Decision note', text(r['decision_note'])),
            if (r['inspection_result'] != null)
              _row(context, 'Inspection', _inspectionLabel(text(r['inspection_result']))),
            if (r['inspection_notes'] != null)
              _row(context, 'Inspection notes', text(r['inspection_notes'])),
            const SizedBox(height: 20),
            if (isApprover && status == 'PENDING')
              Wrap(
                spacing: 12,
                runSpacing: 8,
                children: [
                  FilledButton.icon(
                    onPressed: _busy ? null : () => _decide(true),
                    icon: const Icon(Icons.check),
                    label: const Text('Approve'),
                  ),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _decide(false),
                    icon: const Icon(Icons.close),
                    label: const Text('Reject'),
                  ),
                ],
              ),
            if (isStaff && status == 'APPROVED')
              Align(
                alignment: Alignment.centerLeft,
                child: FilledButton.icon(
                  onPressed: _busy ? null : _release,
                  icon: const Icon(Icons.outbox),
                  label: const Text('Release item'),
                ),
              ),
            if (isStaff && status == 'RELEASED')
              _ReturnForm(
                api: widget.api,
                requestId: widget.requestId,
                onDone: () {
                  showInfo(context, 'Return recorded and request closed.');
                  widget.onChanged();
                  _reload();
                },
              ),
            const SizedBox(height: 24),
            Text('History', style: theme.textTheme.titleMedium),
            const SizedBox(height: 6),
            if (history.isEmpty)
              const Text('No history yet.')
            else
              for (final h in history)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    '${fmtDate(h['created_at'])}  ${text(h['action'])}: '
                    '${text(h['from_status'], 'start')} to ${text(h['to_status'])}',
                  ),
                ),
          ],
        ),
      ),
    );
  }
}

/// Staff form: record the return and the inspection result. This also closes the request.
class _ReturnForm extends StatefulWidget {
  const _ReturnForm({required this.api, required this.requestId, required this.onDone});

  final ApiClient api;
  final int requestId;
  final VoidCallback onDone;

  @override
  State<_ReturnForm> createState() => _ReturnFormState();
}

class _ReturnFormState extends State<_ReturnForm> {
  final _formKey = GlobalKey<FormState>();
  final _notes = TextEditingController();

  String? _result;
  bool _busy = false;
  String? _error;
  Map<String, String> _serverErrors = {};

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _error = null;
      _serverErrors = {};
    });
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _busy = true);
    final notes = _notes.text.trim();
    try {
      await widget.api.post('/requests/${widget.requestId}/return', body: {
        'inspection_result': _result,
        if (notes.isNotEmpty) 'inspection_notes': notes,
      });
      if (!mounted) return;
      setState(() => _busy = false);
      widget.onDone();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        if (e.kind == ApiErrorKind.validation && e.fieldErrors.isNotEmpty) {
          _serverErrors = e.fieldErrors;
        } else {
          _error = e.message;
        }
      });
      if (_serverErrors.isNotEmpty) _formKey.currentState?.validate();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = 'Something unexpected happened. Please try again.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Return and inspection', style: theme.textTheme.titleMedium),
          const SizedBox(height: 12),
          if (_error != null)
            Semantics(
              liveRegion: true,
              child: Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: theme.colorScheme.errorContainer,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  _error!,
                  style: TextStyle(color: theme.colorScheme.onErrorContainer),
                ),
              ),
            ),
          DropdownButtonFormField<String>(
            initialValue: _result,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Inspection result',
              border: OutlineInputBorder(),
            ),
            items: const [
              DropdownMenuItem(value: 'good', child: Text('Good condition')),
              DropdownMenuItem(value: 'damaged', child: Text('Damaged')),
              DropdownMenuItem(value: 'missing_parts', child: Text('Missing parts')),
            ],
            onChanged: _busy ? null : (v) => setState(() => _result = v),
            validator: (v) =>
                v == null ? 'Choose the inspection result.' : _serverErrors['inspection_result'],
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _notes,
            enabled: !_busy,
            maxLength: 255,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Inspection notes (optional)',
              border: OutlineInputBorder(),
            ),
            validator: (v) {
              if ((v ?? '').trim().length > 255) return 'Keep the notes under 255 characters.';
              return _serverErrors['inspection_notes'];
            },
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Record return and close'),
            ),
          ),
        ],
      ),
    );
  }
}
