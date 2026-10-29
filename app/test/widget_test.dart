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

  testWidgets('borrower opens the new request form and sees validation messages',
      (tester) async {
    final client = MockClient((request) async {
      final path = request.url.path;
      if (path.endsWith('/login')) {
        return _json({
          'token': 'test-token',
          'user': {'id': 1, 'name': 'Test Borrower One', 'role': 'borrower'},
        }, 200);
      }
      if (path.endsWith('/equipment')) {
        return _json({
          'data': [
            {'id': 1, 'name': 'Projector A', 'asset_tag': 'EQ-001', 'status': 'available'},
          ],
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

    await tester.tap(find.text('New request'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Submit request'));
    await tester.tap(find.text('Submit request'));
    await tester.pump();

    expect(find.text('Choose the equipment.'), findsOneWidget);
    expect(find.text('Give a short reason (at least 3 characters).'), findsOneWidget);
  });

  testWidgets('approver opens a pending request and sees Approve and Reject',
      (tester) async {
    final request7 = {
      'id': 7,
      'status': 'PENDING',
      'reason': 'Class demo',
      'requested_days': 2,
      'equipment': {'id': 1, 'name': 'Projector A', 'asset_tag': 'EQ-001'},
      'borrower': {'id': 1, 'name': 'Test Borrower One'},
    };
    final client = MockClient((request) async {
      final path = request.url.path;
      if (path.endsWith('/login')) {
        return _json({
          'token': 'test-token',
          'user': {'id': 3, 'name': 'Test Approver', 'role': 'approver'},
        }, 200);
      }
      if (path.endsWith('/requests/7')) {
        return _json({
          'data': {...request7, 'audit_logs': []},
        }, 200);
      }
      if (path.endsWith('/requests')) {
        return _json({'data': [request7]}, 200);
      }
      return _json({'data': []}, 200);
    });
    final session = Session(ApiClient(client: client));
    await tester.pumpWidget(FinalsApp(session: session));

    await tester.enterText(find.byType(TextFormField).first, 'approver@example.test');
    await tester.enterText(find.byType(TextFormField).last, 'DevPass123!');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Projector A (EQ-001)'));
    await tester.pumpAndSettle();

    expect(find.text('Approve'), findsOneWidget);
    expect(find.text('Reject'), findsOneWidget);
  });
}
