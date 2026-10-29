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

void main() {
  testWidgets('empty login form shows validation messages', (tester) async {
    final session = Session(ApiClient(client: MockClient((_) async => _json({}, 200))));
    await tester.pumpWidget(FinalsApp(session: session));

    await tester.tap(find.text('Sign in'));
    await tester.pump();

    expect(find.text('Enter your email.'), findsOneWidget);
    expect(find.text('Enter your password.'), findsOneWidget);
  });

  testWidgets('wrong password shows an error and stays on login', (tester) async {
    final session = Session(
      ApiClient(client: MockClient((_) async => _json({'message': 'Invalid credentials.'}, 401))),
    );
    await tester.pumpWidget(FinalsApp(session: session));

    await tester.enterText(find.byType(TextFormField).first, 'borrower1@example.test');
    await tester.enterText(find.byType(TextFormField).last, 'wrong');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Email or password is wrong.'), findsOneWidget);
    expect(session.signedIn, isFalse);
  });

  testWidgets('borrower signs in and sees the empty requests state', (tester) async {
    final client = MockClient((request) async {
      if (request.url.path.endsWith('/login')) {
        return _json({
          'token': 'test-token',
          'user': {'id': 1, 'name': 'Test Borrower One', 'role': 'borrower'},
        }, 200);
      }
      return _json({'data': []}, 200);
    });
    final session = Session(ApiClient(client: client));
    await tester.pumpWidget(FinalsApp(session: session));

    await tester.enterText(find.byType(TextFormField).first, 'borrower1@example.test');
    await tester.enterText(find.byType(TextFormField).last, 'DevPass123!');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(session.signedIn, isTrue);
    expect(find.text('No requests yet'), findsOneWidget);
  });
}
