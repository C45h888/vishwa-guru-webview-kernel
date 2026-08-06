<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Events;

use App\Events\Contracts\EventDraftInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreEventRequest — validates admin "create new event" form
 * submissions and produces an EventDraftInput for the service layer.
 *
 * Doctrine (mirrors StoreCampaignRequest):
 *   - No state machine. State is validated against the allowed set
 *     [draft, published, completed] at this boundary.
 *   - Slug uniqueness is enforced at the storage layer (partial unique
 *     index `events_slug_live_idx`); the controller catches
 *     DuplicateEventSlugException and re-renders with the error.
 *   - starts_at is required (events have a non-null starts_at column).
 *   - ends_at, when present, must be after starts_at.
 *   - timezone is required but defaults to 'Asia/Kolkata' so the
 *     admin only edits it when overriding.
 *   - banner_file_id is optional (admin can publish without a banner).
 */
final class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'banner_file_id' => ['nullable', 'string', 'size:26'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['required', 'string', 'max:60'],
            'venue' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:1000'],
            'state' => ['required', 'string', Rule::in(['draft', 'published', 'completed'])],
            'is_featured' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug must be lowercase letters, digits, hyphens, or underscores (no spaces).',
            'ends_at.after_or_equal' => 'End date must be on or after the start date.',
            'starts_at.required' => 'Start date is required for any event.',
            'state.in' => 'State must be one of: draft, published, completed.',
        ];
    }

    public function toInput(): EventDraftInput
    {
        $validated = $this->validated();

        return new EventDraftInput(
            slug: (string) $validated['slug'],
            title: (string) $validated['title'],
            description: $validated['description'] ?? null,
            shortDescription: $validated['short_description'] ?? null,
            bannerFileId: $validated['banner_file_id'] ?? null,
            startsAt: $validated['starts_at'],
            endsAt: $validated['ends_at'] ?? null,
            timezone: (string) ($validated['timezone'] ?? 'Asia/Kolkata'),
            venue: $validated['venue'] ?? null,
            venueAddress: $validated['venue_address'] ?? null,
            state: (string) $validated['state'],
            isFeatured: (bool) ($validated['is_featured'] ?? false),
            displayOrder: (int) ($validated['display_order'] ?? 0),
            metadata: $validated['metadata'] ?? [],
            createdBy: (string) ($this->user()?->getKey() ?? ''),
        );
    }
}
