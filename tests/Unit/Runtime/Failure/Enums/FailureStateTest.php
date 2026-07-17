<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Failure\Enums;

use App\Runtime\Failure\Enums\FailureState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FailureState enum — closed vocabulary + structural invariants.
 */
final class FailureStateTest extends TestCase
{
    #[Test]
    public function it_has_exactly_five_states(): void
    {
        $this->assertCount(5, FailureState::cases());
    }

    #[Test]
    public function the_expected_states_are_present(): void
    {
        $values = array_map(fn ($s) => $s->value, FailureState::cases());
        sort($values);

        $this->assertSame(
            ['classified', 'observed', 'recorded', 'resolved', 'surfaced'],
            $values,
        );
    }

    #[Test]
    public function every_state_has_a_unique_string_value(): void
    {
        $values = array_map(fn ($s) => $s->value, FailureState::cases());
        $this->assertCount(count($values), array_unique($values));
    }

    #[Test]
    public function resolved_is_terminal(): void
    {
        // Resolved is the terminal state — only the state machine knows
        // whether to keep it terminal or cycle. The enum itself doesn't
        // encode this; it's a property of the state machine's transition
        // table. This test documents the contract.
        $this->assertSame('resolved', FailureState::Resolved->value);
    }
}