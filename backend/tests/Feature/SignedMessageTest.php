<?php

namespace Tests\Feature;

use App\Exceptions\PermanentNotifierException;
use App\Exceptions\TransientNotifierException;
use App\Models\ReminderNotification;
use App\Services\FakeNotifier;
use App\Services\MessageSigner;
use App\Services\Notifier;
use App\Services\SignedHttpNotifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Week 14: signed messages between the sender (SignedHttpNotifier) and the receiver
 * (POST /api/notifier/receive). The keys below are made-up test values, not real secrets.
 * These tests need no database.
 */
class SignedMessageTest extends TestCase
{
    private const KEY = 'test-key-0123456789-abcdefghijklmnop-ONLY';
    private const OTHER_KEY = 'other-key-9876543210-zyxwvutsrqponmlk-ONLY';

    private string $body = '{"notification_id":7,"type":"reminder","request_id":3,"recipient_id":1,"attempt":1}';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'notifier.signing_key' => self::KEY,
            'notifier.previous_signing_key' => null,
            'notifier.tolerance_seconds' => 300,
            'notifier.url' => 'https://localhost:8443/api/notifier/receive',
        ]);
        Cache::flush();
    }

    /** Post raw bytes with the three signature headers, exactly like the sender does. */
    private function receive(string $body, array $signed, array $override = [])
    {
        $h = array_merge([
            'HTTP_X_MESSAGE_ID' => $signed['id'],
            'HTTP_X_MESSAGE_TIMESTAMP' => (string) $signed['timestamp'],
            'HTTP_X_MESSAGE_SIGNATURE' => $signed['signature'],
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $override);

        return $this->call('POST', '/api/notifier/receive', [], [], [], $h, $body);
    }

    private function sign(string $body, ?string $id = null, ?int $ts = null): array
    {
        return app(MessageSigner::class)->sign($body, $id, $ts);
    }

    public function test_ac15_valid_message_is_accepted(): void
    {
        $this->receive($this->body, $this->sign($this->body))
            ->assertStatus(202)->assertJson(['status' => 'accepted']);
    }

    public function test_ac16_altered_body_is_rejected(): void
    {
        $signed = $this->sign($this->body);
        $altered = str_replace('"recipient_id":1', '"recipient_id":2', $this->body);

        $this->receive($altered, $signed)->assertStatus(401)->assertJson(['reason' => 'bad_signature']);
    }

    public function test_ac16_altered_timestamp_is_rejected(): void
    {
        $signed = $this->sign($this->body, null, time() - 400); // signed as old
        $this->receive($this->body, $signed, ['HTTP_X_MESSAGE_TIMESTAMP' => (string) time()])
            ->assertStatus(401)->assertJson(['reason' => 'bad_signature']);
    }

    public function test_ac16_message_signed_with_another_key_is_rejected(): void
    {
        config(['notifier.signing_key' => self::OTHER_KEY]);
        $signed = $this->sign($this->body);
        config(['notifier.signing_key' => self::KEY]);

        $this->receive($this->body, $signed)->assertStatus(401)->assertJson(['reason' => 'bad_signature']);
    }

    public function test_ac17_expired_message_is_rejected_even_with_a_good_signature(): void
    {
        $this->receive($this->body, $this->sign($this->body, null, time() - 301))
            ->assertStatus(401)->assertJson(['reason' => 'expired']);
    }

    public function test_ac17_message_from_the_future_is_rejected(): void
    {
        $this->receive($this->body, $this->sign($this->body, null, time() + 301))
            ->assertStatus(401)->assertJson(['reason' => 'expired']);
    }

    public function test_ac18_replayed_message_is_rejected_the_second_time(): void
    {
        $signed = $this->sign($this->body);

        $this->receive($this->body, $signed)->assertStatus(202);
        $this->receive($this->body, $signed)->assertStatus(409)->assertJson(['reason' => 'replayed']);
    }

    public function test_a_bad_signature_does_not_use_up_the_message_id(): void
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $good = $this->sign($this->body, $id);

        $this->receive($this->body, $good, ['HTTP_X_MESSAGE_SIGNATURE' => 'v1='.str_repeat('0', 64)])->assertStatus(401);
        $this->receive($this->body, $good)->assertStatus(202); // the real sender is not blocked by an attacker
    }

    public function test_missing_or_malformed_headers_are_rejected(): void
    {
        $signed = $this->sign($this->body);

        $this->receive($this->body, $signed, ['HTTP_X_MESSAGE_SIGNATURE' => ''])->assertStatus(400);
        $this->receive($this->body, $signed, ['HTTP_X_MESSAGE_ID' => 'not-a-uuid'])->assertStatus(400);
        $this->receive($this->body, $signed, ['HTTP_X_MESSAGE_TIMESTAMP' => 'yesterday'])->assertStatus(400);
        $this->receive($this->body, $signed, ['HTTP_X_MESSAGE_SIGNATURE' => 'v2='.str_repeat('a', 64)])->assertStatus(400);
    }

    public function test_ac19_missing_or_short_key_is_refused_not_accepted(): void
    {
        $signed = $this->sign($this->body);

        config(['notifier.signing_key' => null]);
        $this->receive($this->body, $signed)->assertStatus(500);

        config(['notifier.signing_key' => 'too-short']);
        $this->receive($this->body, $signed)->assertStatus(500);

        $this->expectException(\RuntimeException::class);
        app(MessageSigner::class)->sign($this->body);
    }

    public function test_previous_key_is_accepted_during_rotation_only(): void
    {
        config(['notifier.signing_key' => self::OTHER_KEY]);
        $oldSigned = $this->sign($this->body);

        config(['notifier.signing_key' => self::KEY, 'notifier.previous_signing_key' => null]);
        $this->receive($this->body, $oldSigned)->assertStatus(401);

        config(['notifier.previous_signing_key' => self::OTHER_KEY]);
        $this->receive($this->body, $oldSigned)->assertStatus(202);
    }

    public function test_ac20_rejection_log_has_no_signature_key_or_body(): void
    {
        Log::spy();
        $signed = $this->sign($this->body);
        $this->receive($this->body.' ', $signed)->assertStatus(401);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) use ($signed) {
            $text = json_encode($context);

            return $message === 'notifier.message_rejected'
                && ! str_contains($text, $signed['signature'])
                && ! str_contains($text, self::KEY)
                && ! str_contains($text, 'notification_id');
        })->once();
    }

    // ---- sender side ----

    private function notification(): ReminderNotification
    {
        $n = new ReminderNotification(['type' => 'reminder', 'borrow_request_id' => 3, 'recipient_id' => 1, 'attempts' => 1]);
        $n->id = 7;

        return $n;
    }

    public function test_sender_posts_signed_ids_only_over_the_configured_url(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);

        app(SignedHttpNotifier::class)->send($this->notification());

        Http::assertSent(function (HttpRequest $r) {
            return $r->url() === 'https://localhost:8443/api/notifier/receive'
                && $r->hasHeader('X-Message-Id') && $r->hasHeader('X-Message-Timestamp') && $r->hasHeader('X-Message-Signature')
                && json_decode($r->body(), true) === [
                    'notification_id' => 7, 'type' => 'reminder', 'request_id' => 3, 'recipient_id' => 1, 'attempt' => 1,
                ];
        });
    }

    public function test_sender_to_receiver_end_to_end_and_retry_is_not_a_replay(): void
    {
        Http::fake(function (HttpRequest $r) {
            $res = $this->call('POST', '/api/notifier/receive', [], [], [], [
                'HTTP_X_MESSAGE_ID' => $r->header('X-Message-Id')[0],
                'HTTP_X_MESSAGE_TIMESTAMP' => $r->header('X-Message-Timestamp')[0],
                'HTTP_X_MESSAGE_SIGNATURE' => $r->header('X-Message-Signature')[0],
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], $r->body());

            return Http::response($res->getContent(), $res->getStatusCode(), ['Content-Type' => 'application/json']);
        });

        $notifier = app(SignedHttpNotifier::class);
        $notifier->send($this->notification());
        $notifier->send($this->notification()); // a retry builds a new message id, so it is accepted

        $this->addToAssertionCount(1); // no exception = both accepted
    }

    public function test_sender_maps_failures_to_retry_or_human(): void
    {
        $notifier = app(SignedHttpNotifier::class);

        // One fake whose answer we change between cases (a second Http::fake() would not replace the first).
        $next = null;
        $calls = 0;
        Http::fake(function () use (&$next, &$calls) {
            $calls++;
            if ($next instanceof \Throwable) {
                throw $next;
            }

            return $next;
        });

        $next = Http::response('boom', 503);
        $this->assertThrows(fn () => $notifier->send($this->notification()), TransientNotifierException::class);

        $next = Http::response('slow down', 429);
        $this->assertThrows(fn () => $notifier->send($this->notification()), TransientNotifierException::class);

        $next = new ConnectionException('cURL error 7: refused');
        $this->assertThrows(fn () => $notifier->send($this->notification()), TransientNotifierException::class);

        $next = Http::response(['reason' => 'bad_signature'], 401);
        $this->assertThrows(fn () => $notifier->send($this->notification()), PermanentNotifierException::class);

        $before = $calls;
        config(['notifier.signing_key' => null]);
        $this->assertThrows(fn () => $notifier->send($this->notification()), PermanentNotifierException::class);
        $this->assertSame($before, $calls, 'nothing may be sent without a valid key');
    }

    public function test_driver_setting_chooses_the_notifier(): void
    {
        config(['notifier.driver' => 'fake']);
        $this->assertInstanceOf(FakeNotifier::class, app()->make(Notifier::class));

        config(['notifier.driver' => 'signed_http']);
        $this->assertInstanceOf(SignedHttpNotifier::class, app()->make(Notifier::class));
    }
}
