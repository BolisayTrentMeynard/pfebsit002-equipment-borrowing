/// Settings that are safe to ship in the web client. No secrets belong here.
///
/// The default is the HTTPS address served by Caddy in front of the Laravel API (Week 14).
/// To use the plain local server instead (no Caddy), override it at run time:
///   flutter run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://localhost:8443/api',
  );
}
