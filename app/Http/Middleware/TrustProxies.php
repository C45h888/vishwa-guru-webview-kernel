<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Nginx terminates TLS on the host and proxies via the published
     * 127.0.0.1:8000 mapping. From inside the container that connection
     * arrives from the Docker bridge gateway (e.g. 172.21.0.1), NOT from
     * 127.0.0.1, so a loopback-only trust never matches and
     * X-Forwarded-Proto is ignored. '*' is safe here because the
     * container's port is localhost-bound on the host: the only route
     * into the app is the host Nginx (plus compose-network peers, which
     * never issue HTTP requests).
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
