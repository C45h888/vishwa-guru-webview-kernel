<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Events;

use App\Events\Contracts\EventUpdateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateEventRequest — validates admin "edit event" form submissions
 * and produces an EventUpdateInput for the service layer.
 *
 * Doctrine mirrors StoreEventRequest; the only difference is that the
 * slug uniqueness check excludes the row being edited.
 */
final class UpdateEventRequest extends FormRequest
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
        $editingId = (string) $this->route('event');

        return [
            'slug' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('events', 'slug')
                    ->ignore($editingId, 'id')
                    ->whereNull('deleted_at'),
            ],
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
            'slug.unique' => 'An event with this slug already exists.',
            'ends_at.after_or_equal' => 'End date must be on or after the start date.',
            'starts_at.required' => 'Start date is required for any event.',
            'state.in' => 'State must be one of: draft, published, completed.',
        ];
    }

    public function toInput(): EventUpdateInput
    {
        $validated = $this->validated();

        return new EventUpdateInput(
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
            updatedBy: (string) ($this->user()?->getKey() ?? ''),
        );
    }
}
