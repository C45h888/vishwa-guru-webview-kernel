<?php

declare(strict_types=1);

namespace App\Runtime\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/ping — liveness probe.
 *
 * Returns 200 + minimal JSON. No subsystem checks. No dependencies.
 * MUST work even when DB / cache / queue are unreachable — that is
 * precisely the failure mode it exists to detect.
 *
 * Doctrine distinction:
 *   /health       = readiness probe (subsystems are usable)
 *   /api/v1/ping  = liveness probe (the PHP process is serving HTTP)
 *
 * Follows the Kubernetes / Rails / ASP.NET pattern.
 */
final class PingController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'ping' => 'pong',
            'v'    => '1',
        ], 200);
    }
}