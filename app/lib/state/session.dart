import 'package:flutter/foundation.dart';

import '../api/api_client.dart';

class AppUser {
  const AppUser({required this.id, required this.name, required this.role});

  final int id;
  final String name;
  final String role; // borrower | approver | staff
}

/// Who is signed in. The role shown in the UI only chooses which screens appear;
/// the server still checks the role on every request.
class Session extends ChangeNotifier {
  Session(this.api) {
    api.onUnauthorized = _expired;
  }

  final ApiClient api;
  AppUser? user;
  String? notice;

  bool get signedIn => user != null;

  Future<void> login(String email, String password) async {
    final json = await api.login(email, password);
    final u = json['user'];
    final token = json['token'];
    if (u is! Map || token is! String) {
      throw ApiException(ApiErrorKind.server, 'The server gave an unexpected answer.');
    }
    api.token = token;
    user = AppUser(
      id: (u['id'] as num).toInt(),
      name: u['name'].toString(),
      role: u['role'].toString(),
    );
    notice = null;
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await api.post('/logout');
    } catch (_) {
      // Signing out locally is enough even if the server cannot be reached.
    }
    _clear();
  }

  void _expired() {
    if (!signedIn) return;
    _clear(message: 'Your session expired. Please sign in again.');
  }

  void _clear({String? message}) {
    api.token = null;
    user = null;
    notice = message;
    notifyListeners();
  }
}
