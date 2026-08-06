<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Campaigns;

use App\Campaigns\Contracts\CampaignDraftInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreCampaignRequest — validates admin "create new campaign" form
 * submissions and produces a CampaignDraftInput for the service layer.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - No state machine. State is validated against the allowed set
 *     [draft, active, completed] at this boundary; the service layer
 *     accepts whatever the controller hands it.
 *   - Slug uniqueness is enforced at the storage layer (partial unique
 *     index `campaigns_slug_live_idx`). This request validates the
 *     slug SHAPE; the controller catches DuplicateCampaignSlugException
 *     and re-renders the form with the slug-taken error attached.
 *   - Currency code is validated against the currencies table.
 *   - Target amount, dates are all nullable so a draft can be saved
 *     with minimal information. Active campaigns should have all
 *     three populated; that's a future concern, not Pass 2's.
 *
 * @see App\Campaigns\Services\CampaignAuthoringService
 */
final class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth + admin middleware already gate the route; this method
        // returning true is the FormRequest signal to proceed.
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
            'category' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'target_amount_minor' => ['nullable', 'integer', 'min:0'],
            'state' => ['required', 'string', Rule::in(['draft', 'active', 'completed'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_featured' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'cover_image_file_id' => ['nullable', 'string', 'size:26'],
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
            'category.regex' => 'Category must be lowercase letters, digits, hyphens, or underscores (no spaces).',
            'ends_at.after_or_equal' => 'End date must be on or after the start date.',
            'currency_code.exists' => 'Currency code must be one of the codes registered in the currencies table.',
        ];
    }

    public function toInput(): CampaignDraftInput
    {
        $validated = $this->validated();

        return new CampaignDraftInput(
            slug: (string) $validated['slug'],
            title: (string) $validated['title'],
            description: $validated['description'] ?? null,
            shortDescription: $validated['short_description'] ?? null,
            category: (string) $validated['category'],
            currencyCode: strtoupper((string) $validated['currency_code']),
            targetAmountMinor: isset($validated['target_amount_minor'])
                ? (int) $validated['target_amount_minor']
                : null,
            state: (string) $validated['state'],
            startsAt: $validated['starts_at'] ?? null,
            endsAt: $validated['ends_at'] ?? null,
            isFeatured: (bool) ($validated['is_featured'] ?? false),
            displayOrder: (int) ($validated['display_order'] ?? 0),
            coverImageFileId: $validated['cover_image_file_id'] ?? null,
            metadata: $validated['metadata'] ?? [],
            createdBy: (string) ($this->user()?->getKey() ?? ''),
        );
    }
}
