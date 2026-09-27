<?php

declare(strict_types=1);

namespace Tests\Unit\Cms\Domain\ValueObjects;

use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Cms\Domain\ValueObjects\HomepageDonateCta;
use App\Cms\Domain\ValueObjects\HomepageMissionQuote;
use App\Cms\Domain\ValueObjects\HomepageProgram;
use App\Cms\Domain\ValueObjects\HomepageStory;
use App\Cms\Domain\ValueObjects\HomepageTrustPanel;
use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HomepageContentTest extends TestCase
{
    public function test_it_round_trips_version_one_content(): void
    {
        $payload = $this->validPayload();

        $content = HomepageContent::fromArray($payload, []);

        $this->assertSame(HomepageContent::CURRENT_VERSION, $content->version());
        $this->assertSame($payload, $content->toArray());
    }

    public function test_it_requires_exactly_three_programs(): void
    {
        $payload = $this->validPayload();
        $payload['programs'] = [$payload['programs'][0]];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly three programs');

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_requires_canonical_program_order(): void
    {
        $payload = $this->validPayload();
        $payload['programs'] = [
            $payload['programs'][1],
            $payload['programs'][0],
            $payload['programs'][2],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pooja, annadanam, temple_care');

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_accepts_null_image_references(): void
    {
        $payload = $this->validPayload();
        $payload['story']['image_file_id'] = null;
        foreach ($payload['programs'] as $i => $program) {
            $payload['programs'][$i]['image_file_id'] = null;
        }

        $content = HomepageContent::fromArray($payload, []);

        $this->assertNull($content->story()->imageFileId());
        foreach ($content->programs() as $program) {
            $this->assertNull($program->imageFileId());
        }
    }

    public function test_it_rejects_an_image_id_not_in_existing_media_ids(): void
    {
        $imageId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['story']['image_file_id'] = $imageId;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in cms_media_assets');

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_accepts_a_valid_image_id_when_present_in_existing_set(): void
    {
        $imageId = EntityId::generate('cms_media_asset')->value();
        $programId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['story']['image_file_id'] = $imageId;
        $payload['programs'][0]['image_file_id'] = $programId;

        $content = HomepageContent::fromArray($payload, [$imageId, $programId]);

        $this->assertSame($imageId, $content->story()->imageFileId()->value());
        $this->assertSame($programId, $content->programs()[0]->imageFileId()->value());
    }

    public function test_it_round_trips_a_decoupled_pillar_image_reference(): void
    {
        $storyId = EntityId::generate('cms_media_asset')->value();
        $pillarId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['story']['image_file_id'] = $storyId;
        $payload['story']['pillar_image_file_id'] = $pillarId;

        // The referenced-set enforcement covers the pillar slot too.
        $content = HomepageContent::fromArray($payload, [$storyId, $pillarId]);

        $this->assertSame($storyId, $content->story()->imageFileId()->value());
        $this->assertSame($pillarId, $content->story()->pillarImageFileId()->value());
        $this->assertSame($payload, $content->toArray());
    }

    public function test_it_rejects_a_pillar_image_id_not_in_existing_media_ids(): void
    {
        $pillarId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['story']['pillar_image_file_id'] = $pillarId;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in cms_media_assets');

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_rejects_unsupported_versions(): void
    {
        $payload = $this->validPayload();
        $payload['version'] = 2;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('version must be 1');

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_rejects_an_unknown_program_key(): void
    {
        $payload = $this->validPayload();
        $payload['programs'][0]['key'] = 'weekly_yagna';

        $this->expectException(InvalidArgumentException::class);

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_rejects_cta_label_without_cta_url(): void
    {
        $payload = $this->validPayload();
        $payload['story']['cta_label'] = 'Read more';
        $payload['story']['cta_url'] = null;

        $this->expectException(InvalidArgumentException::class);

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_rejects_an_external_cta_url(): void
    {
        $payload = $this->validPayload();
        $payload['story']['cta_url'] = 'https://example.com/about';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('site-relative path');

        HomepageContent::fromArray($payload, []);
    }

    public function test_it_rejects_an_empty_trust_panel(): void
    {
        $payload = $this->validPayload();
        $payload['trust_panel']['operating_principles'] = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('operating_principles must contain at least one');

        HomepageContent::fromArray($payload, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'version' => 1,
            'story' => [
                'eyebrow' => 'Our Story',
                'title' => 'A trust sustained by seva',
                'body' => 'Temple Trust is a registered charitable trust.',
                'cta_label' => 'Read more about the trust',
                'cta_url' => '/about',
                'image_file_id' => null,
                'pillar_image_file_id' => null,
                'alt_text' => 'Temple and sacred grounds',
            ],
            'mission_quote' => [
                'eyebrow' => 'Our Mission',
                'quote' => 'To preserve the sacred traditions of daily pooja.',
                'attribution' => null,
            ],
            'programs' => [
                [
                    'key' => 'pooja',
                    'eyebrow' => 'Practice',
                    'title' => 'Daily Pooja',
                    'body' => 'The rhythm of pooja — at sunrise, noon, and sunset.',
                    'image_file_id' => null,
                    'pillar_image_file_id' => null,
                    'alt_text' => 'Daily pooja',
                ],
                [
                    'key' => 'annadanam',
                    'eyebrow' => 'Service',
                    'title' => 'Annadanam',
                    'body' => 'Free meals served daily to all who visit the temple.',
                    'image_file_id' => null,
                    'pillar_image_file_id' => null,
                    'alt_text' => 'Annadanam service',
                ],
                [
                    'key' => 'temple_care',
                    'eyebrow' => 'Stewardship',
                    'title' => 'Temple Care',
                    'body' => 'The temple structure and the surrounding grounds.',
                    'image_file_id' => null,
                    'pillar_image_file_id' => null,
                    'alt_text' => 'Temple care',
                ],
            ],
            'trust_panel' => [
                'eyebrow' => 'Trust & Accountability',
                'title' => 'Stewardship you can rely on',
                'registration' => 'Formally registered trust.',
                'tax_status' => 'Eligible donations receive applicable tax documentation.',
                'operating_principles' => ['Service before convenience'],
                'vows' => ['Preserve tradition'],
            ],
            'donate_cta' => [
                'eyebrow' => 'Offer Your Seva',
                'title' => 'Help sustain the temple’s daily work',
                'body' => 'Every offering supports daily worship.',
                'cta_label' => 'Donate Now',
                'cta_url' => '/donate',
            ],
        ];
    }
}
