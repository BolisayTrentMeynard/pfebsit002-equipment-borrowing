// Week 15, chunk 3: Flutter Web state tests (AC-34, AC-35, AC-36).
// The app code is NOT changed in this chunk. These tests check the states the app already has:
// 403, 401 back to login, 422 field errors, 429, 500 without details, network error with
// "Try again", a 15 s timeout, desktop and narrow layout, and form limits against server limits.
import 'dart:async';
import 'dart:convert';

import 'package:finals_app/api/api_client.dart';
import 'package:finals_app/main.dart';
import 'package:finals_app/state/session.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

http.Response _json(Object body, int status) => http.Response(
      jsonEncode(body),
      status,
      headers: {'content-type': 'application/json'},
    );

const _request7 = {
  'id': 7,
  'status': 'PENDING',
  'reason': 'Class demo',
  'requested_days': 2,
  'equipment': {'id': 1, 'name': 'Projector A', 'asset_tag': 'EQ-001'},
  'borrower': {'id': 1, 'name': 'Test Borrower One'},
};

const _equipment = {
  'data': [
    {'id': 1, 'name': 'Projector A', 'asset_tag': 'EQ-001', 'status': 'available'},
  ],
};

/// A fake API: /login always works (as a borrower); every other call goes to [others].
Session _session(Future<http.Response> Function(http.Request request) others) {
  final client = MockClient((request) async {
    if (request.url.path.endsWith('/login')) {
      return _json({
        'token': 'test-token',
        'user': {'id': 1, 'name': 'Test Borrower One', 'role': 'borrower'},
      }, 200);
    }
    return others(request);
  });
  return Session(ApiClient(client: client));
}

Future<void> _fillAndSignIn(WidgetTester tester) async {
  await tester.enterText(find.byType(TextFormField).first, 'borrower1@example.test');
  await tester.enterText(find.byType(TextFormField).last, 'DevPass123!');
  await tester.tap(find.text('Sign in'));
}

Future<void> _signIn(WidgetTester tester, Session session) async {
  await tester.pumpWidget(FinalsApp(session: session));
  await _fillAndSignIn(tester);
  await tester.pumpAndSettle();
}

Future<void> _openNewRequestForm(WidgetTester tester) async {
  await tester.tap(find.text('New request'));
  await tester.pumpAndSettle();
}

void _size(WidgetTester tester, double width, double height) {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
}

Session _formSession({Future<http.Response> Function(http.Request request)? onPost}) {
  return _session((request) async {
    final path = request.url.path;
    if (path.endsWith('/equipment')) return _json(_equipment, 200);
    if (request.method == 'POST' && path.endsWith('/requests') && onPost != null) {
      return onPost(request);
    }
    return _json({'data': []}, 200);
  });
}

void main() {
  // ---- AC-35: one test per state ----

  testWidgets('403 on a request detail shows "Not allowed" with an understandable message',
      (tester) async {
    final session = _session((request) async {
      final path = request.url.path;
      if (path.endsWith('/requests/7')) {
        return _json({'message': 'You can only view your own requests.'}, 403);
      }
      return _json({'data': [_request7]}, 200);
    });
    await _signIn(tester, session);

    await tester.tap(find.text('Projector A (EQ-001)'));
    await tester.pumpAndSettle();

    expect(find.text('Not allowed'), findsOneWidget);
    expect(find.text('You can only open your own requests.'), findsOneWidget);
    expect(session.signedIn, isTrue); // 403 does not sign the person out
  });

  testWidgets('401 while signed in returns to login with the session-expired notice',
      (tester) async {
    var expired = false;
    final session = _session((request) async {
      if (expired) return _json({'message': 'Unauthenticated.'}, 401);
      return _json({'data': []}, 200);
    });
    await _signIn(tester, session);
    expect(find.text('No requests yet'), findsOneWidget);

    expired = true;
    await tester.tap(find.text('Refresh').first);
    await tester.pumpAndSettle();

    expect(session.signedIn, isFalse);
    expect(find.text('Your session expired. Please sign in again.'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
    expect(session.api.token, isNull); // the token is dropped from memory
  });

  testWidgets('422 shows the server message under the field', (tester) async {
    const serverMessage = 'The requested days field must be between 1 and 7.';
    final session = _formSession(onPost: (request) async {
      return _json({
        'message': serverMessage,
        'errors': {
          'requested_days': [serverMessage],
        },
      }, 422);
    });
    await _signIn(tester, session);
    await _openNewRequestForm(tester);

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Projector A (EQ-001)').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextFormField, 'Reason'), 'Class demo');
    await tester.enterText(find.widgetWithText(TextFormField, 'Days needed'), '2');
    await tester.ensureVisible(find.text('Submit request'));
    await tester.tap(find.text('Submit request'));
    await tester.pumpAndSettle();

    expect(find.text(serverMessage), findsOneWidget);
  });

  testWidgets('429 on login shows the wait message and stays on login', (tester) async {
    final client = MockClient((_) async => _json({'message': 'Too many attempts.'}, 429));
    final session = Session(ApiClient(client: client));
    await tester.pumpWidget(FinalsApp(session: session));

    await _fillAndSignIn(tester);
    await tester.pumpAndSettle();

    expect(find.text('Too many attempts. Wait a minute and try again.'), findsOneWidget);
    expect(session.signedIn, isFalse);
  });

  testWidgets('500 shows a plain message and never the server detail', (tester) async {
    final session = _session((request) async {
      return _json({'message': 'SQLSTATE[42S02]: Base table or view not found: secret_table'}, 500);
    });
    await _signIn(tester, session);

    expect(find.text('The server had a problem. Try again in a moment.'), findsOneWidget);
    expect(find.textContaining('SQLSTATE'), findsNothing);
    expect(find.textContaining('secret_table'), findsNothing);
    expect(find.text('Try again'), findsOneWidget);
  });

  // ---- AC-34: unreachable server and timeout ----

  testWidgets('network error shows "Try again" and recovers when the server is back',
      (tester) async {
    var down = true;
    final session = _session((request) async {
      if (down) throw http.ClientException('connection refused');
      return _json({'data': []}, 200);
    });
    await _signIn(tester, session);

    expect(find.text('Cannot reach the server'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);

    down = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.text('No requests yet'), findsOneWidget);
  });

  testWidgets('no answer within 15 seconds becomes the network-error state', (tester) async {
    final never = Completer<http.Response>();
    final session = _session((request) => never.future);
    await tester.pumpWidget(FinalsApp(session: session));
    await _fillAndSignIn(tester);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    expect(session.signedIn, isTrue);

    await tester.pump(const Duration(seconds: 16));
    await tester.pump();

    expect(find.text('Cannot reach the server'), findsOneWidget);
    expect(find.textContaining('took too long'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);
  });

  // ---- AC-36: desktop and narrow layout ----

  for (final size in const [(1280.0, 800.0, 'desktop'), (360.0, 640.0, 'narrow')]) {
    testWidgets('login and new request form are usable at ${size.$3} width', (tester) async {
      _size(tester, size.$1, size.$2);
      final session = _formSession();
      await tester.pumpWidget(FinalsApp(session: session));
      expect(find.text('Sign in'), findsOneWidget);

      await _fillAndSignIn(tester);
      await tester.pumpAndSettle();
      await _openNewRequestForm(tester);

      await tester.ensureVisible(find.text('Submit request'));
      expect(find.text('Submit request'), findsOneWidget);
      expect(find.widgetWithText(TextFormField, 'Reason'), findsOneWidget);
      expect(find.widgetWithText(TextFormField, 'Days needed'), findsOneWidget);
      expect(tester.takeException(), isNull); // no overflow or layout error
    });
  }

  // ---- AC-36: form limits compared with the server limits ----

  testWidgets('form limits match the server: reason 3 to 255, days 1 to 7', (tester) async {
    final session = _formSession();
    await _signIn(tester, session);
    await _openNewRequestForm(tester);

    const reasonError = 'Give a short reason (at least 3 characters).';
    const daysError = 'Enter 1 to 7 days.';
    final reason = find.widgetWithText(TextFormField, 'Reason');
    final days = find.widgetWithText(TextFormField, 'Days needed');

    // Below the limits: 2 characters, 8 days.
    await tester.enterText(reason, 'ab');
    await tester.enterText(days, '8');
    await tester.ensureVisible(find.text('Submit request'));
    await tester.tap(find.text('Submit request'));
    await tester.pump();
    expect(find.text(reasonError), findsOneWidget);
    expect(find.text(daysError), findsOneWidget);

    // 0 days is also refused.
    await tester.enterText(days, '0');
    await tester.tap(find.text('Submit request'));
    await tester.pump();
    expect(find.text(daysError), findsOneWidget);

    // On the limits: 3 characters and 7 days pass the form checks (1 day too).
    await tester.enterText(reason, 'abc');
    await tester.enterText(days, '7');
    await tester.tap(find.text('Submit request'));
    await tester.pump();
    expect(find.text(reasonError), findsNothing);
    expect(find.text(daysError), findsNothing);

    await tester.enterText(days, '1');
    await tester.tap(find.text('Submit request'));
    await tester.pump();
    expect(find.text(daysError), findsNothing);

    // 256 characters cannot be typed: the field stops at 255, the server maximum.
    await tester.enterText(reason, 'x' * 256);
    await tester.pump();
    final typed = tester.widget<TextFormField>(reason).controller!.text;
    expect(typed.length, lessThanOrEqualTo(255));
  });
}
