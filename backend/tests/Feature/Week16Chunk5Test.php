<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * Week 16, chunk 5 (AC-44, AC-48, AC-50). Written BEFORE the fix, on purpose:
 *   - with the two fix files put back to their chunk 4 state, tests 1 and 3 must FAIL (proof of the weakness);
 *   - with the fix in place all four must pass.
 * No database is needed: the routes below only throw, and the log goes to a temporary file.
 */
class Week16Chunk5Test extends TestCase
{
    /** A made-up 64-character value that looks like a token hash. Never a real one. */
    private const FAKE_HASH = 'abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234';

    private string $logFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Send the log to a temporary file, so the test can read exactly what would reach laravel.log.
        $this->logFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'chunk5-'.uniqid('', true).'.log';
        config([
            'logging.default' => 'chunk5',
            'logging.channels.chunk5' => ['driver' => 'single', 'path' => $this->logFile, 'level' => 'debug'],
            'app.debug' => false,
        ]);
        Log::forgetChannel('chunk5');
        Log::forgetChannel();
    }

    protected function tearDown(): void
    {
        if ($this->logFile !== '' && is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        parent::tearDown();
    }

    private function logText(): string
    {
        return is_file($this->logFile) ? (string) file_get_contents($this->logFile) : '';
    }

    public function test_ac44_a_failed_query_is_logged_without_sql_bindings_or_host(): void
    {
        Route::get('/api/_chunk5/query-fail', function () {
            // The message Laravel builds from these contains the SQL and the bound value (and, in Laravel 12,
            // the host, port and database name). That is exactly what must NOT reach the log.
            throw new QueryException(
                'mysql',
                'select * from `api_tokens` where `token_hash` = ? limit 1',
                [self::FAKE_HASH],
                new PDOException('SQLSTATE[HY000] [2002] Connection refused', 2002)
            );
        });

        $res = $this->getJson('/api/_chunk5/query-fail');

        $res->assertStatus(500);
        $this->assertSame(['message' => 'Server Error'], $res->json());

        $log = $this->logText();
        $this->assertStringContainsString('db.query_failed', $log, 'the sanitized entry must exist');
        $this->assertStringContainsString('2002', $log, 'the error code must still be logged');
        $this->assertStringNotContainsString(self::FAKE_HASH, $log, 'a bound value reached the log');
        $this->assertStringNotContainsString('api_tokens', $log, 'SQL text reached the log');
        $this->assertStringNotContainsString('SQLSTATE[', $log, 'the raw exception message reached the log');
        $this->assertStringNotContainsString('Connection refused', $log, 'the raw exception message reached the log');
    }

    public function test_ac44_other_exceptions_are_still_logged_with_their_message(): void
    {
        Route::get('/api/_chunk5/other-fail', function () {
            throw new RuntimeException('chunk5 ordinary failure');
        });

        $this->getJson('/api/_chunk5/other-fail')->assertStatus(500);

        $this->assertStringContainsString('chunk5 ordinary failure', $this->logText(), 'ordinary exceptions must be logged as before');
    }

    public function test_ac48_the_database_connect_timeout_is_applied_when_it_is_set(): void
    {
        $this->setTimeoutEnv('3');

        $options = (require config_path('database.php'))['connections']['mysql']['options'];

        $this->setTimeoutEnv(null);

        $this->assertSame(3, $options[PDO::ATTR_TIMEOUT] ?? null);
    }

    public function test_ac48_there_is_no_connect_timeout_option_by_default(): void
    {
        $this->setTimeoutEnv(null);

        $options = (require config_path('database.php'))['connections']['mysql']['options'];

        $this->assertArrayNotHasKey(PDO::ATTR_TIMEOUT, $options);
    }

    /** Sets or clears DB_CONNECT_TIMEOUT where Laravel's env() reads it from. */
    private function setTimeoutEnv(?string $value): void
    {
        if ($value === null) {
            putenv('DB_CONNECT_TIMEOUT');
            unset($_ENV['DB_CONNECT_TIMEOUT'], $_SERVER['DB_CONNECT_TIMEOUT']);

            return;
        }

        putenv('DB_CONNECT_TIMEOUT='.$value);
        $_ENV['DB_CONNECT_TIMEOUT'] = $value;
        $_SERVER['DB_CONNECT_TIMEOUT'] = $value;
    }
}
