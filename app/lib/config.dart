/// Settings that are safe to ship in the web client. No secrets belong here.
///
/// Override the API address at run time:
///   flutter run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000/api',
  );
}
