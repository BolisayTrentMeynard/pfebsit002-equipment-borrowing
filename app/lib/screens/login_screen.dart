import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../state/session.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.session});

  final Session session;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  bool _busy = false;
  bool _showPassword = false;
  String? _error;
  Map<String, String> _serverFieldErrors = {};

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  String? _validateEmail(String? value) {
    final v = (value ?? '').trim();
    if (v.isEmpty) return 'Enter your email.';
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v)) {
      return 'Enter a valid email, like name@example.test.';
    }
    return _serverFieldErrors['email'];
  }

  String? _validatePassword(String? value) {
    if ((value ?? '').isEmpty) return 'Enter your password.';
    return _serverFieldErrors['password'];
  }

  Future<void> _submit() async {
    setState(() {
      _error = null;
      _serverFieldErrors = {};
    });
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _busy = true);
    try {
      await widget.session.login(_email.text.trim(), _password.text);
      // On success the session changes and the app swaps to the home screen.
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        switch (e.kind) {
          case ApiErrorKind.unauthorized:
            _error = 'Email or password is wrong.';
            break;
          case ApiErrorKind.validation:
            _serverFieldErrors = e.fieldErrors;
            _error = e.fieldErrors.isEmpty ? e.message : null;
            break;
          default:
            _error = e.message;
        }
      });
      if (_serverFieldErrors.isNotEmpty) _formKey.currentState?.validate();
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
    final notice = widget.session.notice;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Form(
                key: _formKey,
                child: AutofillGroup(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text('Equipment Borrowing', style: theme.textTheme.headlineMedium),
                      const SizedBox(height: 4),
                      Text(
                        'Sign in to request, approve, release and track shared equipment.',
                        style: theme.textTheme.bodyMedium,
                      ),
                      const SizedBox(height: 24),
                      if (notice != null) _Banner(message: notice, isError: false),
                      if (_error != null) _Banner(message: _error!, isError: true),
                      TextFormField(
                        controller: _email,
                        enabled: !_busy,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        autofillHints: const [AutofillHints.username],
                        decoration: const InputDecoration(
                          labelText: 'Email',
                          border: OutlineInputBorder(),
                        ),
                        validator: _validateEmail,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _password,
                        enabled: !_busy,
                        obscureText: !_showPassword,
                        textInputAction: TextInputAction.done,
                        autofillHints: const [AutofillHints.password],
                        onFieldSubmitted: (_) => _busy ? null : _submit(),
                        decoration: InputDecoration(
                          labelText: 'Password',
                          border: const OutlineInputBorder(),
                          suffixIcon: IconButton(
                            tooltip: _showPassword ? 'Hide password' : 'Show password',
                            icon: Icon(
                              _showPassword ? Icons.visibility_off : Icons.visibility,
                            ),
                            onPressed: () => setState(() => _showPassword = !_showPassword),
                          ),
                        ),
                        validator: _validatePassword,
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
                            : const Text('Sign in'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.message, required this.isError});

  final String message;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Semantics(
      liveRegion: true,
      child: Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isError ? scheme.errorContainer : scheme.secondaryContainer,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          message,
          style: TextStyle(
            color: isError ? scheme.onErrorContainer : scheme.onSecondaryContainer,
          ),
        ),
      ),
    );
  }
}
