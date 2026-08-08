<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Http\Requests;

use App\Payments\Http\Requests\RazorpayCheckoutRequest;
use App\Persistence\ValueObjects\EntityId;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class RazorpayCheckoutRequestTest extends TestCase
{
    public function test_canonical_typed_campaign_id_is_accepted(): void
    {
        $validator = Validator::make(
            [
                'amount_minor' => 100,
                'currency' => 'INR',
                'campaign_id' => EntityId::generate('campaign')->value(),
            ],
            (new RazorpayCheckoutRequest)->rules(),
        );

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }

    public function test_bare_campaign_ulid_is_rejected(): void
    {
        $campaignId = EntityId::generate('campaign');
        $validator = Validator::make(
            [
                'amount_minor' => 100,
                'currency' => 'INR',
                'campaign_id' => $campaignId->ulid(),
            ],
            (new RazorpayCheckoutRequest)->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('campaign_id'));
    }

    public function test_anonymous_donation_without_email_or_phone_is_accepted(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => null,
                'email' => null,
                'phone' => null,
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }

    public function test_identified_donation_requires_email_and_phone(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('donor.email'));
        $this->assertNotEmpty($validator->errors()->get('donor.phone'));
    }

    public function test_identified_donation_with_email_and_phone_is_accepted(): void
    {
        $validator = $this->validate([
            'amount_minor' => 100,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }

    /**
     * Validate an input payload against the live FormRequest rules. The
     * request MUST carry the input (merge/replace) before rules() runs,
     * because the "required when identified" rule reads donor.name from
     * the request's own input — a bare (new RazorpayCheckoutRequest)
     * has none, so the condition would never fire.
     *
     * @param  array<string, mixed>  $data
     */
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new RazorpayCheckoutRequest();
        $request->replace($data);

        return Validator::make($request->all(), $request->rules());
    }

    private function campaignId(): string
    {
        return EntityId::generate('campaign')->value();
    }
}
