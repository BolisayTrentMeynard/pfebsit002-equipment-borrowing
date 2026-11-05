<?php

namespace App\Services;

use App\Exceptions\InvalidSignedMessageException as Invalid;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Signs and verifies messages with HMAC-SHA256 (hash_hmac, PHP's standard library; nothing home-made).
 *
 * Why a MAC and not encryption: the message holds only ids (no names, no emails, no text). What must be
 * protected is integrity and authenticity (nobody can change it or forge it), not secrecy. The channel
 * itself is encrypted by HTTPS. See evidence/week-14/decision-record-003-message-protection.md.
 *
 * Signed text:  "v1.<message id>.<unix timestamp>.<raw body>"
 * Receiver checks, in this order: format, signature (constant time), timestamp window, replay.
 * The replay id is stored only after the signature is good, so strangers cannot fill the store.
 */
class MessageSigner
{
    public const VERSION = 'v1';

    /** @return array{id:string, timestamp:int, signature:string} headers values for $body */
    public function sign(string $body, ?string $id = null, ?int $timestamp = null): array
    {
        $id ??= (string) Str::uuid();
        $timestamp ??= time();

        return [
            'id' => $id,
            'timestamp' => $timestamp,
            'signature' => self::VERSION.'='.$this->mac($id, $timestamp, $body, $this->key('signing_key')),
        ];
    }

    /** @throws Invalid when the message must be rejected */
    public function verify(string $body, ?string $id, ?string $timestamp, ?string $signature): void
    {
        if (! $id || ! preg_match('/^[0-9a-f-]{36}$/i', $id)
            || ! $timestamp || ! ctype_digit($timestamp)
            || ! $signature || ! str_starts_with($signature, self::VERSION.'=')) {
            throw new Invalid(Invalid::MALFORMED);
        }

        $given = substr($signature, strlen(self::VERSION) + 1);
        $keys = array_filter([config('notifier.signing_key'), config('notifier.previous_signing_key')]);
        if (! $keys) {
            throw new RuntimeException('No signing key configured.');
        }

        $ok = false;
        foreach ($keys as $key) {
            $this->assertStrong($key);
            // hash_equals compares in constant time. Do not use === on signatures.
            $ok = hash_equals($this->mac($id, (int) $timestamp, $body, $key), $given) || $ok;
        }
        if (! $ok) {
            throw new Invalid(Invalid::BAD_SIGNATURE);
        }

        $tolerance = (int) config('notifier.tolerance_seconds');
        if (abs(time() - (int) $timestamp) > $tolerance) {
            throw new Invalid(Invalid::EXPIRED);
        }

        // Cache::add is atomic: it returns false when the id is already stored. The id only has to be
        // remembered as long as the timestamp check could still accept the message, so twice the window.
        if (! Cache::add('notifier:seen:'.strtolower($id), 1, $tolerance * 2)) {
            throw new Invalid(Invalid::REPLAYED);
        }
    }

    private function mac(string $id, int $timestamp, string $body, string $key): string
    {
        $this->assertStrong($key);

        return hash_hmac('sha256', self::VERSION.'.'.$id.'.'.$timestamp.'.'.$body, $key);
    }

    private function key(string $name): string
    {
        $key = config('notifier.'.$name);
        if (! $key) {
            throw new RuntimeException('NOTIFIER_SIGNING_KEY is not set in .env (run: php artisan notifier:generate-key).');
        }

        return $key;
    }

    private function assertStrong(string $key): void
    {
        if (strlen($key) < (int) config('notifier.min_key_length')) {
            throw new RuntimeException('Signing key is too short (minimum '.config('notifier.min_key_length').' characters).');
        }
    }
}
