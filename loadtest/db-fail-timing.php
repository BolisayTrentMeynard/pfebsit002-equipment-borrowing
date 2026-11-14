<?php
/**
 * Week 16, chunk 5: how long does ONE refused database connection take on this PC? (plain PHP, no Laravel)
 *
 * Use a CLOSED port, never the real MySQL port, so MySQL is not touched at all:
 *   php loadtest\db-fail-timing.php 127.0.0.1 3399 5
 * Prints one line per attempt: milliseconds until PDO gave up. No password is used and no error text is printed
 * (only the error code), so the output is safe to screenshot.
 *
 * Why it exists: in chunk 4b one failing request took about 9 s. This separates "the operating system needs this
 * long to refuse one connection" from "Laravel tries several times". Compare with:
 *   $env:APP_ENV='staging'; $env:DB_PORT='3399'; Measure-Command { php artisan ops:status | Out-Null }
 */
$host = $argv[1] ?? '127.0.0.1';
$port = $argv[2] ?? '3399';
$n = max(1, (int) ($argv[3] ?? 5));

if ((int) $port === 3306) {
    fwrite(STDERR, "STOP: use a closed port such as 3399, not 3306.\n");
    exit(1);
}

function attempt(string $host, string $port, array $options): array
{
    $t = hrtime(true);
    $code = 'connected?!';
    try {
        new PDO("mysql:host=$host;port=$port", 'root', '', $options + [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable $e) {
        $code = (string) $e->getCode();
    }

    return [round((hrtime(true) - $t) / 1e6), $code];
}

echo "PHP " . PHP_VERSION . ", target $host:$port (closed port expected)\n";
foreach (['default options' => [], 'ATTR_TIMEOUT = 2' => [PDO::ATTR_TIMEOUT => 2]] as $label => $opts) {
    echo "\n$label\n";
    $all = [];
    for ($i = 1; $i <= $n; $i++) {
        [$ms, $code] = attempt($host, $port, $opts);
        $all[] = $ms;
        echo sprintf("  attempt %d: %6.0f ms  (error code %s)\n", $i, $ms, $code);
    }
    sort($all);
    echo sprintf("  median %.0f ms, max %.0f ms\n", $all[(int) floor((count($all) - 1) / 2)], end($all));
}
