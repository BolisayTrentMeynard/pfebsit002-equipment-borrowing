import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'state/session.dart';

void main() {
  runApp(FinalsApp(session: Session(ApiClient())));
}

class FinalsApp extends StatelessWidget {
  const FinalsApp({super.key, required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Equipment Borrowing',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1F5C6B)),
      ),
      home: ListenableBuilder(
        listenable: session,
        builder: (context, _) => session.signedIn
            ? HomeScreen(session: session)
            : LoginScreen(session: session),
      ),
    );
  }
}
