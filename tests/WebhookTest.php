<?php

declare(strict_types=1);

namespace Voicast\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Voicast\EventType;
use Voicast\Exception\VoicastException;
use Voicast\Exception\WebhookVerificationException;
use Voicast\ToolCallRequest;
use Voicast\Webhook;
use Voicast\WebhookEvent;

use function Voicast\verifyToolCall;
use function Voicast\verifyWebhook;

/**
 * Receiver-side verification of webhooks and in-call tool calls. The vectors are signed like voicast and
 * by the Node SDK, with results decided by the Node SDK (scripts/sdk-vectors.mjs).
 */
final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    private const OTHER = 'whsec_dGhpcyBpcyBhbm90aGVyIHNlY3JldCBrZXk=';

    /** @return list<array<string, mixed>> */
    private static function vectors(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/testdata/webhook_vectors.json'), true)['vectors'];
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function vectorCases(): array
    {
        $out = [];
        foreach (self::vectors() as $v) {
            $out[$v['name']] = [$v];
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private static function find(string $name): array
    {
        foreach (self::vectors() as $v) {
            if ($v['name'] === $name) {
                return $v;
            }
        }
        throw new \LogicException($name);
    }

    /** @param array<string, mixed> $v */
    private static function at(array $v): FixedClockWebhook
    {
        return new FixedClockWebhook($v['secret'], $v['now'], $v['tolerance'] ?? 300);
    }

    /** @param array<string, mixed> $v */
    #[DataProvider('vectorCases')]
    public function testVectors(array $v): void
    {
        $wh = self::at($v);
        $run = match ($v['verify'] ?? null) {
            'webhook' => fn () => $wh->verifyEvent($v['payload'], $v['headers'])->raw,
            'tool_call' => fn () => $wh->verifyToolCall($v['payload'], $v['headers'])->raw,
            default => fn () => $wh->verify($v['payload'], $v['headers']),
        };
        if ($v['expect'] === null) {
            try {
                $run();
                self::fail('verified');
            } catch (WebhookVerificationException $e) {
                self::assertSame($v['error'], $e->getMessage());
            }
            return;
        }
        self::assertEquals($v['expect'], $run());
    }

    public function testSignMatchesToolsPyAndNode(): void
    {
        foreach (['signed like voicast', 'signed by the node sdk', 'standard webhooks spec vector'] as $name) {
            $v = self::find($name);
            $h = $v['headers'];
            self::assertSame($h['webhook-signature'], (new Webhook([$v['secret'], self::OTHER]))->sign($h['webhook-id'], (int) $h['webhook-timestamp'], $v['payload']));
        }
        $v = self::find('signed like voicast');
        $h = $v['headers'];
        $date = (new \DateTimeImmutable())->setTimestamp((int) $h['webhook-timestamp']);
        self::assertSame($h['webhook-signature'], (new Webhook(self::SECRET))->sign($h['webhook-id'], $date, $v['payload']));
    }

    public function testEventAndToolCallObjects(): void
    {
        $v = self::find('webhook event');
        $e = self::at($v)->verifyEvent($v['payload'], $v['headers']);
        self::assertInstanceOf(WebhookEvent::class, $e);
        self::assertSame([EventType::CALL_ENDED, '2026-10-03T00:00:00.000Z', 'completed', ['id' => 'ten_1', 'external_id' => 'clinic-1']], [$e->type, $e->timestamp, $e->data['outcome'], $e->tenant()]);
        $billing = new WebhookEvent(['type' => 'billing.balance_low', 'data' => ['balance_yen' => 100]]);
        self::assertSame([null, null], [$billing->timestamp, $billing->tenant()]);
        self::assertNull((new WebhookEvent(['type' => 'x', 'data' => 5]))->tenant());

        $v = self::find('tool call');
        $tc = self::at($v)->verifyToolCall($v['payload'], $v['headers']);
        self::assertInstanceOf(ToolCallRequest::class, $tc);
        self::assertSame(['find_free_slots', 'tc_1', ['date' => '2026-10-04'], 'phone', '+81312345678', 'clinic-1'], [$tc->tool, $tc->toolCallId, $tc->arguments, $tc->call['channel'], $tc->call['from'], $tc->call['tenant']['external_id']]);
        $odd = new ToolCallRequest(['type' => 'tool.call', 'tool' => 'x', 'arguments' => 5, 'call' => []]);
        self::assertSame([null, [], [], false], [$odd->toolCallId, $odd->arguments, $odd->call, $odd->test]);
        $test = new ToolCallRequest(['type' => 'tool.call', 'tool' => 'x', 'tool_call_id' => 'try_1', 'call' => ['id' => 'try_1', 'channel' => 'test', 'tenant' => null], 'test' => true]);
        self::assertSame([true, 'test', null], [$test->test, $test->call['channel'], $test->call['tenant']]);
        self::assertCount(10, EventType::all());
        self::assertContains('billing.cap_reached', EventType::all());
    }

    public function testFunctionsWithTheRealClock(): void
    {
        $wh = new Webhook(self::SECRET);
        $now = time();
        $sign = fn (string $body) => ['webhook-id' => 'msg_now', 'webhook-timestamp' => (string) $now, 'webhook-signature' => $wh->sign('msg_now', $now, $body)];
        $ev = '{"type":"call.started","timestamp":"t","data":{"call_id":"c","from":"+81"}}';
        self::assertSame('+81', verifyWebhook($ev, $sign($ev), [self::OTHER, self::SECRET], 10)->data['from']);
        $tool = '{"type":"tool.call","tool":"x","tool_call_id":"tc","arguments":{},"call":{}}';
        self::assertSame('x', verifyToolCall($tool, $sign($tool), self::SECRET)->tool);
        $this->expectException(WebhookVerificationException::class);
        verifyWebhook($ev, $sign($ev), self::OTHER);
    }

    public function testHeaderForms(): void
    {
        $v = self::find('signed like voicast');
        $wh = self::at($v);
        $h = $v['headers'];
        $upper = ['Webhook-Id' => $h['webhook-id'], 'WEBHOOK-TIMESTAMP' => [$h['webhook-timestamp'], 'x'], 'webhook-signature' => $h['webhook-signature']];
        self::assertEquals($v['expect'], $wh->verify($v['payload'], $upper));
        $server = ['HTTP_WEBHOOK_ID' => $h['webhook-id'], 'HTTP_WEBHOOK_TIMESTAMP' => $h['webhook-timestamp'], 'HTTP_WEBHOOK_SIGNATURE' => $h['webhook-signature'], 'REQUEST_METHOD' => 'POST'];
        self::assertEquals($v['expect'], $wh->verify($v['payload'], $server));
        $psr7 = new class ($h) {
            /** @param array<string, string> $h */
            public function __construct(private array $h)
            {
            }

            public function getHeaderLine(string $name): string
            {
                return $this->h[strtolower($name)] ?? '';
            }
        };
        self::assertEquals($v['expect'], $wh->verify($v['payload'], $psr7));
        foreach ([[...$h, 'webhook-id' => []], [...$h, 'webhook-id' => null], new class () {
            public function getHeaderLine(string $name): string
            {
                return '';
            }
        }] as $bad) {
            try {
                $wh->verify($v['payload'], $bad);
                self::fail('verified');
            } catch (WebhookVerificationException $e) {
                self::assertSame('Missing required headers', $e->getMessage());
            }
        }
    }

    public function testNotJsonKeepsCause(): void
    {
        $v = self::find('signed body that is not JSON');
        try {
            self::at($v)->verify($v['payload'], $v['headers']);
            self::fail('verified');
        } catch (WebhookVerificationException $e) {
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testDefaultToleranceAndClock(): void
    {
        $v = self::find('signed like voicast');
        $wh = new Webhook(self::SECRET);
        self::assertSame(300, $wh->tolerance);
        $this->expectExceptionMessage('Message timestamp too old');
        $wh->verify($v['payload'], $v['headers']); // signed in 2026-10, checked with the real clock (later)
    }

    public function testSecretChecks(): void
    {
        foreach (['', [], [self::SECRET, ''], [self::SECRET, 5]] as $secret) {
            try {
                new Webhook($secret);
                self::fail('accepted');
            } catch (VoicastException $e) {
                self::assertNotInstanceOf(WebhookVerificationException::class, $e);
                self::assertSame('Webhook secret is required', $e->getMessage());
            }
        }
        try {
            new Webhook('whsec_***');
            self::fail('accepted');
        } catch (VoicastException $e) {
            self::assertStringContainsString('not valid base64', $e->getMessage());
        }
        // unpadded base64 is accepted, like atob in the Node SDK
        self::assertSame(300, (new Webhook('whsec_dGhpcyBpcyBhbm90aGVyIHNlY3JldCBrZXk'))->tolerance);
    }
}
