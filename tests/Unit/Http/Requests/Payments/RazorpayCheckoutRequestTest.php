<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Payments;

use App\Http\Requests\Payments\RazorpayCheckoutRequest;
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
}
