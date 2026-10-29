import 'package:flutter/material.dart';

import '../api/api_client.dart';

void showInfo(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

/// Shows the message from an [ApiException]. Anything else gets a safe generic text,
/// so internal details never reach the screen.
void showError(BuildContext context, Object error) {
  final scheme = Theme.of(context).colorScheme;
  final message = error is ApiException
      ? error.message
      : 'Something unexpected happened. Please try again.';
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        backgroundColor: scheme.errorContainer,
        content: Text(message, style: TextStyle(color: scheme.onErrorContainer)),
      ),
    );
}

Future<bool> confirmDialog(
  BuildContext context, {
  required String title,
  required String message,
  required String confirmLabel,
}) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(ctx, true),
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return result ?? false;
}

/// Asks for a short note. Returns null if cancelled, otherwise the trimmed note
/// (an empty string is possible when the note is optional).
Future<String?> askNote(
  BuildContext context, {
  required String title,
  required String message,
  required bool required,
  required String confirmLabel,
}) {
  return showDialog<String>(
    context: context,
    builder: (ctx) => _NoteDialog(
      title: title,
      message: message,
      required: required,
      confirmLabel: confirmLabel,
    ),
  );
}

class _NoteDialog extends StatefulWidget {
  const _NoteDialog({
    required this.title,
    required this.message,
    required this.required,
    required this.confirmLabel,
  });

  final String title;
  final String message;
  final bool required;
  final String confirmLabel;

  @override
  State<_NoteDialog> createState() => _NoteDialogState();
}

class _NoteDialogState extends State<_NoteDialog> {
  final _formKey = GlobalKey<FormState>();
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  String? _validate(String? value) {
    final v = (value ?? '').trim();
    if (widget.required && v.length < 3) return 'Write at least 3 characters.';
    if (v.length > 255) return 'Keep it under 255 characters.';
    return null;
  }

  void _submit() {
    if (_formKey.currentState?.validate() ?? false) {
      Navigator.pop(context, _controller.text.trim());
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.message),
            const SizedBox(height: 12),
            TextFormField(
              controller: _controller,
              autofocus: true,
              maxLength: 255,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: widget.required ? 'Note' : 'Note (optional)',
                border: const OutlineInputBorder(),
              ),
              validator: _validate,
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(onPressed: _submit, child: Text(widget.confirmLabel)),
      ],
    );
  }
}
