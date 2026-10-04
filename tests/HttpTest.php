<?php

declare(strict_types=1);

namespace Voicast\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Voicast\Client;
use Voicast\Exception\ApiException;
use Voicast\Exception\AuthenticationException;
use Voicast\Exception\ConflictException;
use Voicast\Exception\ConnectionException;
use Voicast\Exception\NotFoundException;
use Voicast\Exception\PaymentRequiredException;
use Voicast\Exception\PermissionException;
use Voicast\Exception\RateLimitException;
use Voicast\Exception\TimeoutException;
use Voicast\Exception\ValidationException;
use Voicast\Exception\VoicastException;
use Voicast\Http\HttpClient;
use Voicast\Http\Response;
use Voicast\Version;

/** Retries, timeouts, error types and configuration. */
final class HttpTest extends TestCase
{
    private const LIST = ['data' => []];

    protected function tearDown(): void
    {
        putenv(Client::API_KEY_ENV);
    }

    /** @return array{0: ?\Throwable, 1: mixed} */
    private static function attempt(callable $fn): array
    {
        try {
            return [null, $fn()];
        } catch (\Throwable $e) {
            return [$e, null];
        }
    }

    public function testHeaders(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, self::LIST));
        FakeTransport::client($t)->agents->list();
        $h = $t->last()->headers;
        self::assertSame('Bearer vk_test_abc', $h['authorization']);
        self::assertSame('application/json', $h['accept']);
        self::assertSame('voicast-php/' . Version::VERSION, $h['user-agent']);
        self::assertArrayNotHasKey('content-type', $h);
        self::assertNull($t->last()->body);
        self::assertSame(30.0, $t->last()->timeout);
    }

    public function testBodyAndUnicode(): void
    {
        $t = new FakeTransport(FakeTransport::json(201, ['id' => 'ten_1']));
        FakeTransport::client($t)->tenants->create(['external_id' => 'clinic-1', 'name' => '○○歯科', 'unknown' => 'dropped']);
        self::assertSame('application/json', $t->last()->headers['content-type']);
        self::assertSame('{"external_id":"clinic-1","name":"○○歯科"}', $t->last()->body);
    }

    public function testUserAgentFormat(): void
    {
        self::assertMatchesRegularExpression('/^voicast-php\/\d+\.\d+\.\d+$/', Version::USER_AGENT);
    }

    public function testVersionMatchesChangelog(): void
    {
        self::assertStringContainsString('## ' . Version::VERSION, (string) file_get_contents(__DIR__ . '/../CHANGELOG.md'));
    }

    public function testApiKeyFromEnv(): void
    {
        putenv(Client::API_KEY_ENV . '= vk_live_env ');
        $t = new FakeTransport(FakeTransport::json(200, self::LIST));
        (new Client(null, ['transport' => $t]))->agents->list();
        self::assertSame('Bearer vk_live_env', $t->last()->headers['authorization']);
    }

    public function testMissingApiKey(): void
    {
        putenv(Client::API_KEY_ENV . '=  ');
        [$e] = self::attempt(fn () => new Client());
        self::assertInstanceOf(VoicastException::class, $e);
        self::assertStringContainsString(Client::API_KEY_ENV, $e->getMessage());
        [$e2] = self::attempt(fn () => new Client(''));
        self::assertInstanceOf(VoicastException::class, $e2);
    }

    public function testBaseUrlAndTimeout(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, self::LIST));
        $c = new Client('vk_test_x', ['transport' => $t]);
        self::assertSame('https://api.voicast.jp', $c->baseUrl);
        $c->agents->list();
        self::assertSame('https://api.voicast.jp/v1/agents', $t->last()->url);
        self::assertSame('http://localhost:8787', (new Client('vk_test_x', ['base_url' => 'http://localhost:8787//']))->baseUrl);
        (new Client('vk_test_x', ['transport' => $t, 'timeout' => 5]))->agents->list();
        self::assertSame(5.0, $t->last()->timeout);
        (new Client('vk_test_x', ['transport' => $t, 'timeout' => 5]))->agents->list(['timeout' => 1.5, 'unknown' => 1]);
        self::assertSame(1.5, $t->last()->timeout);
    }

    /** @return list<array{int, string, class-string}> */
    public static function statuses(): array
    {
        return [
            [400, 'bad_json', ValidationException::class],
            [401, 'unauthorized', AuthenticationException::class],
            [402, 'balance_depleted', PaymentRequiredException::class],
            [403, 'forbidden', PermissionException::class],
            [404, 'not_found', NotFoundException::class],
            [409, 'in_use', ConflictException::class],
            [413, 'too_large', ApiException::class],
            [418, 'teapot', ApiException::class],
            [422, 'invalid_request', ValidationException::class],
        ];
    }

    #[DataProvider('statuses')]
    public function testStatusToClass(int $status, string $code, string $class): void
    {
        $details = [['path' => 'name', 'message' => 'required']];
        $t = new FakeTransport(FakeTransport::json($status, ['error' => $code, 'message' => "m $status", 'details' => $details], ['cf-ray' => 'ray-1']));
        [$e] = self::attempt(fn () => FakeTransport::client($t)->sessions->create(['tenant_id' => 'ten_1']));
        self::assertInstanceOf($class, $e);
        self::assertInstanceOf(VoicastException::class, $e);
        self::assertSame([$status, $code, "m $status", 'ray-1', $details], [$e->getStatus(), $e->getErrorCode(), $e->getMessage(), $e->getRequestId(), $e->getDetails()]);
        self::assertSame([$status, $code, $details], [$e->status, $e->errorCode, $e->details]);
        self::assertCount(1, $t->calls);
    }

    public function testNonJsonErrorBodies(): void
    {
        $t = new FakeTransport(
            new Response(502, [], '<html>bad gateway</html>'),
            new Response(400, [], 'null'),
            new Response(500, ['cf-ray' => ''], '"oops"'),
            new Response(404, ['x-request-id' => 'req_1'], '{"error":1,"message":null}'),
        );
        $c = FakeTransport::client($t, ['max_retries' => 0]);
        foreach ([[502, ApiException::class, null], [400, ValidationException::class, null], [500, ApiException::class, null], [404, NotFoundException::class, 'req_1']] as [$status, $class, $rid]) {
            [$e] = self::attempt(fn () => $c->calls->get('c'));
            self::assertInstanceOf($class, $e);
            self::assertSame([$status, null, "HTTP $status", $rid, null], [$e->status, $e->errorCode, $e->getMessage(), $e->requestId, $e->details]);
        }
    }

    public function testInvalidJsonIn2xx(): void
    {
        $t = new FakeTransport(new Response(200, ['CF-Ray' => 'r2'], '{oops'));
        [$e] = self::attempt(fn () => FakeTransport::client($t)->calls->get('c'));
        self::assertInstanceOf(ApiException::class, $e);
        self::assertSame([200, 'r2'], [$e->status, $e->requestId]);
        self::assertStringContainsString('Invalid JSON', $e->getMessage());
        self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        self::assertCount(1, $t->calls);
    }

    public function testOtherTransportErrorsAreConnectionErrors(): void
    {
        $boom = new \RuntimeException('socket hang up');
        $t = new FakeTransport($boom);
        [$e] = self::attempt(fn () => FakeTransport::client($t, ['max_retries' => 0])->calls->get('c'));
        self::assertInstanceOf(ConnectionException::class, $e);
        self::assertNotInstanceOf(TimeoutException::class, $e);
        self::assertSame($boom, $e->getPrevious());
        self::assertSame('Connection error: socket hang up', $e->getMessage());
        self::assertNull($e->status);
    }

    public function testSdkExceptionsFromATransportAreNotWrapped(): void
    {
        $own = new PermissionException('custom');
        $t = new FakeTransport($own);
        [$e] = self::attempt(fn () => FakeTransport::client($t)->calls->get('c'));
        self::assertSame($own, $e);
        self::assertCount(1, $t->calls);
    }

    public function testConstructDirectly(): void
    {
        $e = new RateLimitException('slow');
        self::assertSame(['slow', null, null, null], [$e->getMessage(), $e->status, $e->retryAfter, $e->details]);
        self::assertSame(0, $e->getCode());
    }

    public function test5xxWithBackoffThenSuccess(): void
    {
        $s = new Sleeps();
        $t = new FakeTransport(FakeTransport::json(500, ['error' => 'internal', 'message' => 'x']), FakeTransport::json(503, []), FakeTransport::json(200, ['id' => 'call_1']));
        self::assertSame(['id' => 'call_1'], FakeTransport::client($t, [], $s)->calls->get('call_1'));
        self::assertCount(3, $t->calls);
        self::assertSame([0.5, 1.0], $s->waits);
    }

    public function testGivesUpAfterMaxRetries(): void
    {
        $s = new Sleeps();
        $t = new FakeTransport(FakeTransport::json(500, ['error' => 'internal', 'message' => 'down']));
        [$e] = self::attempt(fn () => FakeTransport::client($t, ['max_retries' => 3], $s)->calls->get('c'));
        self::assertInstanceOf(ApiException::class, $e);
        self::assertCount(4, $t->calls);
        self::assertSame([0.5, 1.0, 2.0], $s->waits);
    }

    public function testMaxRetriesPerRequest(): void
    {
        $t = new FakeTransport(FakeTransport::json(500, []));
        self::attempt(fn () => FakeTransport::client($t, ['max_retries' => 5])->calls->get('c', ['max_retries' => 0]));
        self::assertCount(1, $t->calls);
    }

    public function testConnectionErrorsRetriedForSafeRequests(): void
    {
        $t = new FakeTransport(new \RuntimeException('ECONNRESET'), FakeTransport::json(200, self::LIST));
        self::assertSame(self::LIST, FakeTransport::client($t)->tenants->list());
        self::assertCount(2, $t->calls);
    }

    public function testTimeoutRetriedForSafeRequests(): void
    {
        $t = new FakeTransport(new TimeoutException('Request timed out after 1 s'));
        [$e] = self::attempt(fn () => FakeTransport::client($t, ['max_retries' => 1])->calls->get('c'));
        self::assertInstanceOf(TimeoutException::class, $e);
        self::assertInstanceOf(ConnectionException::class, $e);
        self::assertCount(2, $t->calls);
    }

    public function testPatchAndLinksAreRetried(): void
    {
        $t = new FakeTransport(FakeTransport::json(502, []), FakeTransport::json(200, ['id' => 'ten_1']));
        FakeTransport::client($t)->tenants->update('ten_1', ['name' => 'x']);
        self::assertCount(2, $t->calls);
        $t = new FakeTransport(new ConnectionException('reset'), FakeTransport::json(201, ['url' => 'u', 'expires_at' => 1]));
        FakeTransport::client($t)->webhooks->createPortalLink();
        self::assertCount(2, $t->calls);
    }

    /** @return list<array{mixed}> */
    public static function unsafeFailures(): array
    {
        return [[FakeTransport::json(500, [])], [new \RuntimeException('ECONNRESET')], [new TimeoutException('t')]];
    }

    #[DataProvider('unsafeFailures')]
    public function testUnsafeRequestsAreNotRetried(mixed $reply): void
    {
        $t = new FakeTransport($reply);
        $c = FakeTransport::client($t);
        $ops = [
            fn () => $c->agents->create(['name' => 'x', 'config' => []]),
            fn () => $c->agents->update('a', ['config' => []]),
            fn () => $c->tenants->create(['external_id' => 'x']),
            fn () => $c->tenants->rotateSecret('t'),
            fn () => $c->sessions->create(['tenant_id' => 't']),
            fn () => $c->connections->create(['connector' => 'teams', 'secret' => 's']),
            fn () => $c->billing->createTopup(['amount_yen' => 500]),
            fn () => $c->tools->rotateSecret(),
            fn () => $c->tools->try(['agent_id' => 'a', 'tool' => 'x']),
        ];
        foreach ($ops as $op) {
            [$e] = self::attempt($op);
            self::assertInstanceOf(VoicastException::class, $e);
        }
        self::assertCount(count($ops), $t->calls);
    }

    public function test4xxNotRetried(): void
    {
        foreach ([400, 401, 402, 403, 404, 409, 422] as $s) {
            $t = new FakeTransport(FakeTransport::json($s, ['error' => 'x', 'message' => 'y']));
            self::attempt(fn () => FakeTransport::client($t)->calls->get('c'));
            self::assertCount(1, $t->calls);
        }
    }

    public function test429WaitsRetryAfterForAnyRequest(): void
    {
        $s = new Sleeps();
        $t = new FakeTransport(FakeTransport::json(429, ['error' => 'rate_limited', 'message' => 'slow'], ['retry-after' => '7']), FakeTransport::json(201, ['id' => 'ses_1']));
        self::assertSame(['id' => 'ses_1'], FakeTransport::client($t, [], $s)->sessions->create(['tenant_id' => 't']));
        self::assertSame([7.0], $s->waits);
    }

    public function test429WaitsUntilRateLimitReset(): void
    {
        $s = new Sleeps();
        $t = new FakeTransport(FakeTransport::json(429, ['error' => 'rate_limited', 'message' => 'x'], ['x-ratelimit-reset' => '1012']), FakeTransport::json(200, ['id' => 'c']));
        $c = FakeTransport::client($t, [], $s);
        $c->http->clock = static fn (): float => 1000.0;
        $c->calls->get('c');
        self::assertSame([12.0], $s->waits);
    }

    public function test429WithoutHeadersUsesBackoff(): void
    {
        $s = new Sleeps();
        $t = new FakeTransport(FakeTransport::json(429, ['error' => 'rate_limited', 'message' => 'x']), FakeTransport::json(200, ['id' => 'c']));
        FakeTransport::client($t, [], $s)->calls->get('c');
        self::assertSame([0.5], $s->waits);
    }

    public function test429ThrownWithRetryAfter(): void
    {
        $t = new FakeTransport(FakeTransport::json(429, ['error' => 'rate_limited', 'message' => 'x'], ['retry-after' => '1']));
        [$e] = self::attempt(fn () => FakeTransport::client($t, ['max_retries' => 1])->calls->get('c'));
        self::assertInstanceOf(RateLimitException::class, $e);
        self::assertSame([1.0, 'rate_limited'], [$e->retryAfter, $e->errorCode]);
        self::assertCount(2, $t->calls);
    }

    public function test429AtExactlyOneMinuteIsRetried(): void
    {
        $s = new Sleeps();
        $t = new FakeTransport(FakeTransport::json(429, [], ['retry-after' => '60']), FakeTransport::json(200, ['id' => 'c']));
        FakeTransport::client($t, [], $s)->calls->get('c');
        self::assertSame([60.0], $s->waits);
    }

    public function test429LongerThanAMinuteThrownAtOnce(): void
    {
        $s = new Sleeps();
        $t = new FakeTransport(FakeTransport::json(429, ['error' => 'rate_limited', 'message' => 'x'], ['retry-after' => '61']));
        [$e] = self::attempt(fn () => FakeTransport::client($t, [], $s)->calls->get('c'));
        self::assertInstanceOf(RateLimitException::class, $e);
        self::assertCount(1, $t->calls);
        self::assertSame([], $s->waits);
    }

    public function testDefaultSleepRandomAndClock(): void
    {
        $c = new Client('vk_test_x', ['transport' => new FakeTransport(FakeTransport::json(200, []))]);
        $start = microtime(true);
        ($c->http->sleep)(0.01);
        self::assertGreaterThanOrEqual(0.009, microtime(true) - $start);
        $r = ($c->http->random)();
        self::assertTrue($r >= 0 && $r <= 1);
        self::assertEqualsWithDelta(microtime(true), ($c->http->clock)(), 5);
    }

    public function testBackoff(): void
    {
        $half = static fn (): float => 0.5;
        self::assertSame([0.5, 1.0, 2.0, 4.0, 8.0, 8.0, 8.0], array_map(fn ($n) => HttpClient::backoff($n, $half), [0, 1, 2, 3, 4, 5, 10]));
        self::assertSame(0.375, HttpClient::backoff(0, static fn (): float => 0.0));
        self::assertSame(0.625, HttpClient::backoff(0, static fn (): float => 1.0));
    }

    public function testRateLimitWait(): void
    {
        $w = fn (array $h, float $now = 0.0) => HttpClient::rateLimitWait(new Response(429, $h), $now);
        self::assertSame(2.0, $w(['retry-after' => '2']));
        self::assertSame(0.4, $w(['retry-after' => '0.4']));
        self::assertSame(4.0, $w(['retry-after' => 'Thu, 01 Jan 1970 00:00:05 GMT'], 1));
        self::assertSame(0.0, $w(['retry-after' => 'Thu, 01 Jan 1970 00:00:05 GMT'], 9));
        self::assertSame(6.0, $w(['retry-after' => 'soon', 'x-ratelimit-reset' => '10'], 4));
        self::assertSame(0.0, $w(['retry-after' => '-3']));
        self::assertSame(0.0, $w(['x-ratelimit-reset' => '1'], 5));
        self::assertNull($w(['x-ratelimit-reset' => 'x']));
        self::assertNull($w(['x-ratelimit-reset' => '']));
        self::assertNull($w(['retry-after' => ' ']));
        self::assertNull($w([]));
        self::assertSame(60.0, HttpClient::MAX_RATE_LIMIT_WAIT);
    }

    public function testEmptyResponseIsNull(): void
    {
        $t = new FakeTransport(new Response(204));
        FakeTransport::client($t)->tenants->delete('ten_1');
        self::assertSame('DELETE', $t->last()->method);
    }

    public function testQueryValues(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, []));
        $c = FakeTransport::client($t);
        self::assertSame('https://api.test/p?a=true&b=false&c=1&e=x%20y&f=', $c->http->url('/p', ['a' => true, 'b' => false, 'c' => 1, 'd' => null, 'e' => 'x y', 'f' => '']));
    }
}
