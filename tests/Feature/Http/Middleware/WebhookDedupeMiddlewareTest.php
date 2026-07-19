<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\WebhookDedupeMiddleware;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Repositories\WebhookEventRepositoryContract;
use App\Payments\Infrastructure\Repositories\WebhookEventRepository;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Infrastructure\LaravelDbAdapter;
use App\Redis\Contracts\RedisConnectorContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use App\Shared\Support\SystemClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Feature tests for WebhookDedupeMiddleware — Layer 3 of 3-layer dedupe.
 *
 * Test strategy (mirrors IdempotencyMiddlewareTest from Layer 1):
 *   - Real `WebhookEventRepository` against the in-memory SQLite DB
 *     (via RefreshDatabase). This exercises the cold-path DB atomic
 *     reserve() primitive.
 *   - Mockery to fake `RedisConnectorContract` (no real Redis in test env).
 *   - Closure-based test routes that simulate webhook controllers.
 */
final class WebhookDedupeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRedis(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(RedisConnectorContract::class);
        $client = Mockery::mock();
        $mock->shouldReceive('connection')->byDefault()->andReturn($client);
        return $mock;
    }

    private function realWebhookRepo(): WebhookEventRepository
    {
        return new WebhookEventRepository(
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
        $mock->shouldReceive('integer')->andReturnUsing(
            fn (string $key, int $default) => $default
        );
        $mock->shouldReceive('get')->andReturnUsing(
            fn (string $key, mixed $default = null) => $default
        );
        return $mock;
    }

    private function bindMiddleware(\Mockery\MockInterface $redis, WebhookEventRepository $webhookRepo): void
    {
        $this->app->bind(WebhookDedupeMiddleware::class, function () use ($redis, $webhookRepo) {
            return new WebhookDedupeMiddleware(
                $redis,
                $this->config(),
                $webhookRepo,
                new SystemClock(),
            );
        });
    }

    private function registerTestRoutes(): void
    {
        // Razorpay webhook test route.
        Route::post('/razorpay', function () {
            return response()->json(['received' => true, 'provider' => 'razorpay'], 200);
        })->middleware('webhook-dedupe');

        // PayPal webhook test route.
        Route::post('/paypal', function () {
            return response()->json(['received' => true, 'provider' => 'paypal'], 200);
        })->middleware('webhook-dedupe');

        // Unknown webhook URL — no match.
        Route::post('/unknown', function () {
            return response()->json(['received' => true, 'provider' => 'unknown'], 200);
        })->middleware('webhook-dedupe');
    }

    public function testPassesThroughUnknownWebhookUrl(): void
    {
        $this->bindMiddleware($this->fakeRedis(), $this->realWebhookRepo());
        $this->registerTestRoutes();

        // POST to /unknown — providerFor() returns null → pass through.
        $response = $this->postJson('/unknown', ['x' => 1]);
        $response->assertOk();
        $response->assertJson(['provider' => 'unknown']);
    }

    public function testReturnsCachedResponseOnDuplicateWebhook(): void
    {
        $redis = $this->fakeRedis();
        $client = $redis->shouldReceive('connection')->andReturn(Mockery::mock());

        // First call: SETEX reserves (returns true).
        $client->shouldReceive('set')
            ->once()
            ->with('idem:webhook:razorpay:evt_dup_001', 'processing', ['NX', 'EX' => 604800])
            ->andReturn(true);

        // After controller runs, cache the response.
        $cachedPayload = json_encode([
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['received' => true, 'provider' => 'razorpay']),
            'cached_at' => '2026-07-19T00:00:00+00:00',
        ]);
        $client->shouldReceive('set')
            ->once()
            ->with('idem:webhook:razorpay:evt_dup_001', Mockery::any(), ['EX' => 604800])
            ->andReturnUsing(function ($key, $value) use ($cachedPayload) {
                return true;
            });

        // Second call: GET returns the cached payload.
        $client->shouldReceive('get')
            ->with('idem:webhook:razorpay:evt_dup_001')
            ->andReturn($cachedPayload);

        $this->bindMiddleware($redis, $this->realWebhookRepo());
        $this->registerTestRoutes();

        // First request — runs controller, caches response.
        $first = $this->postJson(
            '/razorpay',
            ['event' => 'payment.captured'],
            ['X-Razorpay-Event-Id' => 'evt_dup_001'],
        );
        $first->assertOk();
        $first->assertJson(['received' => true]);

        // Second request with SAME event ID — returns cached, no controller re-invocation.
        $second = $this->postJson(
            '/razorpay',
            ['event' => 'payment.captured'],
            ['X-Razorpay-Event-Id' => 'evt_dup_001'],
        );
        $second->assertOk();
        $second->assertHeader('X-Idempotency-Replay', 'true');
    }

    public function testPassesThroughWhenEventIdHeaderIsMissing(): void
    {
        $redis = $this->fakeRedis();
        // No SETEX or GET calls expected.
        $redis->shouldReceive('connection')->never();
        $this->bindMiddleware($redis, $this->realWebhookRepo());
        $this->registerTestRoutes();

        // POST without X-Razorpay-Event-Id header → pass through.
        $response = $this->postJson('/razorpay', ['event' => 'payment.captured']);
        $response->assertOk();
        $response->assertJson(['provider' => 'razorpay']);
    }

    public function testFallsThroughToDbWhenRedisUnreachable(): void
    {
        $redis = $this->fakeRedis();
        $client = $redis->shouldReceive('connection')->andReturn(Mockery::mock());
        // Redis SETEX throws — middleware catches and falls through to DB.
        $client->shouldReceive('set')
            ->andThrow(new \RuntimeException('Redis unreachable'));

        $this->bindMiddleware($redis, $this->realWebhookRepo());
        $this->registerTestRoutes();

        // First request: Redis throws, falls through to DB reserve() → true.
        $first = $this->postJson(
            '/razorpay',
            ['event' => 'payment.captured'],
            ['X-Razorpay-Event-Id' => 'redis-down-key'],
        );
        $first->assertOk();

        // Second request: Redis throws again, DB reserve() returns false
        // (duplicate). Middleware falls through to controller. Doctrine:
        // DB UNIQUE constraint is the source of truth; duplicate
        // processing at the application level is acceptable in the cold path.
        $second = $this->postJson(
            '/razorpay',
            ['event' => 'payment.captured'],
            ['X-Razorpay-Event-Id' => 'redis-down-key'],
        );
        $second->assertOk();
    }

    public function testDoesNotCacheFiveHundredResponses(): void
    {
        $redis = $this->fakeRedis();
        $client = $redis->shouldReceive('connection')->andReturn(Mockery::mock());
        $client->shouldReceive('set')
            ->with('idem:webhook:razorpay:err-key', 'processing', ['NX', 'EX' => 604800])
            ->andReturn(true);
        // The cache write should NOT be called (5xx is retryable).
        $client->shouldNotReceive('set');

        $this->bindMiddleware($redis, $this->realWebhookRepo());

        // Register a 500-returning route.
        Route::post('/razorpay-err', function () {
            return response()->json(['error' => 'internal'], 500);
        })->middleware('webhook-dedupe');

        $response = $this->postJson(
            '/razorpay-err',
            ['x' => 1],
            ['X-Razorpay-Event-Id' => 'err-key'],
        );
        $response->assertStatus(500);
        // Doctrine: 5xx is retryable; never cache so gateway can retry.
    }
}
