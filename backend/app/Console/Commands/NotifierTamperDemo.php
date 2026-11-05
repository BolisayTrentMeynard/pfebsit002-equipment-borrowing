<?php

namespace App\Console\Commands;

use App\Services\MessageSigner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Demo for the tamper test: posts six messages to the receiver and prints expected vs actual status.
 * Needs the server running (php artisan serve, and Caddy if NOTIFIER_URL is https) and a signing key in .env.
 * It sends only made-up ids. It never prints the key or a signature.
 */
class NotifierTamperDemo extends Command
{
    protected $signature = 'notifier:tamper-demo';

    protected $description = 'Send valid, altered, expired, replayed and wrongly signed messages to the receiver and show the answers';

    public function handle(MessageSigner $signer): int
    {
        if (app()->isProduction()) {
            $this->error('Demo command is disabled in production.');

            return self::FAILURE;
        }

        $body = json_encode(['notification_id' => 0, 'type' => 'reminder', 'request_id' => 0, 'recipient_id' => 0, 'attempt' => 1]);
        $real = config('notifier.signing_key');
        $rows = [];

        $rows[] = $this->post('1 valid message', $body, $signer->sign($body), 202);
        $rows[] = $this->post('2 body altered after signing', str_replace('"recipient_id":0', '"recipient_id":1', $body), $signer->sign($body), 401);
        $rows[] = $this->post('3 expired (signed 10 minutes ago)', $body, $signer->sign($body, null, time() - 600), 401);

        $replay = $signer->sign($body);
        $this->post('4a replay: first send', $body, $replay, 202);
        $rows[] = $this->post('4b replay: same message again', $body, $replay, 409);

        config(['notifier.signing_key' => hash('sha256', Str::random(40))]); // a wrong key, thrown away at once
        $wrong = $signer->sign($body);
        config(['notifier.signing_key' => $real]);
        $rows[] = $this->post('5 signed with a different key', $body, $wrong, 401);

        $rows[] = $this->post('6 no signature headers at all', $body, ['id' => '', 'timestamp' => '', 'signature' => ''], 400);

        $this->table(['Case', 'Expected', 'Got', 'Result'], $rows);

        return collect($rows)->contains(fn ($r) => $r[3] === 'FAIL') ? self::FAILURE : self::SUCCESS;
    }

    private function post(string $label, string $body, array $signed, int $expected): array
    {
        $headers = array_filter([
            'X-Message-Id' => $signed['id'],
            'X-Message-Timestamp' => (string) $signed['timestamp'],
            'X-Message-Signature' => $signed['signature'],
        ], fn ($v) => $v !== '');

        try {
            $status = Http::withOptions(['verify' => config('notifier.ca_bundle') ?: true])
                ->timeout(5)->withHeaders($headers)->withBody($body, 'application/json')->acceptJson()
                ->post(config('notifier.url'))->status();
        } catch (\Throwable $e) {
            $status = 0; // could not reach the receiver at all
        }

        return [$label, $expected, $status ?: 'no answer', $status === $expected ? 'PASS' : 'FAIL'];
    }
}
