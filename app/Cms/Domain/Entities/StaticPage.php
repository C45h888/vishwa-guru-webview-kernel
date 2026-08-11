<?php

declare(strict_types=1);

namespace App\Cms\Domain\Entities;

use App\Cms\Domain\Enums\CmsTransitionEvent;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\StateMachines\StaticPageStateMachine;
use App\Cms\Domain\ValueObjects\AboutPageContent;
use App\Cms\Domain\ValueObjects\HeroBannerSlot;
use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Cms\Domain\ValueObjects\LegalPageContent;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Static Page aggregate root.
 *
 * Mirrors the `static_pages` table defined in
 * `database/schema-neon/V1-schema.sql` lines 257-280.
 *
 * Mutators are guarded. Status changes go through
 * `transitionTo()` with the StaticPageStateMachine. Direct mutation of
 * `state` via `withChanges()` is a programming error and throws.
 *
 * Hero banners live in a junction table; the `heroBannerSlots` field is
 * the denormalized in-memory view assembled by the repository when
 * loading a page. The actual mapping is in `hero_banner_pages`.
 *
 * body_html is the canonical render output cache. It is written by
 * StaticPageService on every state transition that produces publicly-
 * readable content (PUBLISHED, UPDATED); public reads do not re-render.
 *
 * homepage_content is a JSONB column whose value is parsed by
 * HomepageContentFactory (which validates the nested cms_media_assets
 * references) and carried here as a typed HomepageContent. Because
 * HomepageContent cannot self-validate without a database query, every
 * hydration through `fromRow()` must supply the already-validated typed
 * override. Direct callers that don't have a typed value pass null and
 * the entity treats a populated raw column as a programming error.
 *
 * legal_page_content follows the same doctrine: parsed by
 * LegalPageContentFactory and carried as a typed LegalPageContent.
 * The Legal aggregate has no cms_media_assets references today, so its
 * existence check is purely structural.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.3.1
 */
final class StaticPage implements EntityContract
{
    public const ENTITY_TYPE = 'static_page';

    /**
     * @param  list<HeroBannerSlot>  $heroBannerSlots
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly PageSlug $slug,
        private readonly string $title,
        private readonly ?string $metaDescription,
        private readonly PageBody $body,
        private readonly ?string $bodyHtml,
        private readonly SeoMetadata $seoMetadata,
        private readonly ?HomepageContent $homepageContent,
        private readonly ?AboutPageContent $aboutPageContent,
        private readonly ?LegalPageContent $legalPageContent,
        private readonly StaticPageState $state,
        private readonly bool $isHomepage,
        private readonly int $displayOrder,
        private readonly array $heroBannerSlots,
        private readonly ?DateTimeImmutable $publishedAt,
        private readonly ?DateTimeImmutable $lastPublishedAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt = null,
        private readonly ?string $createdBy = null,
        private readonly ?string $updatedBy = null,
    ) {
    }

    /**
     * Create a brand-new static page in DRAFT.
     */
    public static function draft(
        PageSlug $slug,
        string $title,
        ?string $metaDescription,
        PageBody $body,
        SeoMetadata $seoMetadata,
        bool $isHomepage,
        int $displayOrder,
        ?string $createdBy = null,
        ?EntityId $id = null,
        ?HomepageContent $homepageContent = null,
        ?AboutPageContent $aboutPageContent = null,
        ?LegalPageContent $legalPageContent = null,
    ): self {
        if (trim($title) === '') {
            throw new InvalidArgumentException('StaticPage title cannot be empty');
        }
        if ($displayOrder < 0) {
            throw new InvalidArgumentException("displayOrder cannot be negative (got {$displayOrder})");
        }

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            slug: $slug,
            title: $title,
            metaDescription: $metaDescription,
            body: $body,
            bodyHtml: '',
            seoMetadata: $seoMetadata,
            homepageContent: $homepageContent,
            aboutPageContent: $aboutPageContent,
            legalPageContent: $legalPageContent,
            state: StaticPageState::DRAFT,
            isHomepage: $isHomepage,
            displayOrder: $displayOrder,
            heroBannerSlots: [],
            publishedAt: null,
            lastPublishedAt: null,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: null,
            // Drive-by C: spec §9.3 — author defaults to "system" until
            // Phase 4 wires real actors. Columns are nullable in PG with
            // no DB DEFAULT, so the literal string lands at the entity
            // boundary rather than at INSERT time.
            createdBy: $createdBy ?? 'system',
            updatedBy: 'system',
        );
    }

    /**
     * Rehydrate from a database row.
     *
     * The homepage_content and about_page_content columns are JSONB and
     * cannot self-validate without a database query. Therefore the repository
     * supplies the already-validated typed VOs as the second and third
     * arguments. A populated raw column without a typed override is a
     * programming error: callers must go through HomepageContentFactory
     * and AboutPageContentFactory respectively.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(
        array $row,
        ?HomepageContent $homepageContent = null,
        ?AboutPageContent $aboutPageContent = null,
        ?LegalPageContent $legalPageContent = null,
    ): self {
        $required = ['id', 'slug', 'title', 'state', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("StaticPage row missing required key: {$key}");
            }
        }

        // body_json is a JSONB column; raw string from query() is already
        // a decoded array per PersistenceAdapterContract contract.
        $bodyArray = is_array($row['body_json'] ?? null)
            ? $row['body_json']
            : json_decode((string) ($row['body_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        $body = PageBody::fromArray(is_array($bodyArray) ? $bodyArray : []);

        $seoArray = is_array($row['seo_metadata'] ?? null)
            ? $row['seo_metadata']
            : json_decode((string) ($row['seo_metadata'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        $seo = SeoMetadata::fromArray(is_array($seoArray) ? $seoArray : []);

        // heroBannerSlots are not stored on the row; they are joined in
        // by the repository via the hero_banner_pages junction. Default
        // to empty array; the repository decorator populates it.
        $slots = [];
        if (isset($row['hero_banner_slots']) && is_array($row['hero_banner_slots'])) {
            foreach ($row['hero_banner_slots'] as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $slots[] = new HeroBannerSlot(
                    bannerId: EntityId::fromString((string) ($raw['banner_id'] ?? '')),
                    displayOrder: (int) ($raw['display_order'] ?? 0),
                    weight: (int) ($raw['weight'] ?? 100),
                );
            }
        }

        $rawHomepageContent = $row['homepage_content'] ?? null;
        $hasRawHomepageContent = $rawHomepageContent !== null
            && $rawHomepageContent !== ''
            && $rawHomepageContent !== '{}';
        if ($hasRawHomepageContent && $homepageContent === null) {
            throw new LogicException(
                'StaticPage row has populated homepage_content; callers must supply '
                .'the typed HomepageContent via HomepageContentFactory.'
            );
        }

        $rawAboutPageContent = $row['about_page_content'] ?? null;
        $hasRawAboutPageContent = $rawAboutPageContent !== null
            && $rawAboutPageContent !== ''
            && $rawAboutPageContent !== '{}';
        if ($hasRawAboutPageContent && $aboutPageContent === null) {
            throw new LogicException(
                'StaticPage row has populated about_page_content; callers must supply '
                .'the typed AboutPageContent via AboutPageContentFactory.'
            );
        }

        $rawLegalPageContent = $row['legal_page_content'] ?? null;
        $hasRawLegalPageContent = $rawLegalPageContent !== null
            && $rawLegalPageContent !== ''
            && $rawLegalPageContent !== '{}';
        if ($hasRawLegalPageContent && $legalPageContent === null) {
            throw new LogicException(
                'StaticPage row has populated legal_page_content; callers must supply '
                .'the typed LegalPageContent via LegalPageContentFactory.'
            );
        }

        return new self(
            id: EntityId::fromString((string) $row['id']),
            slug: new PageSlug((string) $row['slug']),
            title: (string) $row['title'],
            metaDescription: isset($row['meta_description']) ? (string) $row['meta_description'] : null,
            body: $body,
            bodyHtml: isset($row['body_html']) ? (string) $row['body_html'] : null,
            seoMetadata: $seo,
            homepageContent: $homepageContent,
            aboutPageContent: $aboutPageContent,
            legalPageContent: $legalPageContent,
            state: StaticPageState::from((string) $row['state']),
            isHomepage: (bool) ($row['is_homepage'] ?? false),
            displayOrder: (int) ($row['display_order'] ?? 0),
            heroBannerSlots: $slots,
            publishedAt: self::parseDate($row['published_at'] ?? null),
            lastPublishedAt: self::parseDate($row['last_published_at'] ?? null),
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
            deletedAt: self::parseDate($row['deleted_at'] ?? null),
            createdBy: isset($row['created_by']) ? (string) $row['created_by'] : null,
            updatedBy: isset($row['updated_by']) ? (string) $row['updated_by'] : null,
        );
    }

    public function id(): EntityId
    {
        return $this->id;
    }

    public function entityType(): string
    {
        return self::ENTITY_TYPE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'slug' => $this->slug->value(),
            'title' => $this->title,
            'meta_description' => $this->metaDescription,
            'body_json' => json_encode($this->body->toArray(), JSON_THROW_ON_ERROR),
            'body_html' => $this->bodyHtml,
            'seo_metadata' => json_encode($this->seoMetadata->toArray(), JSON_THROW_ON_ERROR),
            'homepage_content' => $this->homepageContent === null
                ? null
                : json_encode($this->homepageContent->toArray(), JSON_THROW_ON_ERROR),
            'about_page_content' => $this->aboutPageContent === null
                ? null
                : json_encode($this->aboutPageContent->toArray(), JSON_THROW_ON_ERROR),
            'legal_page_content' => $this->legalPageContent === null
                ? null
                : json_encode($this->legalPageContent->toArray(), JSON_THROW_ON_ERROR),
            'state' => $this->state->value,
            'is_homepage' => $this->isHomepage,
            'display_order' => $this->displayOrder,
            'published_at' => $this->publishedAt?->format(DATE_ATOM),
            'last_published_at' => $this->lastPublishedAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }

    /**
     * Narrow public-read summary (6 fields). Mirrors the fallback shape
     * already emitted by HomeController when no homepage CMS row exists,
     * and the StaticPageSummary TS interface declared in
     * resources/js/domains/cms/types.ts. Controllers hand this to Inertia
     * via `$rendered->page->toReadSummary()` instead of the full toArray()
     * (which carries 20+ fields including body_json, body_html, audit
     * timestamps, created_by/updated_by).
     *
     * @return array<string, mixed>
     */
    public function toReadSummary(): array
    {
        return [
            'id'              => $this->id->value(),
            'slug'            => $this->slug->value(),
            'title'           => $this->title,
            'meta_description' => $this->metaDescription,
            'state'           => $this->state->value,
            'is_homepage'     => $this->isHomepage,
        ];
    }

/**
 * Apply entity-level changes. State changes go through
 * transitionTo() with the state machine — direct mutation here is a
 * programming error.
 *
 * The typed homepageContent is carried through the round-trip so
 * that mutators (title, body, hero banners, state) do not silently
 * lose the homepage aggregate.
 *
 * @param  array<string, mixed>  $changes
 */
public function withChanges(array $changes): static
    {
        if (array_key_exists('state', $changes)) {
            throw new LogicException(
                'StaticPage::withChanges() cannot set state directly. Use transitionTo() with a StaticPageStateMachine.'
            );
        }

        $row = $this->toArray();
        $merged = array_merge($row, $changes);
        $merged['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($merged, $this->homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    /**
     * Apply a state-machine-validated transition. The machine is the
     * SOLE authority on whether the transition is valid. Returns a new
     * StaticPage instance reflecting the transition.
     *
     * @param  array<string, mixed>  $context
     */
    public function transitionTo(
        StaticPageStateMachine $machine,
        StaticPageState $to,
        array $context = [],
    ): self {
        $event = $this->eventForTarget($to);
        $result = $machine->transition($this->state, $event, $context);

        $row = $this->toArray();
        $row['state'] = $result->toState()->value;
        foreach ($result->entityChanges() as $field => $value) {
            $row[$field] = $value;
        }
        foreach ($result->timestampChanges() as $column => $ts) {
            $row[$column] = $ts->format(DATE_ATOM);
        }
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    /**
     * Best-effort event inference for the (from, to) target pair.
     * The table is small and unambiguous — no arm-ordering traps like
     * in Payment::eventForTarget.
     */
    private function eventForTarget(StaticPageState $target): CmsTransitionEvent
    {
        return match (true) {
            $target === StaticPageState::PUBLISHED => CmsTransitionEvent::PAGE_PUBLISHED,
            $target === StaticPageState::UPDATED   => CmsTransitionEvent::PAGE_EDITED,
            $target === StaticPageState::ARCHIVED  => CmsTransitionEvent::PAGE_ARCHIVED,
            $target === StaticPageState::DRAFT     => CmsTransitionEvent::PAGE_RESTORED,
            default => throw new LogicException(
                sprintf('No event inferred for transition %s -> %s', $this->state->value, $target->value)
            ),
        };
    }

    // ─── Business methods ────────────────────────────────────────────────

    public function markAsHomepage(): self
    {
        $row = $this->toArray();
        $row['is_homepage'] = true;
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    public function clearHomepage(): self
    {
        $row = $this->toArray();
        $row['is_homepage'] = false;
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    public function withBody(PageBody $body, ?string $newBodyHtml = null): self
    {
        $row = $this->toArray();
        $row['body_json'] = json_encode($body->toArray(), JSON_THROW_ON_ERROR);
        if ($newBodyHtml !== null) {
            $row['body_html'] = $newBodyHtml;
        }
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    public function withHomepageContent(?HomepageContent $homepageContent): self
    {
        $row = $this->toArray();
        $row['homepage_content'] = $homepageContent === null
            ? null
            : json_encode($homepageContent->toArray(), JSON_THROW_ON_ERROR);
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    public function withAboutPageContent(?AboutPageContent $aboutPageContent): self
    {
        $row = $this->toArray();
        $row['about_page_content'] = $aboutPageContent === null
            ? null
            : json_encode($aboutPageContent->toArray(), JSON_THROW_ON_ERROR);
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $aboutPageContent, $this->legalPageContent);
    }

    public function withLegalPageContent(?LegalPageContent $legalPageContent): self
    {
        $row = $this->toArray();
        $row['legal_page_content'] = $legalPageContent === null
            ? null
            : json_encode($legalPageContent->toArray(), JSON_THROW_ON_ERROR);
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $this->aboutPageContent, $legalPageContent);
    }

    public function attachHeroBanner(HeroBannerSlot $slot): self
    {
        $slots = $this->heroBannerSlots;
        // Replace if banner already attached
        $slots = array_values(array_filter(
            $slots,
            static fn (HeroBannerSlot $s) => ! $s->bannerId()->equals($slot->bannerId())
        ));
        $slots[] = $slot;
        usort($slots, static fn (HeroBannerSlot $a, HeroBannerSlot $b) => $a->displayOrder() <=> $b->displayOrder());

        $row = $this->toArray();
        $row['hero_banner_slots'] = array_map(
            static fn (HeroBannerSlot $s) => [
                'banner_id' => $s->bannerId()->value(),
                'display_order' => $s->displayOrder(),
                'weight' => $s->weight(),
            ],
            $slots,
        );
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    public function detachHeroBanner(EntityId $bannerId): self
    {
        $slots = array_values(array_filter(
            $this->heroBannerSlots,
            static fn (HeroBannerSlot $s) => ! $s->bannerId()->equals($bannerId)
        ));

        $row = $this->toArray();
        $row['hero_banner_slots'] = array_map(
            static fn (HeroBannerSlot $s) => [
                'banner_id' => $s->bannerId()->value(),
                'display_order' => $s->displayOrder(),
                'weight' => $s->weight(),
            ],
            $slots,
        );
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row, $this->homepageContent, $this->aboutPageContent, $this->legalPageContent);
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function slug(): PageSlug
    {
        return $this->slug;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function body(): PageBody
    {
        return $this->body;
    }

    public function bodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function seoMetadata(): SeoMetadata
    {
        return $this->seoMetadata;
    }

    public function homepageContent(): ?HomepageContent
    {
        return $this->homepageContent;
    }

    public function aboutPageContent(): ?AboutPageContent
    {
        return $this->aboutPageContent;
    }

    public function legalPageContent(): ?LegalPageContent
    {
        return $this->legalPageContent;
    }

    public function state(): StaticPageState
    {
        return $this->state;
    }

    public function isHomepage(): bool
    {
        return $this->isHomepage;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * @return list<HeroBannerSlot>
     */
    public function heroBannerSlots(): array
    {
        return $this->heroBannerSlots;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function lastPublishedAt(): ?DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?string
    {
        return $this->updatedBy;
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }
}
