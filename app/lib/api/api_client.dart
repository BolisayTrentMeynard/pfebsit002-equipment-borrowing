import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';

enum ApiErrorKind {
  network,
  unauthorized,
  forbidden,
  notFound,
  validation,
  conflict,
  tooManyRequests,
  server,
}

/// One error type for the whole app, so every screen can show the right state.
class ApiException implements Exception {
  ApiException(this.kind, this.message, {this.fieldErrors = const {}});

  final ApiErrorKind kind;
  final String message;

  /// Field name -> first server message (from HTTP 422).
  final Map<String, String> fieldErrors;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

  /// Kept in memory only. It is never written to browser storage or to logs.
  String? token;

  /// Called when an authenticated request gets HTTP 401 (token expired or invalid).
  void Function()? onUnauthorized;

  Uri _uri(String path, Map<String, String>? query) {
    final uri = Uri.parse('${AppConfig.apiBaseUrl}$path');
    return (query == null || query.isEmpty) ? uri : uri.replace(queryParameters: query);
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    final json = await _send(
      'POST',
      '/login',
      body: {'email': email, 'password': password},
      auth: false,
    );
    if (json is! Map) {
      throw ApiException(ApiErrorKind.server, 'The server gave an unexpected answer.');
    }
    return Map<String, dynamic>.from(json);
  }

  Future<dynamic> get(String path, {Map<String, String>? query}) =>
      _send('GET', path, query: query);

  Future<dynamic> post(String path, {Map<String, dynamic>? body}) =>
      _send('POST', path, body: body);

  Future<dynamic> _send(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
    bool auth = true,
  }) async {
    final request = http.Request(method, _uri(path, query));
    request.headers['Accept'] = 'application/json';
    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }
    if (auth && token != null) {
      request.headers['Authorization'] = 'Bearer $token';
    }

    http.Response response;
    try {
      final streamed = await _client.send(request).timeout(const Duration(seconds: 15));
      response = await http.Response.fromStream(streamed).timeout(const Duration(seconds: 15));
    } on TimeoutException {
      throw ApiException(
        ApiErrorKind.network,
        'The server took too long to answer. Check that the backend is running, then try again.',
      );
    } catch (_) {
      throw ApiException(
        ApiErrorKind.network,
        'Could not reach the server. Check your connection and that the backend is running.',
      );
    }

    dynamic json;
    try {
      final text = utf8.decode(response.bodyBytes);
      json = text.isEmpty ? null : jsonDecode(text);
    } catch (_) {
      json = null;
    }

    final code = response.statusCode;
    if (code >= 200 && code < 300) return json;

    final serverMessage =
        (json is Map && json['message'] is String) ? json['message'] as String : null;

    switch (code) {
      case 401:
        if (auth) onUnauthorized?.call();
        throw ApiException(
          ApiErrorKind.unauthorized,
          serverMessage ?? 'Please sign in again.',
        );
      case 403:
        throw ApiException(
          ApiErrorKind.forbidden,
          'Your role is not allowed to open this.',
        );
      case 404:
        throw ApiException(ApiErrorKind.notFound, 'That item was not found.');
      case 409:
        throw ApiException(
          ApiErrorKind.conflict,
          serverMessage ?? 'This action is not allowed in the current state.',
        );
      case 422:
        throw ApiException(
          ApiErrorKind.validation,
          serverMessage ?? 'Some fields are not valid.',
          fieldErrors: _fieldErrors(json),
        );
      case 429:
        throw ApiException(
          ApiErrorKind.tooManyRequests,
          'Too many attempts. Wait a minute and try again.',
        );
      default:
        // Internal details from the server are never shown to the user.
        throw ApiException(
          ApiErrorKind.server,
          'The server had a problem. Try again in a moment.',
        );
    }
  }

  Map<String, String> _fieldErrors(dynamic json) {
    final result = <String, String>{};
    if (json is Map && json['errors'] is Map) {
      (json['errors'] as Map).forEach((key, value) {
        if (value is List && value.isNotEmpty) {
          result[key.toString()] = value.first.toString();
        } else if (value is String) {
          result[key.toString()] = value;
        }
      });
    }
    return result;
  }
}
