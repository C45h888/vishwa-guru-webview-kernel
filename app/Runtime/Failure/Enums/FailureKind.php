<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Enums;

/**
 * Closed vocabulary of failure categories the Runtime layer recognizes.
 *
 * Each FailureKind drives the state machine's inference:
 *   - handler class to dispatch
 *   - default log level
 *   - user-facing message template
 *   - whether the failure is a candidate for coalescing
 *
 * Doctrine: a new FailureKind case is added here only when a failure
 * mode appears that no existing case covers. Adding a case = updating
 * the state machine tables + the unit test that asserts every case has
 * the four inference outputs.
 */
enum FailureKind: string
{
    case BootEnvMissing          = 'boot.env_missing';
    case PersistenceBindingFailed = 'persistence.binding_failed';
    case PersistenceQueryFailed   = 'persistence.query_failed';
    case ProbeSubsystemDown       = 'probe.subsystem_down';
    case HttpUnhandled            = 'http.unhandled';
    case CommandFailed            = 'command.failed';
    case FrameworkException       = 'framework.exception';
}