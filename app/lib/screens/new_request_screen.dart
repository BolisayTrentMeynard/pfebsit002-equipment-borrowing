import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api/api_client.dart';
import '../widgets/cards.dart';
import '../widgets/dialogs.dart';
import '../widgets/state_view.dart';

/// Borrower form. The same rules are checked here and on the server;
/// the server is the one that decides, and its messages are shown under the fields.
class NewRequestScreen extends StatefulWidget {
  const NewRequestScreen({super.key, required this.api, required this.onCreated});

  final ApiClient api;
  final VoidCallback onCreated;

  @override
  State<NewRequestScreen> createState() => _NewRequestScreenState();
}

class _NewRequestScreenState extends State<NewRequestScreen> {
  static const int maxDays = 7; // same limit as the server

  final _formKey = GlobalKey<FormState>();
  final _reason = TextEditingController();
  final _days = TextEditingController(text: '1');

  late Future<List<Map<String, dynamic>>> _equipment;
  int? _equipmentId;
  bool _busy = false;
  String? _error;
  Map<String, String> _serverErrors = {};

  @override
  void initState() {
    super.initState();
    _equipment = _loadEquipment();
  }

  @override
  void dispose() {
    _reason.dispose();
    _days.dispose();
    super.dispose();
  }

  Future<List<Map<String, dynamic>>> _loadEquipment() async {
    final json = await widget.api.get('/equipment');
    final data = json is Map ? json['data'] : null;
    if (data is! List) {
      throw ApiException(ApiErrorKind.server, 'The server gave an unexpected answer.');
    }
    return data
        .whereType<Map>()
        .map((m) => Map<String, dynamic>.from(m))
        .where((e) => e['status'] == 'available')
        .toList();
  }

  void _retry() {
    setState(() {
      _equipment = _loadEquipment();
    });
  }

  String? _validateEquipment(int? value) {
    if (value == null) return 'Choose the equipment.';
    return _serverErrors['equipment_id'];
  }

  String? _validateReason(String? value) {
    final v = (value ?? '').trim();
    if (v.length < 3) return 'Give a short reason (at least 3 characters).';
    if (v.length > 255) return 'Keep the reason under 255 characters.';
    return _serverErrors['reason'];
  }

  String? _validateDays(String? value) {
    final n = int.tryParse((value ?? '').trim());
    if (n == null || n < 1 || n > maxDays) return 'Enter 1 to $maxDays days.';
    return _serverErrors['requested_days'];
  }

  Future<void> _submit() async {
    setState(() {
      _error = null;
      _serverErrors = {};
    });
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _busy = true);
    try {
      await widget.api.post('/requests', body: {
        'equipment_id': _equipmentId,
        'reason': _reason.text.trim(),
        'requested_days': int.parse(_days.text.trim()),
      });
      if (!mounted) return;
      widget.onCreated();
      showInfo(context, 'Request submitted. It is waiting for approval.');
      Navigator.pop(context);
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
    return Scaffold(
      appBar: AppBar(title: const Text('New request')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _equipment,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return Center(
              child: Semantics(label: 'Loading', child: CircularProgressIndicator()),
            );
          }
          final error = snapshot.error;
          if (error != null) {
            final message = error is ApiException ? error.message : 'Please try again.';
            return StateView(
              icon: Icons.cloud_off_outlined,
              title: 'Could not load the equipment',
              message: message,
              actionLabel: 'Try again',
              onAction: _retry,
            );
          }
          final items = snapshot.data ?? const <Map<String, dynamic>>[];
          if (items.isEmpty) {
            return StateView(
              icon: Icons.inbox_outlined,
              title: 'Nothing available',
              message: 'No equipment is available to borrow right now.',
              actionLabel: 'Refresh',
              onAction: _retry,
            );
          }
          return _form(context, items);
        },
      ),
    );
  }

  Widget _form(BuildContext context, List<Map<String, dynamic>> items) {
    final scheme = Theme.of(context).colorScheme;
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 520),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null)
                  Semantics(
                    liveRegion: true,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 16),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: scheme.errorContainer,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        _error!,
                        style: TextStyle(color: scheme.onErrorContainer),
                      ),
                    ),
                  ),
                DropdownButtonFormField<int>(
                  initialValue: _equipmentId,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Equipment',
                    border: OutlineInputBorder(),
                  ),
                  items: [
                    for (final e in items)
                      DropdownMenuItem<int>(
                        value: (e['id'] as num).toInt(),
                        child: Text(
                          '${text(e['name'])} (${text(e['asset_tag'])})',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                  onChanged: _busy ? null : (v) => setState(() => _equipmentId = v),
                  validator: _validateEquipment,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _reason,
                  enabled: !_busy,
                  maxLength: 255,
                  maxLines: 2,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Reason',
                    helperText: 'What do you need it for?',
                    border: OutlineInputBorder(),
                  ),
                  validator: _validateReason,
                ),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _days,
                  enabled: !_busy,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _busy ? null : _submit(),
                  decoration: const InputDecoration(
                    labelText: 'Days needed',
                    helperText: 'Between 1 and $maxDays days',
                    border: OutlineInputBorder(),
                  ),
                  validator: _validateDays,
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Submit request'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
