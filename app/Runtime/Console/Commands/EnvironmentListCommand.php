<?php

declare(strict_types=1);

namespace App\Runtime\Console\Commands;

use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Runtime\Validation\EnvValidator;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * php artisan temple:env — print required-vs-present env matrix.
 *
 * Useful as a pre-deploy guard:
 *   php artisan temple:env || exit 1
 *
 * Exits non-zero when any required key is missing. Reports the failure
 * to the runtime FailureRouter for unified logging + classification.
 */
final class EnvironmentListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'temple:env';

    /**
     * @var string
     */
    protected $description = 'Print required-vs-present env-key matrix for the current environment.';

    public function __construct(
        private readonly EnvValidator $validator,
        private readonly EnvironmentContract $env,
        private readonly FailureReportingContract $failureRouter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->env->type();
        $keys = $this->validator->requiredKeys($type);
        $missing = $this->validator->missingKeys($type);

        $this->info("Required env keys for {$type->label()} environment:");
        $this->newLine();

        foreach ($keys as $key => $required) {
            if (! $required) {
                continue; // Skip optional keys to keep the list focused.
            }

            $status = in_array($key, $missing, true) ? '[MISSING]' : '[present]';
            $line = "  {$status} {$key}";

            if ($required && in_array($key, $missing, true)) {
                $line .= '  (required)';
            }

            $this->line($line);
        }

        $this->newLine();

        if ($missing !== []) {
            $this->error(sprintf(
                '%d required env %s missing. Run with no arguments for details.',
                count($missing),
                count($missing) === 1 ? 'key is' : 'keys are',
            ));

            $this->failureRouter->report(new FailureRecord(
                id: Identifier::generate(),
                kind: FailureKind::CommandFailed,
                origin: self::class,
                message: 'temple:env detected missing required env keys',
                previousState: FailureState::Observed,
                context: ['command' => 'temple:env', 'missing' => $missing],
                occurredAt: new DateTimeImmutable(),
            ));

            return self::FAILURE;
        }

        $this->info('All required env keys are present.');
        return self::SUCCESS;
    }
}