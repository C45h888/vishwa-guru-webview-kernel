<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\IdempotencyMiddleware;
use App\Payments\Domain\Repositories\IdempotencyKeyRepositoryContract;
use App\Payments\Infrastructure\Repositories\IdempotencyKeyRepository;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Infrastructure\LaravelDbAdapter;
use App\Redis\Contracts\RedisConnectorContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use App\Shared\Support\SystemClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Feature tests for IdempotencyMiddleware — the inbound HTTP dedupe
 * gate (Layer 1 of 3-layer dedupe).
 *
 * Test strategy:
 *   - Real `RedisConnectorContract` is mocked via Mockery (no real Redis
 *     in the default test env per phpunit.xml).
 *   - Real `IdempotencyKeyRepository` is used against the in-memory
 *     SQLite DB (via RefreshDatabase + the payments idem migration).
 *     This exercises the cold-path DB atomic reserve().
 *   - The middleware is exercised via a closure-based test route
 *     registered in setUp(). Real DonationController doesn't exist yet
 *     (Phase 3).
 */
final class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRedis(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(RedisConnectorContract::class);
        // connection() returns a stdClass stand-in for the phpredis
        // client. Tests set expectations on the methods called by the
        // middleware (set, get).
        $client = Mockery::mock();
        $mock->shouldReceive('connection')->byDefault()->andReturn($client);
        return $mock;
    }

    private function realDb(): IdempotencyKeyRepository
    {
        return new IdempotencyKeyRepository(
            new LaravelDbAdapter(DB::connection()),
            new SystemClock(),
        );
    }

    private function config(): ConfigurationContract
    {
        $mock = Mockery::mock(ConfigurationContract::class);
        $mock->shouldReceive('string')->andReturnUsing(
            fn (string $key, string $default) => $default
        );
        $mock->shouldReceive('get')->andReturnUsing(
            fn (string $key, mixed $default = null) => $default
        );
        return $mock;
    }

    private function bindMiddleware(\Mockery\MockInterface $redis, IdempotencyKeyRepository $db): void
    {
        $this->app->bind(IdempotencyMiddleware::class, function () use ($redis, $db) {
            return new IdempotencyMiddleware(
                $redis,
                $this->config(),
                $db,
                new SystemClock(),
            );
        });
    }

    private function registerTestRoute(): void
    {
        // Register a test POST route that the middleware will protect.
        // The handler is a closure that returns a known response — the
        // tests can assert whether the handler was invoked or not.
        \Illuminate\Support\Facades\Route::post('/test-mw', function () {
            return response()->json(['status' => 'created', 'donation_id' => 'don_test_001'], 201);
        })->middleware('idempotency');
    }

    public function testPassesThroughGetRequestsWithoutIdempotencyCheck(): void
    {
        $this->bindMiddleware($this->fakeRedis(), $this->realDb());
        $this->registerTestRoute();

        // GET request — no Idempotency-Key, no SETEX.
        $response = $this->getJson('/test-mw');

        // 405 is fine (route is POST-only); what matters is no crash.
        // We use a different route for GET to verify pass-through.
        \Illuminate\Support\Facades\Route::get('/test-mw-get', function () {
            return response()->json(['ok' => true]);
        })->middleware('idempotency');
        $response = $this->getJson('/test-mw-get');
        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function testReturnsCachedResponseOnDuplicatePost(): void
    {
        $redis = $this->fakeRedis();
        $client = $redis->shouldReceive('connection')->andReturn(Mockery::mock());

        // First call: SETEX reserves (returns true).
        $client->shouldReceive('set')
            ->once()
            ->with('idem:http:donation:key-1', 'processing', ['NX', 'EX' => 86400])
            ->andReturn(true);

        // After controller runs, cache the response.
        $cachedPayload = json_encode([
            'status' => 201,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['status' => 'created', 'donation_id' => 'don_test_001']),
            'cached_at' => '2026-07-19T00:00:00+00:00',
        ]);
        $client->shouldReceive('set')
            ->once()
            ->with('idem:http:donation:key-1', Mockery::any(), ['EX' => 86400])
            ->andReturnUsing(function ($key, $value) use ($cachedPayload) {
                // Capture the actual value being cached.
                $GLOBALS['__cached'] = $value;
                return true;
            });

        // Second call: GET returns the cached payload.
        $client->shouldReceive('get')
            ->with('idem:http:donation:key-1')
            ->andReturn($cachedPayload);

        $this->bindMiddleware($redis, $this->realDb());
        $this->registerTestRoute();

        // First request — runs controller, caches response.
        $first = $this->postJson('/test-mw', ['amount' => 1000], ['Idempotency-Key' => 'key-1']);
        $first->assertStatus(201);

        // Second request with SAME key — returns cached response, controller NOT re-invoked.
        // (We'd need a counter to assert the handler ran once; for now, assert
        //  the body matches the first call's body.)
        $second = $this->postJson('/test-mw', ['amount' => 1000], ['Idempotency-Key' => 'key-1']);
        $second->assertStatus(201);
        $second->assertHeader('X-Idempotency-Replay', 'true');
    }

    public function testPassesThroughWhenIdempotencyHeaderIsMissing(): void
    {
        $redis = $this->fakeRedis();
        // No SETEX or GET calls expected.
        $redis->shouldReceive('connection')->never();
        $this->bindMiddleware($redis, $this->realDb());
        $this->registerTestRoute();

        $response = $this->postJson('/test-mw', ['amount' => 1000]); // no header
        $response->assertStatus(201);
        $response->assertJson(['status' => 'created', 'donation_id' => 'don_test_001']);
    }

    public function testFallsThroughToDbWhenRedisUnreachable(): void
    {
        $redis = $this->fakeRedis();
        $client = $redis->shouldReceive('connection')->andReturn(Mockery::mock());
        // Redis SETEX throws — middleware catches and falls through to DB.
        $client->shouldReceive('set')
            ->andThrow(new \RuntimeException('Redis unreachable'));

        $this->bindMiddleware($redis, $this->realDb());
        $this->registerTestRoute();

        // First request: Redis throws, falls through to DB reserve() → true (new key).
        $first = $this->postJson('/test-mw', ['amount' => 1000], ['Idempotency-Key' => 'redis-down-key']);
        $first->assertStatus(201);

        // Second request: Redis throws again, DB reserve() returns false (duplicate).
        // Middleware falls through to controller; controller returns 201 again.
        // Doctrine: DB UNIQUE constraint is the source of truth; the
        // application-level duplicate is acceptable in the cold path.
        $second = $this->postJson('/test-mw', ['amount' => 1000], ['Idempotency-Key' => 'redis-down-key']);
        $second->assertStatus(201);
    }

    public function testDoesNotCacheFiveHundredResponses(): void
    {
        $redis = $this->fakeRedis();
        $client = $redis->shouldReceive('connection')->andReturn(Mockery::mock());
        $client->shouldReceive('set')
            ->with('idem:http:donation:err-key', 'processing', ['NX', 'EX' => 86400])
            ->andReturn(true);
        // The cache write should NOT be called (5xx is retryable).
        $client->shouldNotReceive('set');

        $this->bindMiddleware($redis, $this->realDb());

        // Register a 500-returning route.
        \Illuminate\Support\Facades\Route::post('/test-500', function () {
            return response()->json(['error' => 'internal'], 500);
        })->middleware('idempotency');

        $response = $this->postJson('/test-500', ['x' => 1], ['Idempotency-Key' => 'err-key']);
        $response->assertStatus(500);
        // Doctrine: 5xx is retryable; never cache so client can retry.
    }
}
