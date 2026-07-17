<?php

declare(strict_types=1);

namespace App\Runtime\Failure\Contracts;

use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;

/**
 * The single entry point for reporting failures into the runtime kernel.
 *
 * Doctrine: every failure in the system passes through this contract.
 * Domain modules (Payments, future Donations, etc.) call
 * `FailureReportingContract::report(...)` rather than instantiating
 * handlers directly. The contract IS the routing membrane.
 *
 * Verified by tests/Feature/Runtime/Failure/FailureReportingContractTest.php
 * — a static-analysis-flavored test that greps the codebase for direct
 * handler instantiations and fails if any are found outside FailureRouter.
 */
interface FailureReportingContract
{
    /**
     * Report a failure into the runtime kernel.
     *
     * The router walks the failure through its full lifecycle
     * (Observed → Resolved) by consulting the state machine at each
     * step, applying the inferred side effects (log + handler dispatch),
     * and returning the final transition result.
     *
     * @param  FailureRecord  $record  the immutable failure facts
     * @return FailureTransitionResult  the final state after the lifecycle completes
     */
    public function report(FailureRecord $record): FailureTransitionResult;
}