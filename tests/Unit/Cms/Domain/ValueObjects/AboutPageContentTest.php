<?php

declare(strict_types=1);

namespace Tests\Unit\Cms\Domain\ValueObjects;

use App\Cms\Domain\ValueObjects\AboutPageContent;
use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AboutPageContentTest extends TestCase
{
    public function test_it_round_trips_version_one_content(): void
    {
        $payload = $this->validPayload();

        $content = AboutPageContent::fromArray($payload, []);

        $this->assertSame(AboutPageContent::CURRENT_VERSION, $content->version());
        $this->assertSame($payload, $content->toArray());
    }

    public function test_it_rejects_an_empty_timeline(): void
    {
        $payload = $this->validPayload();
        $payload['timeline'] = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('timeline must contain at least one entry');

        AboutPageContent::fromArray($payload, []);
    }

    public function test_it_accepts_zero_trustees(): void
    {
        $payload = $this->validPayload();
        $payload['trustees'] = [];

        $content = AboutPageContent::fromArray($payload, []);

        $this->assertSame([], $content->trustees());
    }

    public function test_it_rejects_an_image_id_not_in_existing_media_ids(): void
    {
        $imageId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['values']['image_file_id'] = $imageId;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in cms_media_assets');

        AboutPageContent::fromArray($payload, []);
    }

    public function test_it_rejects_a_trustee_photo_id_not_in_existing_media_ids(): void
    {
        $photoId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['trustees'][0]['photo_file_id'] = $photoId;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in cms_media_assets');

        AboutPageContent::fromArray($payload, []);
    }

    public function test_it_accepts_referenced_image_ids_present_in_existing_media_set(): void
    {
        $imageId = EntityId::generate('cms_media_asset')->value();
        $photoId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['values']['image_file_id'] = $imageId;
        $payload['trustees'][0]['photo_file_id'] = $photoId;

        $content = AboutPageContent::fromArray($payload, [$imageId, $photoId]);

        $this->assertSame($imageId, $content->values()->imageFileId()->value());
        $this->assertSame($photoId, $content->trustees()[0]->photoFileId()->value());
    }

    public function test_referenced_image_id_helper_collects_distinct_ids(): void
    {
        $imageId = EntityId::generate('cms_media_asset')->value();
        $photoId = EntityId::generate('cms_media_asset')->value();

        $payload = $this->validPayload();
        $payload['values']['image_file_id'] = $imageId;
        $payload['trustees'][0]['photo_file_id'] = $photoId;
        $payload['trustees'][1]['photo_file_id'] = $photoId; // duplicate

        $ids = AboutPageContent::referencedImageFileIdsFromArray($payload);

        $this->assertCount(2, $ids);
        $this->assertContains($imageId, $ids);
        $this->assertContains($photoId, $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'version' => 1,
            'values' => [
                'eyebrow' => 'Our Values',
                'title' => 'Seva, Satya, and Smriti',
                'body' => 'The trust is sustained by three commitments: seva, satya, and smriti.',
                'image_file_id' => null,
                'alt_text' => null,
            ],
            'timeline' => [
                [
                    'year' => 1998,
                    'title' => 'Temple founded',
                    'description' => 'A small group of devotees established the temple.',
                ],
            ],
            'trustees' => [
                [
                    'name' => 'Dr. Anjali Rao',
                    'role' => 'Chair',
                    'photo_file_id' => null,
                    'bio' => 'A Sanskrit scholar and practising devotee.',
                ],
                [
                    'name' => 'Sundaram Iyer',
                    'role' => 'Treasurer',
                    'photo_file_id' => null,
                    'bio' => 'A retired banker.',
                ],
            ],
            'donate_cta' => [
                'eyebrow' => 'Offer Your Seva',
                'title' => "Help sustain the temple's daily work",
                'body' => 'Every offering supports daily pooja and Annadanam.',
                'cta_label' => 'Donate Now',
                'cta_url' => '/donate',
            ],
        ];
    }
}
