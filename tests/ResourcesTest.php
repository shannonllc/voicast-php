<?php

declare(strict_types=1);

namespace Voicast\Tests;

use PHPUnit\Framework\TestCase;
use Voicast\Exception\NotFoundException;
use Voicast\Page;

/** Each resource method: method, path, query, body and pagination. */
final class ResourcesTest extends TestCase
{
    private const CONFIG = ['greeting' => 'g', 'persona' => 'p', 'fields' => [], 'handoff_when' => [], 'closing' => 'c'];

    /** @return array{0: string, 1: string, 2: ?string} method, path with query, raw body */
    private static function raw(FakeTransport $t): array
    {
        $r = $t->last();
        $u = parse_url($r->url);
        return [$r->method, ($u['path'] ?? '') . (isset($u['query']) ? '?' . $u['query'] : ''), $r->body];
    }

    public function testAgents(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, ['id' => 'agt_1']));
        $c = FakeTransport::client($t);
        $c->agents->create(['name' => 'Reception', 'config' => self::CONFIG]);
        self::assertSame(['POST', '/v1/agents', ['name' => 'Reception', 'config' => self::CONFIG]], $t->lastCall());
        $c->agents->list();
        self::assertSame(['GET', '/v1/agents', null], $t->lastCall());
        $c->agents->get('agt_1');
        self::assertSame(['GET', '/v1/agents/agt_1', null], $t->lastCall());
        $c->agents->get('agt_1', ['version' => 3]);
        self::assertSame(['GET', '/v1/agents/agt_1?version=3', null], $t->lastCall());
        $c->agents->update('agt_1', ['config' => self::CONFIG]);
        self::assertSame(['PUT', '/v1/agents/agt_1', ['config' => self::CONFIG]], $t->lastCall());
        $c->agents->update('agt_1', ['name' => 'New', 'config' => self::CONFIG]);
        self::assertSame(['name' => 'New', 'config' => self::CONFIG], $t->lastCall()[2]);
        $c->agents->versions('agt_1');
        self::assertSame(['GET', '/v1/agents/agt_1/versions', null], $t->lastCall());
        $c->agents->delete('agt_1');
        self::assertSame(['DELETE', '/v1/agents/agt_1', null], $t->lastCall());
        $c->agents->get('a/b?c d');
        self::assertSame('https://api.test/v1/agents/a%2Fb%3Fc%20d', $t->last()->url);
    }

    public function testTenants(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, ['id' => 'ten_1']));
        $c = FakeTransport::client($t);
        $c->tenants->create(['external_id' => 'clinic-1']);
        self::assertSame(['POST', '/v1/tenants', ['external_id' => 'clinic-1']], $t->lastCall());
        $c->tenants->create(['external_id' => 'clinic-1', 'name' => 'n', 'agent_id' => 'agt_1']);
        self::assertSame(['external_id' => 'clinic-1', 'name' => 'n', 'agent_id' => 'agt_1'], $t->lastCall()[2]);
        $c->tenants->list();
        self::assertSame(['GET', '/v1/tenants', null], $t->lastCall());
        $c->tenants->get('ten_1');
        self::assertSame(['GET', '/v1/tenants/ten_1', null], $t->lastCall());
        $c->tenants->update('ten_1', ['status' => 'disabled']);
        self::assertSame(['PATCH', '/v1/tenants/ten_1', ['status' => 'disabled']], $t->lastCall());
        $c->tenants->update('ten_1', ['name' => 'x', 'agent_id' => null]);
        self::assertSame('{"name":"x","agent_id":null}', $t->last()->body);
        $c->tenants->update('ten_1', []);
        self::assertSame('{}', $t->last()->body);
        $c->tenants->rotateSecret('ten_1');
        self::assertSame(['POST', '/v1/tenants/ten_1/secret', null], $t->lastCall());
        $c->tenants->delete('ten_1');
        self::assertSame(['DELETE', '/v1/tenants/ten_1', null], $t->lastCall());
        $c->tenants->listConnections('ten_1');
        self::assertSame(['GET', '/v1/tenants/ten_1/connections', null], $t->lastCall());
        $c->tenants->createConnectLink('ten_1');
        self::assertSame(['POST', '/v1/tenants/ten_1/connect-links', '{}'], self::raw($t));
        $c->tenants->createConnectLink('ten_1', ['connectors' => ['google_calendar'], 'frame_origin' => 'https://saas.example']);
        self::assertSame('{"connectors":["google_calendar"],"frame_origin":"https://saas.example"}', $t->last()->body);
        $c->tenants->createSipCredential('ten_1');
        self::assertSame(['POST', '/v1/tenants/ten_1/sip-credentials', '{}'], self::raw($t));
        $c->tenants->createSipCredential('ten_1', ['name' => '本院の PBX', 'agent_id' => 'agt_1']);
        self::assertSame(['POST', '/v1/tenants/ten_1/sip-credentials', ['name' => '本院の PBX', 'agent_id' => 'agt_1']], $t->lastCall());
        $c->tenants->createSipCredential('ten_1', ['agent_id' => null]);
        self::assertSame('{"agent_id":null}', $t->last()->body);
        $c->tenants->listSipCredentials('ten_1');
        self::assertSame(['GET', '/v1/tenants/ten_1/sip-credentials', null], $t->lastCall());
        $c->tenants->deleteSipCredential('ten_1', 'sipc_1');
        self::assertSame(['DELETE', '/v1/tenants/ten_1/sip-credentials/sipc_1', null], $t->lastCall());
        $c->tenants->deleteSipCredential('ten/1', 'sipc/1');
        self::assertSame('https://api.test/v1/tenants/ten%2F1/sip-credentials/sipc%2F1', $t->last()->url);
    }

    public function testCalls(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, ['id' => 'call_1']));
        $c = FakeTransport::client($t);
        $c->calls->get('call_1');
        self::assertSame(['GET', '/v1/calls/call_1', null], $t->lastCall());
        $c->calls->getByProvider('CA123');
        self::assertSame(['GET', '/v1/calls/by-provider/CA123', null], $t->lastCall());
        $c->calls->transcript('call_1');
        self::assertSame(['GET', '/v1/calls/call_1/transcript', null], $t->lastCall());
    }

    /** @param list<string> $ids */
    private static function page(array $ids, bool $hasMore, int $base): \Voicast\Http\Response
    {
        $data = [];
        foreach ($ids as $i => $id) {
            $data[] = ['id' => $id, 'started_at' => $base - $i];
        }
        return FakeTransport::json(200, ['data' => $data, 'has_more' => $hasMore]);
    }

    public function testPagination(): void
    {
        $t = new FakeTransport(self::page(['a', 'b'], true, 1000), self::page(['c', 'd'], true, 900), self::page(['e'], false, 800));
        $page = FakeTransport::client($t)->calls->list(['tenant_id' => 'ten_1', 'limit' => 2]);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame(['a', 'b'], array_column($page->data, 'id'));
        self::assertTrue($page->hasMore);
        self::assertTrue($page->hasNextPage());
        self::assertSame(999, $page->nextCursor());
        self::assertCount(1, $t->calls);
        $ids = [];
        foreach ($page as $call) {
            $ids[] = $call['id'];
        }
        self::assertSame(['a', 'b', 'c', 'd', 'e'], $ids);
        self::assertSame(
            ['tenant_id=ten_1&limit=2', 'tenant_id=ten_1&before=999&limit=2', 'tenant_id=ten_1&before=899&limit=2'],
            array_map(fn ($r) => (string) parse_url($r->url, PHP_URL_QUERY), $t->calls),
        );
    }

    public function testPaginationStartsBeforeAndStopsOnAnEmptyPage(): void
    {
        $t = new FakeTransport(self::page(['x'], true, 500), FakeTransport::json(200, ['data' => [], 'has_more' => true]));
        $page = FakeTransport::client($t)->calls->list(['before' => 12345]);
        $ids = [];
        foreach ($page as $call) {
            $ids[] = $call['id'];
        }
        self::assertSame(['x'], $ids);
        self::assertSame(['before=12345', 'before=500'], array_map(fn ($r) => (string) parse_url($r->url, PHP_URL_QUERY), $t->calls));
        self::assertCount(2, iterator_to_array($page->pages(), false));
    }

    public function testLastPageAndMissingFields(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, []));
        $page = FakeTransport::client($t)->calls->list();
        self::assertSame([[], false, false, null, null], [$page->data, $page->hasMore, $page->hasNextPage(), $page->nextCursor(), $page->nextPage()]);
        self::assertSame('https://api.test/v1/calls', $t->last()->url);
    }

    public function testPaginationError(): void
    {
        $t = new FakeTransport(self::page(['x'], true, 500), FakeTransport::json(404, ['error' => 'not_found', 'message' => 'gone']));
        $page = FakeTransport::client($t, ['max_retries' => 0])->calls->list();
        $got = [];
        try {
            foreach ($page as $call) {
                $got[] = $call['id'];
            }
            self::fail('no error');
        } catch (NotFoundException) {
            self::assertSame(['x'], $got);
        }
    }

    public function testSessionsUsageConnectorsWebhooks(): void
    {
        $t = new FakeTransport(FakeTransport::json(201, ['id' => 'ses_1']));
        $c = FakeTransport::client($t);
        $c->sessions->create(['tenant_id' => 'ten_1']);
        self::assertSame(['POST', '/v1/sessions', ['tenant_id' => 'ten_1']], $t->lastCall());
        $c->sessions->create(['tenant_id' => 'ten_1', 'agent_id' => 'agt_1', 'output' => 'text']);
        self::assertSame(['tenant_id' => 'ten_1', 'agent_id' => 'agt_1', 'output' => 'text'], $t->lastCall()[2]);
        $c->usage->get();
        self::assertSame(['GET', '/v1/usage', null], $t->lastCall());
        $c->usage->get(['from' => '2026-10-01', 'to' => 1790000000000, 'group_by' => 'tenant']);
        self::assertSame(['GET', '/v1/usage?from=2026-10-01&to=1790000000000&group_by=tenant', null], $t->lastCall());
        $c->usage->get(['group_by' => 'tenant', 'channel' => 'phone', 'tenant_id' => 'ten_1', 'external_id' => 'clinic-1']);
        self::assertSame(['GET', '/v1/usage?group_by=tenant&channel=phone&tenant_id=ten_1&external_id=clinic-1', null], $t->lastCall());
        $c->connectors->list();
        self::assertSame(['GET', '/v1/connectors', null], $t->lastCall());
        $c->webhooks->createPortalLink();
        self::assertSame(['POST', '/v1/webhooks/portal', '{}'], self::raw($t));
        $c->webhooks->createPortalLink(['frame_origin' => 'https://saas.example']);
        self::assertSame('{"frame_origin":"https://saas.example"}', $t->last()->body);
    }

    public function testConnections(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, ['id' => 'con_1']));
        $c = FakeTransport::client($t);
        $c->connections->list();
        self::assertSame('https://api.test/v1/connections', $t->last()->url);
        $c->connections->list(['tenant_id' => null]);
        self::assertSame('https://api.test/v1/connections?tenant_id=', $t->last()->url);
        $c->connections->list(['tenant_id' => 'ten_1']);
        self::assertSame('https://api.test/v1/connections?tenant_id=ten_1', $t->last()->url);
        $c->connections->create(['connector' => 'teams', 'secret' => 'https://x.logic.azure.com/y']);
        self::assertSame(['POST', '/v1/connections', ['connector' => 'teams', 'secret' => 'https://x.logic.azure.com/y']], $t->lastCall());
        $c->connections->create(['connector' => 'http', 'secret' => 'k', 'input' => [], 'tenant_id' => 't']);
        self::assertSame('{"connector":"http","secret":"k","input":{},"tenant_id":"t"}', $t->last()->body);
        $c->connections->get('con_1');
        self::assertSame(['GET', '/v1/connections/con_1', null], $t->lastCall());
        $c->connections->update('con_1', ['config' => ['calendar_id' => 'primary']]);
        self::assertSame(['PATCH', '/v1/connections/con_1', ['config' => ['calendar_id' => 'primary']]], $t->lastCall());
        $c->connections->update('con_1', ['config' => []]);
        self::assertSame('{"config":{}}', $t->last()->body);
        $c->connections->options('con_1');
        self::assertSame(['GET', '/v1/connections/con_1/options', null], $t->lastCall());
        $c->connections->options('con_1', ['spreadsheet' => 's1', 'sheet' => null]);
        self::assertSame(['GET', '/v1/connections/con_1/options?spreadsheet=s1', null], $t->lastCall());
        $c->connections->delete('con_1');
        self::assertSame(['DELETE', '/v1/connections/con_1', null], $t->lastCall());
    }

    public function testBillingAndTools(): void
    {
        $t = new FakeTransport(FakeTransport::json(200, ['balance' => ['yen' => 1200]]));
        $c = FakeTransport::client($t);
        self::assertSame(1200, $c->billing->get()['balance']['yen']);
        self::assertSame(['GET', '/v1/billing', null], $t->lastCall());
        $c->billing->createTopup(['amount_yen' => 5000]);
        self::assertSame(['POST', '/v1/billing/topups', ['amount_yen' => 5000]], $t->lastCall());
        $c->retention->get();
        self::assertSame(['GET', '/v1/retention', null], $t->lastCall());
        $c->retention->update(['retention_days' => 7, 'x' => 1]);
        self::assertSame(['PATCH', '/v1/retention', ['retention_days' => 7]], $t->lastCall());
        $c->tools->getSecret();
        self::assertSame(['GET', '/v1/tools/secret', null], $t->lastCall());
        $c->tools->rotateSecret();
        self::assertSame(['POST', '/v1/tools/secret/rotate', null], $t->lastCall());
        $c->tools->try(['agent_id' => 'agt_1', 'tool' => 'find_slots', 'arguments' => ['date' => '2026-10-05'], 'tenant_id' => 'ten_1', 'x' => 1]);
        self::assertSame(['POST', '/v1/tools/try', ['agent_id' => 'agt_1', 'tool' => 'find_slots', 'arguments' => ['date' => '2026-10-05'], 'tenant_id' => 'ten_1']], $t->lastCall());
        $tool = ['name' => 'lookup', 'description' => 'd', 'url' => 'https://api.example.com/x', 'method' => 'GET', 'sign' => false, 'auth' => ['type' => 'api_key', 'header' => 'Authorization', 'value' => 'sk_123456789012']];
        $c->tools->try(['tool' => $tool, 'arguments' => []]);
        self::assertSame('{"tool":{"name":"lookup","description":"d","url":"https://api.example.com/x","method":"GET","sign":false,"auth":{"type":"api_key","header":"Authorization","value":"sk_123456789012"}},"arguments":{}}', $t->last()->body);
    }
}
