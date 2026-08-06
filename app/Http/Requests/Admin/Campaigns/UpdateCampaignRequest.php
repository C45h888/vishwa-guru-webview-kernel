<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Campaigns;

use App\Campaigns\Contracts\CampaignUpdateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateCampaignRequest — validates admin "edit campaign" form
 * submissions and produces a CampaignUpdateInput for the service layer.
 *
 * Doctrine mirrors StoreCampaignRequest — see that file's docblock
 * for the full rationale. The only difference: this request also
 * validates that the slug is unique AMONG OTHER ROWS (i.e. excludes
 * the row being edited), since the partial unique index would
 * otherwise trip on a no-op save.
 *
 * The current row's id is passed via the route binding; we read it
 * out of the route inside the rules() callback so the same FormRequest
 * works for both the create and edit flows.
 */
final class UpdateCampaignRequest extends FormRequest
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
        $editingId = (string) $this->route('campaign');

        return [
            'slug' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                // Slug uniqueness excluding the row being edited.
                Rule::unique('campaigns', 'slug')
                    ->ignore($editingId, 'id')
                    ->whereNull('deleted_at'),
            ],
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
            'slug.unique' => 'A campaign with this slug already exists.',
            'category.regex' => 'Category must be lowercase letters, digits, hyphens, or underscores (no spaces).',
            'ends_at.after_or_equal' => 'End date must be on or after the start date.',
            'currency_code.exists' => 'Currency code must be one of the codes registered in the currencies table.',
        ];
    }

    public function toInput(): CampaignUpdateInput
    {
        $validated = $this->validated();

        return new CampaignUpdateInput(
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
            updatedBy: (string) ($this->user()?->getKey() ?? ''),
        );
    }
}
