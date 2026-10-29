import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'state/session.dart';

final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();

void main() {
  runApp(FinalsApp(session: Session(ApiClient())));
}

class FinalsApp extends StatelessWidget {
  const FinalsApp({super.key, required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: _navigatorKey,
      title: 'Equipment Borrowing',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1F5C6B)),
      ),
      home: ListenableBuilder(
        listenable: session,
        builder: (context, _) {
          if (!session.signedIn) {
            // Signed out (or session expired): close any open detail or form pages.
            WidgetsBinding.instance.addPostFrameCallback((_) {
              _navigatorKey.currentState?.popUntil((route) => route.isFirst);
            });
            return LoginScreen(session: session);
          }
          return HomeScreen(session: session);
        },
      ),
    );
  }
}
