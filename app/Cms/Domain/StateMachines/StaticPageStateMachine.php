<?php

declare(strict_types=1);

namespace App\Cms\Domain\StateMachines;

use App\Cms\Domain\Enums\CmsTransitionEvent;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Exceptions\InvalidPageStateTransitionException;
use App\Payments\Domain\StateMachines\StateTransitionResult;
use DateTimeImmutable;

/**
 * Static Page lifecycle state machine.
 *
 * Pure-function class. Stateless, deterministic, no I/O. The sole
 * authority on valid transitions for the StaticPage aggregate root.
 *
 * Transition table (from cms-architecture.md §3.4.1):
 *
 *   DRAFT      + PAGE_PUBLISHED → PUBLISHED  (publish)
 *   DRAFT      + PAGE_ARCHIVED  → ARCHIVED   (archive draft)
 *   PUBLISHED  + PAGE_EDITED    → UPDATED    (admin edits)
 *   PUBLISHED  + PAGE_ARCHIVED  → ARCHIVED   (archive)
 *   UPDATED    + PAGE_PUBLISHED → PUBLISHED  (fold-back — D3 semantics)
 *   UPDATED    + PAGE_ARCHIVED  → ARCHIVED
 *   ARCHIVED   + PAGE_RESTORED  → DRAFT      (admin restores)
 *
 * Terminal state ARCHIVED refuses all normal-lifecycle events
 * (publish, edit, re-archive). The single privileged admin op
 * PAGE_RESTORED is permitted — see `allowedEvents()`.
 *
 * Timestamp correlation (§3.4.3):
 *   PUBLISHED → stamps published_at AND last_published_at (D3: equal)
 *   Other states → just updated_at (handled by entity's withChanges)
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.4
 */
final class StaticPageStateMachine
{
    /**
     * Compute the next state for a static page given a current state and an event.
     *
     * @param  array<string, mixed>  $context  Side-channel (currently unused; reserved for V2 fields)
     * @throws InvalidPageStateTransitionException
     */
    public function transition(
        StaticPageState $from,
        CmsTransitionEvent $event,
        array $context = [],
    ): StateTransitionResult {
        $allowed = $this->allowedEvents($from);
        if (! in_array($event, $allowed, true)) {
            $to = $this->targetFor($from, $event);
            throw InvalidPageStateTransitionException::invalidTransition($from, $to, $event->value);
        }

        $to = $this->targetFor($from, $event);
        $now = new DateTimeImmutable();

        $entityChanges = ['state' => $to->value];
        $timestampChanges = [];

        // PUBLISHED: stamp published_at + last_published_at (D3: equal in V1)
        if ($to === StaticPageState::PUBLISHED) {
            $timestampChanges['published_at'] = $now;
            $entityChanges['last_published_at'] = $now->format(DATE_ATOM);
        }

        return new StateTransitionResult(
            toState: $to,
            entityChanges: $entityChanges,
            timestampChanges: $timestampChanges,
        );
    }

    /**
     * Whether the transition would be accepted without computing the
     * full result bundle.
     */
    public function canTransition(
        StaticPageState $from,
        CmsTransitionEvent $event,
    ): bool {
        return in_array($event, $this->allowedEvents($from), true);
    }

    /**
     * @return list<StaticPageState>
     */
    public function allowedNext(StaticPageState $from): array
    {
        $events = $this->allowedEvents($from);
        $targets = [];
        foreach ($events as $event) {
            $targets[] = $this->targetFor($from, $event);
        }

        return array_values(array_unique($targets, SORT_REGULAR));
    }

    /**
     * @return list<CmsTransitionEvent>
     */
    private function allowedEvents(StaticPageState $from): array
    {
        return match ($from) {
            StaticPageState::DRAFT => [
                CmsTransitionEvent::PAGE_PUBLISHED,
                CmsTransitionEvent::PAGE_ARCHIVED,
            ],
            StaticPageState::PUBLISHED => [
                CmsTransitionEvent::PAGE_EDITED,
                CmsTransitionEvent::PAGE_ARCHIVED,
            ],
            StaticPageState::UPDATED => [
                CmsTransitionEvent::PAGE_PUBLISHED,
                CmsTransitionEvent::PAGE_ARCHIVED,
            ],
            StaticPageState::ARCHIVED => [
                // ARCHIVED accepts only the privileged admin restore op.
                // All normal-lifecycle events (publish, edit, archive)
                // are refused here. See allowedEvents() — the sole
                // authority on validity.
                CmsTransitionEvent::PAGE_RESTORED,
            ],
        };
    }

    private function targetFor(
        StaticPageState $from,
        CmsTransitionEvent $event,
    ): StaticPageState {
        $key = "{$from->value}|{$event->value}";
        $table = [
            // DRAFT +
            'draft|page_published' => StaticPageState::PUBLISHED,
            'draft|page_archived'  => StaticPageState::ARCHIVED,

            // PUBLISHED +
            'published|page_edited'   => StaticPageState::UPDATED,
            'published|page_archived' => StaticPageState::ARCHIVED,

            // UPDATED +
            'updated|page_published' => StaticPageState::PUBLISHED,  // D3 fold-back
            'updated|page_archived'  => StaticPageState::ARCHIVED,

            // ARCHIVED + PAGE_RESTORED → DRAFT (privileged admin restore op).
            'archived|page_restored' => StaticPageState::DRAFT,
        ];

        if (! isset($table[$key])) {
            throw InvalidPageStateTransitionException::invalidTransition($from, $from, $event->value);
        }

        return $table[$key];
    }
}