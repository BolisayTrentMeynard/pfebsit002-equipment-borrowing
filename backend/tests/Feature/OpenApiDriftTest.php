<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\ReminderNotification;
use App\Models\User;
use App\Services\MessageSigner;
use Database\Seeders\UatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Week 17, segment 1d: "drift" test. It fails when backend/openapi.yaml and the code disagree.
 *
 *  1. every api/* route is documented, and every documented operation has a route (same method, same path);
 *  2. the access rules match: bearer token or not, the roles, the digits-only ids, and the rate limits;
 *  3. the real answers of the key operations match the documented schema (keys, types, enums);
 *  4. the YAML itself is usable (version, every $ref resolves, every security scheme exists).
 *
 * Seeded with the UAT seeder (fake data). Nothing here needs MySQL. The failure messages name both sides, so a
 * rename in routes/api.php points straight at the line of the YAML that has to change.
 */
class OpenApiDriftTest extends TestCase
{
    use RefreshDatabase;

    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];
    private const ALL_ROLES = ['approver', 'borrower', 'staff'];
    private const KEY = 'drift-test-key-0123456789-abcdefghijklmnop-ONLY';

    private array $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doc = Yaml::parseFile(base_path('openapi.yaml'));
        config(['uat.password' => 'uat-fake-pass-1234']);
        // The contract describes the real setting (APP_DEBUG=false). With debug on, Laravel adds exception, file, line
        // and trace to abort() answers, so the test must not depend on what the local .env happens to say.
        config(['app.debug' => false]);
    }

    // =====================================================================================
    // 1. Routes and documented operations are the same set
    // =====================================================================================

    public function test_every_api_route_is_documented(): void
    {
        $missing = array_values(array_diff(array_keys($this->routeOperations()), array_keys($this->documentedOperations())));

        $this->assertSame([], $missing, "These routes exist in routes/api.php but are NOT in openapi.yaml:\n  ".implode("\n  ", $missing));
    }

    public function test_every_documented_operation_has_a_route(): void
    {
        $orphans = array_values(array_diff(array_keys($this->documentedOperations()), array_keys($this->routeOperations())));

        $this->assertSame([], $orphans, "These operations are in openapi.yaml but have NO route in routes/api.php:\n  ".implode("\n  ", $orphans));
    }

    public function test_there_are_sixteen_operations_and_their_ids_are_unique(): void
    {
        $this->assertCount(16, $this->routeOperations());
        $this->assertCount(16, $this->documentedOperations());

        $ids = array_column($this->documentedOperations(), 'operationId');
        $this->assertSame($ids, array_values(array_unique($ids)), 'operationId must be unique');
        $this->assertNotContains(null, $ids, 'every operation needs an operationId');
    }

    // =====================================================================================
    // 2. Access rules
    // =====================================================================================

    public function test_authentication_matches_between_routes_and_documentation(): void
    {
        $problems = [];
        foreach ($this->routeOperations() as $key => $route) {
            $doc = $this->documentedOperations()[$key] ?? null;
            if ($doc === null) {
                continue; // reported by the set tests above
            }
            $middleware = $route->gatherMiddleware();
            $isSignature = str_ends_with($key, ' /notifier/receive');

            $expected = in_array('api.auth', $middleware, true) ? ['bearerAuth'] : ($isSignature ? ['messageSignature'] : []);
            $actual = $this->securityNames($doc['op']);

            if ($actual !== $expected) {
                $problems[] = "$key: code needs [".implode(',', $expected)."], openapi.yaml says [".implode(',', $actual).']';
            }
        }

        $this->assertSame([], $problems, "Authentication drift:\n  ".implode("\n  ", $problems));
    }

    public function test_roles_match_between_routes_and_documentation(): void
    {
        $problems = [];
        foreach ($this->routeOperations() as $key => $route) {
            $doc = $this->documentedOperations()[$key] ?? null;
            if ($doc === null) {
                continue;
            }
            $middleware = $route->gatherMiddleware();

            $expected = [];
            if (in_array('api.auth', $middleware, true)) {
                $expected = self::ALL_ROLES;
                foreach ($middleware as $m) {
                    if (str_starts_with($m, 'role:')) {
                        $expected = explode(',', substr($m, 5));
                    }
                }
                sort($expected);
            }
            $actual = $doc['op']['x-allowed-roles'] ?? [];
            sort($actual);

            if ($actual !== $expected) {
                $problems[] = "$key: code allows [".implode(',', $expected)."], openapi.yaml x-allowed-roles says [".implode(',', $actual).']';
            }
        }

        $this->assertSame([], $problems, "Role drift:\n  ".implode("\n  ", $problems));
    }

    public function test_ids_in_paths_are_digits_only_in_code_and_documented_as_integers(): void
    {
        $problems = [];
        foreach ($this->routeOperations() as $key => $route) {
            if (! str_contains($key, '{}')) {
                continue;
            }
            foreach ($route->wheres as $param => $regex) {
                if ($regex !== '[0-9]+') {
                    $problems[] = "$key: route pattern for {{$param}} is $regex, expected [0-9]+";
                }
            }
            $this->assertNotSame([], $route->wheres, "$key: the route has no digits-only pattern for its id");

            $doc = $this->documentedOperations()[$key] ?? null;
            $idParam = $doc === null ? null : $this->resolve($doc['op']['parameters'][0] ?? []);
            if (($idParam['in'] ?? null) !== 'path' || ($idParam['schema']['type'] ?? null) !== 'integer') {
                $problems[] = "$key: openapi.yaml must document the path id as an integer";
            }
        }

        $this->assertSame([], $problems, "Id drift:\n  ".implode("\n  ", $problems));
    }

    public function test_rate_limited_routes_document_a_429_with_retry_after(): void
    {
        $problems = [];
        foreach ($this->routeOperations() as $key => $route) {
            $limited = collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'throttle:'));
            $doc = $this->documentedOperations()[$key] ?? null;
            if ($doc === null) {
                continue;
            }
            $has429 = isset($doc['op']['responses'][429]);

            if ($limited && ! $has429) {
                $problems[] = "$key: has a throttle in code but openapi.yaml has no 429";
            }
            if (! $limited && $has429 && $key !== 'POST /login') {
                $problems[] = "$key: documents a 429 but the route has no throttle";
            }
            if ($has429) {
                $this->assertArrayHasKey('Retry-After', $this->responseHeaders($doc['op']['responses'][429]), "$key: the 429 must document Retry-After");
            }
        }

        $this->assertSame([], $problems, "Rate-limit drift:\n  ".implode("\n  ", $problems));
    }

    // =====================================================================================
    // 3. Real answers match the documented schemas
    // =====================================================================================

    public function test_health_ready_answer_matches(): void
    {
        $this->assertDocumented($this->getJson('/api/health/ready'), 'GET', '/health/ready', 200);
    }

    public function test_login_and_me_and_equipment_answers_match(): void
    {
        $this->seed(UatSeeder::class);

        $login = $this->postJson('/api/login', ['email' => UatSeeder::BORROWER_1, 'password' => 'uat-fake-pass-1234']);
        $this->assertDocumented($login, 'POST', '/login', 200);

        $h = ['Authorization' => 'Bearer '.$login->json('token')];
        $this->assertDocumented($this->getJson('/api/me', $h), 'GET', '/me', 200);
        $this->assertDocumented($this->getJson('/api/equipment', $h), 'GET', '/equipment', 200);
        $this->assertDocumented($this->postJson('/api/logout', [], $h), 'POST', '/logout', 200);
    }

    public function test_the_request_family_answers_match_through_the_whole_workflow(): void
    {
        $this->seed(UatSeeder::class);
        $borrower = $this->as(UatSeeder::BORROWER_1);
        $approver = $this->as(UatSeeder::APPROVER);
        $staff = $this->as(UatSeeder::STAFF);

        // list and detail (the seeded data has every nullable field both set and empty)
        $this->assertDocumented($this->getJson('/api/requests', $borrower), 'GET', '/requests', 200);
        $this->assertDocumented($this->getJson('/api/requests?status=RELEASED', $approver), 'GET', '/requests', 200);
        $overdueId = $this->requestId('UAT-006');
        $this->assertDocumented($this->getJson("/api/requests/$overdueId", $approver), 'GET', '/requests/{id}', 200);

        // create
        $created = $this->postJson('/api/requests', [
            'equipment_id' => Equipment::where('asset_tag', 'UAT-001')->value('id'), 'reason' => 'Drift test request', 'requested_days' => 2,
        ], $borrower);
        $this->assertDocumented($created, 'POST', '/requests', 201);

        // approve, reject, release, return
        $this->assertDocumented($this->postJson('/api/requests/'.$created->json('data.id').'/approve', ['note' => 'ok'], $approver), 'POST', '/requests/{id}/approve', 200);
        $this->assertDocumented($this->postJson('/api/requests/'.$this->requestId('UAT-003').'/reject', [], $approver), 'POST', '/requests/{id}/reject', 200);
        $this->assertDocumented($this->postJson('/api/requests/'.$this->requestId('UAT-004').'/release', [], $staff), 'POST', '/requests/{id}/release', 200);
        $this->assertDocumented($this->postJson('/api/requests/'.$this->requestId('UAT-005').'/return', ['inspection_result' => 'good', 'inspection_notes' => 'Complete'], $staff), 'POST', '/requests/{id}/return', 200);
    }

    public function test_custody_and_manual_handling_answers_match(): void
    {
        $this->seed(UatSeeder::class);
        $approver = $this->as(UatSeeder::APPROVER);

        $this->assertDocumented($this->getJson('/api/custody', $this->as(UatSeeder::STAFF)), 'GET', '/custody', 200);
        $this->assertDocumented($this->getJson('/api/custody?overdue=1', $approver), 'GET', '/custody', 200);

        $list = $this->getJson('/api/manual-handling', $approver);
        $this->assertDocumented($list, 'GET', '/manual-handling', 200);
        $this->assertCount(1, $list->json('data'));

        $id = $list->json('data.0.id');
        $this->assertDocumented($this->postJson("/api/manual-handling/$id/resolve", ['note' => 'Called by phone'], $approver), 'POST', '/manual-handling/{id}/resolve', 200);
    }

    public function test_notifier_receive_answers_match(): void
    {
        config(['notifier.signing_key' => self::KEY, 'notifier.previous_signing_key' => null, 'notifier.tolerance_seconds' => 300]);
        Cache::flush();
        $body = '{"notification_id":12,"type":"overdue","request_id":6,"recipient_id":4,"attempt":1}';

        $signed = app(MessageSigner::class)->sign($body);
        $this->assertDocumented($this->receive($body, $signed), 'POST', '/notifier/receive', 202);
        $this->assertDocumented($this->receive($body, $signed), 'POST', '/notifier/receive', 409); // same id again: replay

        $bad = app(MessageSigner::class)->sign($body);
        $bad['signature'] = 'v1='.str_repeat('0', 64);
        $this->assertDocumented($this->receive($body, $bad), 'POST', '/notifier/receive', 401);

        $this->assertDocumented($this->receive($body, ['id' => 'not-a-uuid', 'timestamp' => time(), 'signature' => 'x']), 'POST', '/notifier/receive', 400);
    }

    public function test_the_shared_error_answers_match(): void
    {
        $this->seed(UatSeeder::class);
        $borrower = $this->as(UatSeeder::BORROWER_1);
        $approver = $this->as(UatSeeder::APPROVER);

        $this->assertDocumented($this->getJson('/api/me'), 'GET', '/me', 401);
        $this->assertDocumented($this->postJson('/api/login', ['email' => UatSeeder::STAFF, 'password' => 'wrong-password-xx']), 'POST', '/login', 401);
        $this->assertDocumented($this->postJson('/api/login', ['email' => 'not-an-email']), 'POST', '/login', 422);
        $this->assertDocumented($this->postJson('/api/requests/'.$this->requestId('UAT-003').'/approve', [], $borrower), 'POST', '/requests/{id}/approve', 403);
        $this->assertDocumented($this->getJson('/api/requests/'.$this->requestId('UAT-006'), $borrower), 'GET', '/requests/{id}', 403);
        $this->assertDocumented($this->getJson('/api/requests/999999', $approver), 'GET', '/requests/{id}', 404);
        $this->assertDocumented($this->postJson('/api/requests/'.$this->requestId('UAT-004').'/approve', [], $approver), 'POST', '/requests/{id}/approve', 409);
        $this->assertDocumented($this->postJson('/api/requests', ['equipment_id' => 1, 'reason' => 'x', 'requested_days' => 99], $borrower), 'POST', '/requests', 422);
        $this->assertDocumented($this->getJson('/api/custody?overdue=maybe', $approver), 'GET', '/custody', 422);
    }

    public function test_login_429_after_five_failed_tries_matches(): void
    {
        $this->seed(UatSeeder::class);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => UatSeeder::STAFF, 'password' => 'wrong-password-xx'])->assertStatus(401);
        }

        $this->assertDocumented($this->postJson('/api/login', ['email' => UatSeeder::STAFF, 'password' => 'wrong-password-xx']), 'POST', '/login', 429);
    }

    public function test_login_429_from_the_per_ip_limit_matches(): void
    {
        // Ten tries from one address, each with a different e-mail so the per-e-mail limit is not the one that stops it.
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/login', ['email' => "nobody{$i}@example.test", 'password' => 'wrong-password-xx'])->assertStatus(401);
        }

        $res = $this->postJson('/api/login', ['email' => 'nobody11@example.test', 'password' => 'wrong-password-xx']);
        $this->assertDocumented($res, 'POST', '/login', 429);
        $this->assertSame('Too Many Attempts.', $res->json('message'));
    }

    public function test_the_known_gap_an_unknown_api_path_has_no_correlation_id(): void
    {
        $res = $this->getJson('/api/nope');

        $res->assertStatus(404)->assertExactJson(['message' => 'Not found.']);
        $this->assertNull($res->headers->get('X-Correlation-ID'), 'documented as a known gap in openapi.yaml; if this fails, remove the gap sentence');
    }

    public function test_every_api_answer_has_the_security_headers_the_yaml_promises(): void
    {
        $this->seed(UatSeeder::class);

        foreach (['/api/health/ready', '/api/nope'] as $url) {
            $res = $this->getJson($url);
            $this->assertSame('no-store, private', $res->headers->get('Cache-Control'), $url);
            $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'), $url);
            $this->assertSame('no-referrer', $res->headers->get('Referrer-Policy'), $url);
            $this->assertFalse($res->headers->has('X-Powered-By'), $url);
        }
        $this->assertStringContainsString('no-store, private', $this->doc['info']['description']);
    }

    // =====================================================================================
    // 4. The YAML itself
    // =====================================================================================

    public function test_the_document_is_openapi_3_1_with_a_bearer_scheme_and_a_signature_scheme(): void
    {
        $this->assertStringStartsWith('3.1.', $this->doc['openapi']);
        $this->assertSame(['type' => 'http', 'scheme' => 'bearer'], array_intersect_key($this->doc['components']['securitySchemes']['bearerAuth'], ['type' => 1, 'scheme' => 1]));
        $this->assertSame('X-Message-Signature', $this->doc['components']['securitySchemes']['messageSignature']['name']);
        $this->assertSame([['bearerAuth' => []]], $this->doc['security']);
    }

    public function test_every_ref_resolves_and_every_security_name_exists(): void
    {
        $bad = [];
        array_walk_recursive($this->doc, function ($value, $key) use (&$bad) {
            if ($key === '$ref' && ! $this->pointerExists($value)) {
                $bad[] = $value;
            }
        });
        $this->assertSame([], $bad, "Unresolved \$ref:\n  ".implode("\n  ", $bad));

        foreach ($this->documentedOperations() as $key => $doc) {
            foreach ($this->securityNames($doc['op']) as $name) {
                $this->assertArrayHasKey($name, $this->doc['components']['securitySchemes'], "$key uses an unknown security scheme $name");
            }
        }
    }

    public function test_the_shared_error_responses_exist(): void
    {
        foreach (['Unauthenticated', 'Forbidden', 'NotFound', 'Conflict', 'ValidationFailed', 'TooManyRequests', 'ServerError', 'NotReady'] as $name) {
            $this->assertArrayHasKey($name, $this->doc['components']['responses']);
        }
    }

    // =====================================================================================
    // Helpers: routes and documentation
    // =====================================================================================

    /** @return array<string, Route> keyed "GET /requests/{}" (no /api prefix, parameter names removed) */
    private function routeOperations(): array
    {
        $ops = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $ops[$method.' '.$this->normalise(substr($route->uri(), 3))] = $route;
            }
        }
        ksort($ops);

        return $ops;
    }

    /** @return array<string, array{op: array, operationId: ?string}> keyed the same way as routeOperations() */
    private function documentedOperations(): array
    {
        $ops = [];
        foreach ($this->doc['paths'] as $path => $item) {
            foreach (self::METHODS as $method) {
                if (isset($item[$method])) {
                    $ops[strtoupper($method).' '.$this->normalise($path)] = ['op' => $item[$method], 'operationId' => $item[$method]['operationId'] ?? null];
                }
            }
        }
        ksort($ops);

        return $ops;
    }

    private function normalise(string $path): string
    {
        return preg_replace('/\{[^}]+\}/', '{}', $path);
    }

    /** @return string[] names of the security schemes that apply to an operation (its own list replaces the global one) */
    private function securityNames(array $op): array
    {
        $requirements = array_key_exists('security', $op) ? $op['security'] : $this->doc['security'];
        $names = [];
        foreach ($requirements as $requirement) {
            $names = array_merge($names, array_keys($requirement));
        }
        sort($names);

        return $names;
    }

    // =====================================================================================
    // Helpers: schema checking (a small checker for the parts of JSON Schema this file uses)
    // =====================================================================================

    private function assertDocumented(TestResponse $res, string $method, string $path, int $status): void
    {
        $this->assertSame($status, $res->getStatusCode(), "$method $path answered {$res->getStatusCode()}, the test expected $status: ".$res->getContent());

        $op = $this->doc['paths'][$path][strtolower($method)] ?? null;
        $this->assertNotNull($op, "$method $path is not in openapi.yaml");
        $this->assertArrayHasKey($status, $op['responses'], "$method $path: openapi.yaml does not document a $status answer");

        $response = $this->resolve($op['responses'][$status]);

        foreach ($this->responseHeaders($response) as $name => $_) {
            // The one header that is allowed to be absent is the correlation id of an unmatched path (known gap, tested above).
            $this->assertTrue($res->headers->has($name), "$method $path $status: documented header $name is missing from the real answer");
        }

        $schema = $response['content']['application/json']['schema'] ?? null;
        if ($schema === null) {
            return;
        }
        $errors = $this->validate($res->json(), $schema, '$');
        $this->assertSame([], $errors, "$method $path $status does not match openapi.yaml:\n  ".implode("\n  ", $errors)."\nBody: ".$res->getContent());
    }

    /** @return array<string, array> documented headers of a response (refs resolved), keyed by name */
    private function responseHeaders(array $response): array
    {
        return array_map(fn ($h) => $this->resolve($h), $this->resolve($response)['headers'] ?? []);
    }

    /** @return string[] problems, empty when $value fits $schema */
    private function validate(mixed $value, array $schema, string $at): array
    {
        $schema = $this->resolve($schema);

        if (isset($schema['allOf'])) {
            $merged = ['type' => 'object', 'properties' => [], 'required' => []];
            foreach ($schema['allOf'] as $part) {
                $part = $this->flatten($part);
                $merged['properties'] = array_merge($merged['properties'], $part['properties'] ?? []);
                $merged['required'] = array_merge($merged['required'], $part['required'] ?? []);
            }

            return $this->validate($value, $merged, $at);
        }

        if (isset($schema['oneOf'])) {
            $all = [];
            foreach ($schema['oneOf'] as $option) {
                $errors = $this->validate($value, $option, $at);
                if ($errors === []) {
                    return [];
                }
                $all = array_merge($all, $errors);
            }

            return ["$at fits none of the oneOf options (".implode('; ', $all).')'];
        }

        $types = (array) ($schema['type'] ?? (isset($schema['properties']) ? 'object' : []));
        if ($value === null) {
            return in_array('null', $types, true) ? [] : ["$at is null, but openapi.yaml does not allow null"];
        }
        if ($types !== [] && ! array_filter($types, fn ($t) => $this->matchesType($value, $t))) {
            return ["$at has the wrong type (".get_debug_type($value).'), openapi.yaml says '.implode('|', $types)];
        }
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return ["$at is ".json_encode($value).', not one of '.json_encode($schema['enum'])];
        }
        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            return ["$at is ".json_encode($value).', openapi.yaml says it is always '.json_encode($schema['const'])];
        }
        if (($schema['format'] ?? null) === 'date-time' && ! preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d/', (string) $value)) {
            return ["$at is not an ISO 8601 date-time"];
        }

        $errors = [];
        if (is_array($value) && isset($schema['properties'])) {
            foreach (array_keys($value) as $key) {
                if (! array_key_exists($key, $schema['properties'])) {
                    $errors[] = "$at.$key is returned but NOT documented in openapi.yaml";
                }
            }
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $value)) {
                    $errors[] = "$at.$key is documented as required but missing from the answer";
                }
            }
            foreach ($schema['properties'] as $key => $sub) {
                if (array_key_exists($key, $value)) {
                    $errors = array_merge($errors, $this->validate($value[$key], $sub, "$at.$key"));
                }
            }
        }
        if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
            foreach ($value as $i => $item) {
                $errors = array_merge($errors, $this->validate($item, $schema['items'], "{$at}[$i]"));
            }
        }

        return $errors;
    }

    private function matchesType(mixed $v, string $type): bool
    {
        return match ($type) {
            'integer' => is_int($v),
            'number' => is_int($v) || is_float($v),
            'string' => is_string($v),
            'boolean' => is_bool($v),
            'array' => is_array($v) && array_is_list($v),
            'object' => is_array($v) && ($v === [] || ! array_is_list($v)),
            'null' => $v === null,
            default => false,
        };
    }

    private function flatten(array $schema): array
    {
        $schema = $this->resolve($schema);
        if (! isset($schema['allOf'])) {
            return $schema;
        }
        $merged = ['properties' => [], 'required' => []];
        foreach ($schema['allOf'] as $part) {
            $part = $this->flatten($part);
            $merged['properties'] = array_merge($merged['properties'], $part['properties'] ?? []);
            $merged['required'] = array_merge($merged['required'], $part['required'] ?? []);
        }

        return $merged;
    }

    private function resolve(array $node): array
    {
        while (isset($node['$ref'])) {
            $node = $this->pointer($node['$ref']);
        }

        return $node;
    }

    private function pointer(string $ref): array
    {
        $this->assertTrue($this->pointerExists($ref), "Unresolved \$ref $ref");
        $node = $this->doc;
        foreach (explode('/', substr($ref, 2)) as $part) {
            $node = $node[str_replace(['~1', '~0'], ['/', '~'], $part)];
        }

        return $node;
    }

    private function pointerExists(string $ref): bool
    {
        if (! str_starts_with($ref, '#/')) {
            return false;
        }
        $node = $this->doc;
        foreach (explode('/', substr($ref, 2)) as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            if (! is_array($node) || ! array_key_exists($part, $node)) {
                return false;
            }
            $node = $node[$part];
        }

        return true;
    }

    // =====================================================================================
    // Helpers: data and calls
    // =====================================================================================

    private function as(string $email): array
    {
        return ['Authorization' => 'Bearer '.ApiToken::issue(User::where('email', $email)->firstOrFail()), 'Accept' => 'application/json'];
    }

    private function requestId(string $assetTag): int
    {
        return (int) BorrowRequest::whereHas('equipment', fn ($q) => $q->where('asset_tag', $assetTag))->value('id');
    }

    private function receive(string $body, array $signed): TestResponse
    {
        return $this->call('POST', '/api/notifier/receive', [], [], [], [
            'HTTP_X_MESSAGE_ID' => $signed['id'], 'HTTP_X_MESSAGE_TIMESTAMP' => (string) $signed['timestamp'],
            'HTTP_X_MESSAGE_SIGNATURE' => $signed['signature'], 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }
}
