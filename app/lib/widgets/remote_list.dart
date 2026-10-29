import 'package:flutter/material.dart';

import '../api/api_client.dart';
import 'state_view.dart';

/// Loads a list from the API and shows loading, empty, unauthorized, not-allowed,
/// network and server-error states. Pull down (or use Refresh) to reload.
class RemoteList extends StatefulWidget {
  const RemoteList({
    super.key,
    required this.api,
    required this.path,
    required this.itemBuilder,
    required this.emptyTitle,
    required this.emptyMessage,
    this.query,
  });

  final ApiClient api;
  final String path;
  final Map<String, String>? query;
  final Widget Function(BuildContext context, Map<String, dynamic> item) itemBuilder;
  final String emptyTitle;
  final String emptyMessage;

  @override
  State<RemoteList> createState() => _RemoteListState();
}

class _RemoteListState extends State<RemoteList> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final json = await widget.api.get(widget.path, query: widget.query);
    final data = json is Map ? json['data'] : null;
    if (data is! List) {
      throw ApiException(ApiErrorKind.server, 'The server gave an unexpected answer.');
    }
    return data.whereType<Map>().map((m) => Map<String, dynamic>.from(m)).toList();
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _refresh() async {
    _reload();
    try {
      await _future;
    } catch (_) {
      // The error is shown by the builder below.
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return Center(
            child: Semantics(
              label: 'Loading',
              child: CircularProgressIndicator(),
            ),
          );
        }

        final error = snapshot.error;
        if (error != null) return _errorView(error);

        final items = snapshot.data ?? const <Map<String, dynamic>>[];
        if (items.isEmpty) {
          return StateView(
            icon: Icons.inbox_outlined,
            title: widget.emptyTitle,
            message: widget.emptyMessage,
            actionLabel: 'Refresh',
            onAction: _reload,
          );
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            itemBuilder: (context, i) => Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 720),
                child: widget.itemBuilder(context, items[i]),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _errorView(Object error) {
    if (error is ApiException) {
      switch (error.kind) {
        case ApiErrorKind.unauthorized:
          return const StateView(
            icon: Icons.lock_outline,
            title: 'Please sign in again',
            message: 'Your session is no longer valid.',
          );
        case ApiErrorKind.forbidden:
          return const StateView(
            icon: Icons.block,
            title: 'Not allowed',
            message: 'Your role is not allowed to open this list.',
          );
        case ApiErrorKind.network:
          return StateView(
            icon: Icons.cloud_off_outlined,
            title: 'Cannot reach the server',
            message: error.message,
            actionLabel: 'Try again',
            onAction: _reload,
          );
        default:
          return StateView(
            icon: Icons.error_outline,
            title: 'Something went wrong',
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
}
