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

    public function test_lowercase_pan_is_normalized_and_accepted(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => 'abcde1234f',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
        $this->assertSame('ABCDE1234F', $validator->getData()['donor']['pan']);
    }

    public function test_spaced_pan_is_normalized_and_accepted(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => 'AB CDE-1234 F',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
        $this->assertSame('ABCDE1234F', $validator->getData()['donor']['pan']);
    }

    public function test_empty_pan_is_normalized_to_null(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => '   ',
            ],
        ]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
        $this->assertNull($validator->getData()['donor']['pan']);
    }

    public function test_invalid_pan_is_rejected_after_normalization(): void
    {
        $validator = $this->validatePrepared([
            'amount_minor' => 500_000,
            'currency' => 'INR',
            'campaign_id' => $this->campaignId(),
            'donor' => [
                'name' => 'A Devotee',
                'email' => 'devotee@example.com',
                'phone' => '+919876543210',
                'pan' => 'ABCDE12345',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors()->get('donor.pan'));
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

    /**
     * Same as validate(), but runs prepareForValidation() first so the
     * boundary normalizations (PAN casing/separators) are exercised the
     * way they are in the real request lifecycle.
     *
     * @param  array<string, mixed>  $data
     */
    private function validatePrepared(array $data): \Illuminate\Validation\Validator
    {
        $request = new RazorpayCheckoutRequest();
        $request->replace($data);

        $prepare = new \ReflectionMethod($request, 'prepareForValidation');
        $prepare->setAccessible(true);
        $prepare->invoke($request);

        return Validator::make($request->all(), $request->rules());
    }

    private function campaignId(): string
    {
        return EntityId::generate('campaign')->value();
    }
}
