<?php

declare(strict_types=1);

namespace Tests\Unit\Cms\StateMachines;

use App\Cms\Domain\Enums\CmsTransitionEvent;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Exceptions\InvalidPageStateTransitionException;
use App\Cms\Domain\StateMachines\StaticPageStateMachine;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic tests for the StaticPage state machine.
 *
 * Doctrine: state machines are the SOLE authority on lifecycle
 * transitions. These tests pin the transition table from
 * cms-architecture.md §3.4.1 — any drift here is a contract breach.
 */
final class StaticPageStateMachineTest extends TestCase
{
    /**
     * DRAFT + PAGE_PUBLISHED → PUBLISHED; entityChanges stamps
     * `published_at` AND `last_published_at` per spec D3.
     */
    public function testHappyPath_PublishFromDraft(): void
    {
        $machine = new StaticPageStateMachine();
        $result = $machine->transition(
            StaticPageState::DRAFT,
            CmsTransitionEvent::PAGE_PUBLISHED,
        );

        $this->assertSame(StaticPageState::PUBLISHED, $result->toState());
        $this->assertSame('published', $result->entityChanges()['state']);

        $this->assertArrayHasKey('published_at', $result->timestampChanges());
        $this->assertArrayHasKey('last_published_at', $result->entityChanges());
    }

    /**
     * Invalid transitions throw InvalidPageStateTransitionException.
     * Representative case: PUBLISHED + PAGE_RESTORED has no entry in
     * the target table (line 152-153 of StaticPageStateMachine), so
     * the privilege-restored path is only reachable from ARCHIVED.
     */
    public function testInvalidTransition_RejectsRestoreFromPublished(): void
    {
        $machine = new StaticPageStateMachine();

        $this->expectException(InvalidPageStateTransitionException::class);
        $machine->transition(
            StaticPageState::PUBLISHED,
            CmsTransitionEvent::PAGE_RESTORED,
        );
    }

    /**
     * `canTransition()` exposes the same matrix as `transition()` but
     * without side effects. Locks in the allowed-events table from the
     * state machine lines 105-128.
     *
     * Each row is a tuple [from, expectedCanTransitionMap] so the
     * PHPUnit data provider binds positionally to the two method
     * parameters below.
     *
     * @return list<array{0: StaticPageState, 1: array<string, bool>}>
     */
    public static function transitionMatrix(): array
    {
        return [
            [StaticPageState::DRAFT, [
                'page_published' => true,
                'page_edited'    => false,
                'page_archived'  => true,
                'page_restored'  => false,
            ]],
            [StaticPageState::PUBLISHED, [
                'page_published' => false,
                'page_edited'    => true,
                'page_archived'  => true,
                'page_restored'  => false,
            ]],
            [StaticPageState::UPDATED, [
                'page_published' => true,
                'page_edited'    => false,
                'page_archived'  => true,
                'page_restored'  => false,
            ]],
            [StaticPageState::ARCHIVED, [
                'page_published' => false,
                'page_edited'    => false,
                'page_archived'  => false,
                'page_restored'  => true,
            ]],
        ];
    }

    /**
     * @dataProvider transitionMatrix
     *
     * @param  array<string, bool>  $expectations
     */
    public function testCanTransitionMatrixMatchesSpec(
        StaticPageState $from,
        array $expectations,
    ): void {
        $machine = new StaticPageStateMachine();

        $eventMap = [
            'page_published' => CmsTransitionEvent::PAGE_PUBLISHED,
            'page_edited'    => CmsTransitionEvent::PAGE_EDITED,
            'page_archived'  => CmsTransitionEvent::PAGE_ARCHIVED,
            'page_restored'  => CmsTransitionEvent::PAGE_RESTORED,
        ];

        foreach ($expectations as $eventName => $expected) {
            $actual = $machine->canTransition($from, $eventMap[$eventName]);
            $this->assertSame(
                $expected,
                $actual,
                "Transition {$from->value} + {$eventName} should be "
                .($expected ? 'allowed' : 'refused')
            );
        }
    }
}
