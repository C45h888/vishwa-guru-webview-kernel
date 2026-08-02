<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\Repositories\CmsMediaAssetRepositoryContract;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Shared\Support\UlidGenerator;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * GallerySeeder — seeds the 3 published galleries and their images.
 *
 * Three galleries segmented by commonality:
 *   1. Sacred Rituals        (5 photos) — daily pooja and worship
 *   2. Sacred Festivals      (14 photos) — major festivals, kalyanam, kumbhabhishekam
 *   3. Community & Cultural  (17 photos) — honours, community life, cultural offerings
 *
 * Doctrine alignment:
 *   - Idempotent: every INSERT uses ON CONFLICT … DO NOTHING so re-runs are safe.
 *   - Adapter-only: writes to file_assets, galleries, gallery_images go through
 *     PersistenceAdapterContract; cms_media_assets inserts go through
 *     CmsMediaAssetRepositoryContract (which validates alt_text + published_at).
 *   - state='published' on every row so the public gallery pipeline surfaces them.
 *
 * Source of truth for asset metadata: storage/app/public/cms-media/<filename>.
 * Sizes, hashes, and dimensions below were generated from the actual files on disk.
 *
 * Usage:
 *   php artisan db:seed --class="Database\Seeders\GallerySeeder" --force
 */
final class GallerySeeder extends Seeder
{
    /**
     * File-level metadata for every image that backs a gallery row.
     * Keyed by basename. The storage_disk is always 'public' (set in seedFileAssets).
     */
    private const FILE_METADATA = [
            'journal-pooja-01-sandhyavandanam.webp' => [
                'storage_path' => 'cms-media/journal-pooja-01-sandhyavandanam.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 246198,
                'file_hash_sha256' => 'f0f98e909fcc9f3d67f2d0c94c201a45287842cd1fcc49ad58c4fadf180f77ad',
                'width' => 1204,
                'height' => 1600,
            ],
            'journal-pooja-02-nivedanam.webp' => [
                'storage_path' => 'cms-media/journal-pooja-02-nivedanam.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 231662,
                'file_hash_sha256' => '6163fb21b66a08d5ca2e868b2ce475f8dd2c45e5ee5200b349e2e18b22d1590f',
                'width' => 1204,
                'height' => 1600,
            ],
            'journal-pooja-03-procession.webp' => [
                'storage_path' => 'cms-media/journal-pooja-03-procession.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 202978,
                'file_hash_sha256' => '1d486d6546350c7e240f77eedb712ee6bd336aef8c9b3a5eacb9e03f4a992e00',
                'width' => 1107,
                'height' => 1600,
            ],
            'journal-pooja-04-sacred-post.webp' => [
                'storage_path' => 'cms-media/journal-pooja-04-sacred-post.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 207308,
                'file_hash_sha256' => '143b3938ba3c4ebd8f31f2ea16161a2e3533380a62dd3c69ea17cc28f2496cfd',
                'width' => 1075,
                'height' => 1600,
            ],
            'journal-pooja-05-temple-entrance.webp' => [
                'storage_path' => 'cms-media/journal-pooja-05-temple-entrance.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 172466,
                'file_hash_sha256' => '1432d66632b52a7543a18d491fb59065f5b47658d5915af74adab8fe2dc82e8d',
                'width' => 1133,
                'height' => 1600,
            ],
            'journal-festivals-01-girija-kalyana.webp' => [
                'storage_path' => 'cms-media/journal-festivals-01-girija-kalyana.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 118010,
                'file_hash_sha256' => '63686099d317c0dca92669d584836a6e347ecd8fe6c0eeab6c4d3bcc714c5929',
                'width' => 1026,
                'height' => 1600,
            ],
            'journal-festivals-03-ganesh-chaturthi.webp' => [
                'storage_path' => 'cms-media/journal-festivals-03-ganesh-chaturthi.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 111206,
                'file_hash_sha256' => '184488396ae21b014af2b94440c942b51e1029b62bdb1e4a2bb95a5af8771598',
                'width' => 963,
                'height' => 1280,
            ],
            'journal-kalyanam-01.webp' => [
                'storage_path' => 'cms-media/journal-kalyanam-01.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 91278,
                'file_hash_sha256' => '7831a0f77aa8acd5cb3c795a321d64d206a9b297cf56ecde0c89841821d178d0',
                'width' => 963,
                'height' => 1280,
            ],
            'journal-kalyanam-02.webp' => [
                'storage_path' => 'cms-media/journal-kalyanam-02.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 110812,
                'file_hash_sha256' => 'fab94155fb68d86062eee221b379a5c2e0425997a1428fdefb6cf6fa439f358a',
                'width' => 1102,
                'height' => 1600,
            ],
            'journal-kalyanam-03.webp' => [
                'storage_path' => 'cms-media/journal-kalyanam-03.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 93048,
                'file_hash_sha256' => '8c4455a7f0b3f91108d418ce0b062e1d16fa5448c0c67c597c815bac190bff4b',
                'width' => 1074,
                'height' => 1600,
            ],
            'journal-kumbhabhishekam-01.webp' => [
                'storage_path' => 'cms-media/journal-kumbhabhishekam-01.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 102794,
                'file_hash_sha256' => '94cf5ee0b1bb6527326dd6d9486e94d024878115a35cc0573a05f353d9b401c6',
                'width' => 963,
                'height' => 1280,
            ],
            'events-slide-1-ganesh-chaturthi.webp' => [
                'storage_path' => 'cms-media/events-slide-1-ganesh-chaturthi.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 111206,
                'file_hash_sha256' => '184488396ae21b014af2b94440c942b51e1029b62bdb1e4a2bb95a5af8771598',
                'width' => 963,
                'height' => 1280,
            ],
            'events-slide-2-varalakshmi.webp' => [
                'storage_path' => 'cms-media/events-slide-2-varalakshmi.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 220640,
                'file_hash_sha256' => '229c0fde3063de6ce9c51cc7e9dc0aeb9f683f638bb1e0ebf1e6a079223c768a',
                'width' => 1204,
                'height' => 1600,
            ],
            'events-slide-3-sandhyavandanam.webp' => [
                'storage_path' => 'cms-media/events-slide-3-sandhyavandanam.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 246198,
                'file_hash_sha256' => 'f0f98e909fcc9f3d67f2d0c94c201a45287842cd1fcc49ad58c4fadf180f77ad',
                'width' => 1204,
                'height' => 1600,
            ],
            'events-slide-4-nivedanam.webp' => [
                'storage_path' => 'cms-media/events-slide-4-nivedanam.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 231662,
                'file_hash_sha256' => '6163fb21b66a08d5ca2e868b2ce475f8dd2c45e5ee5200b349e2e18b22d1590f',
                'width' => 1204,
                'height' => 1600,
            ],
            'events-slide-5-kumbhabhishekam.webp' => [
                'storage_path' => 'cms-media/events-slide-5-kumbhabhishekam.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 102794,
                'file_hash_sha256' => '94cf5ee0b1bb6527326dd6d9486e94d024878115a35cc0573a05f353d9b401c6',
                'width' => 963,
                'height' => 1280,
            ],
            'events-slide-6-kalyanam.webp' => [
                'storage_path' => 'cms-media/events-slide-6-kalyanam.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 91278,
                'file_hash_sha256' => '7831a0f77aa8acd5cb3c795a321d64d206a9b297cf56ecde0c89841821d178d0',
                'width' => 963,
                'height' => 1280,
            ],
            'events-slide-7-cultural-evenings.webp' => [
                'storage_path' => 'cms-media/events-slide-7-cultural-evenings.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 233704,
                'file_hash_sha256' => 'aa7e48aedbfe02b0e5e8496274c3cd6b81b4e7375c1b55aefcd95ea016f5a2d3',
                'width' => 1920,
                'height' => 1212,
            ],
            'events-slide-8-brahmotsavam.webp' => [
                'storage_path' => 'cms-media/events-slide-8-brahmotsavam.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 149868,
                'file_hash_sha256' => '11d871ee97d192dae22116df365c1dab985c77d7b049870ac24469a2e39f2f7d',
                'width' => 1920,
                'height' => 1228,
            ],
            'journal-awards-01-chaganti.webp' => [
                'storage_path' => 'cms-media/journal-awards-01-chaganti.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 117462,
                'file_hash_sha256' => '604e34fa265f3c517e8031b91930844b32eb00b8a89240d294cf7126f1183957',
                'width' => 1007,
                'height' => 1600,
            ],
            'journal-awards-02-vivekananda.webp' => [
                'storage_path' => 'cms-media/journal-awards-02-vivekananda.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 150402,
                'file_hash_sha256' => '0011bf2a2ef64e96b9bc02401efd16893d51bb4deec5ed8d072438bd756e0150',
                'width' => 971,
                'height' => 1600,
            ],
            'journal-awards-03-shishya-celebration.webp' => [
                'storage_path' => 'cms-media/journal-awards-03-shishya-celebration.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 185742,
                'file_hash_sha256' => 'f694b58683eec8d95e0c2e7ec3b4563a6eec4370637267371d404ecd0097ff5a',
                'width' => 1072,
                'height' => 1600,
            ],
            'journal-awards-04-sarvabhouma.webp' => [
                'storage_path' => 'cms-media/journal-awards-04-sarvabhouma.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 121038,
                'file_hash_sha256' => 'e4c7a98a52e43fbe6dd3fd274ea573a50d93d162aa5d32d1da796960a9729c5c',
                'width' => 1041,
                'height' => 1600,
            ],
            'journal-awards-05-south-indian.webp' => [
                'storage_path' => 'cms-media/journal-awards-05-south-indian.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 154098,
                'file_hash_sha256' => 'abb64c1bed14f62df72316f185433261d7912ac0ff620cd99ab37ebb174121d3',
                'width' => 965,
                'height' => 1600,
            ],
            'journal-awards-06-ganap-sachidananda.webp' => [
                'storage_path' => 'cms-media/journal-awards-06-ganap-sachidananda.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 77974,
                'file_hash_sha256' => '5196df5a03f50bcf34ea007972ea56cba7d34bfea24790fffde37ea3cd9a4c58',
                'width' => 1600,
                'height' => 1202,
            ],
            'journal-community-01-village-group.webp' => [
                'storage_path' => 'cms-media/journal-community-01-village-group.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 79304,
                'file_hash_sha256' => '67b9671e0a17479a567f5ec8e2c63b2130610e299ab59f989c611f0c57fcefc7',
                'width' => 970,
                'height' => 1600,
            ],
            'journal-community-02-ceremony-with-priest.webp' => [
                'storage_path' => 'cms-media/journal-community-02-ceremony-with-priest.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 102494,
                'file_hash_sha256' => '5b5114ebbdf2cde3b159d6406588f81983e3fa0f888002b63b0c6b2374ddf4da',
                'width' => 1090,
                'height' => 1600,
            ],
            'journal-community-03-ashram-boys.webp' => [
                'storage_path' => 'cms-media/journal-community-03-ashram-boys.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 135814,
                'file_hash_sha256' => '5a97dc618b33927f16c63b0da587407e8dc092a147ba68eb736da93af5c567e3',
                'width' => 940,
                'height' => 1600,
            ],
            'journal-community-04-ashram-adults.webp' => [
                'storage_path' => 'cms-media/journal-community-04-ashram-adults.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 142488,
                'file_hash_sha256' => '6b926dcf55da5367b0a25715aab6026eccf8545e24a6c148bc38b36390cc4062',
                'width' => 1036,
                'height' => 1599,
            ],
            'journal-community-05-villagers.webp' => [
                'storage_path' => 'cms-media/journal-community-05-villagers.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 164020,
                'file_hash_sha256' => '0a47cbde051c77d06d46dfea8710d41e19dd4b549da1af5b4712c24ecd318f8e',
                'width' => 1062,
                'height' => 1600,
            ],
            'journal-community-06-booklets.webp' => [
                'storage_path' => 'cms-media/journal-community-06-booklets.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 223384,
                'file_hash_sha256' => '5fc9238524900ee5fb5ac28734d46b0f4e0cb877340f965c56f969b7743e3e3c',
                'width' => 1089,
                'height' => 1600,
            ],
            'journal-cultural-01-bharatanatyam-tribute.webp' => [
                'storage_path' => 'cms-media/journal-cultural-01-bharatanatyam-tribute.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 135826,
                'file_hash_sha256' => 'dd935f36e2ef40a0a0b0c65e2ee7bd0332854fcb6033f99d14cb32ddf23cdd26',
                'width' => 970,
                'height' => 1600,
            ],
            'journal-cultural-02-bharatanatyam-guru.webp' => [
                'storage_path' => 'cms-media/journal-cultural-02-bharatanatyam-guru.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 186542,
                'file_hash_sha256' => '448cfed294b00b44f3ff5a8b77be4fab30164ebba65f448e00fc937cea52dcf1',
                'width' => 1019,
                'height' => 1600,
            ],
            'journal-cultural-03-bharatanatyam-hyderabad.webp' => [
                'storage_path' => 'cms-media/journal-cultural-03-bharatanatyam-hyderabad.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 177312,
                'file_hash_sha256' => 'e6c78fde53ce888a4b43463ca4b68e34389e9d345227c69abe5638880f57c9f1',
                'width' => 984,
                'height' => 1600,
            ],
            'journal-cultural-04-literary-tribute.webp' => [
                'storage_path' => 'cms-media/journal-cultural-04-literary-tribute.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 119150,
                'file_hash_sha256' => 'cac7d00e17e6ae63e5b0c39a60e58ece23790d20bc579143f18a626cb579baa6',
                'width' => 1048,
                'height' => 1600,
            ],
            'journal-cultural-05-sangam-collage.webp' => [
                'storage_path' => 'cms-media/journal-cultural-05-sangam-collage.webp',
                'mime_type' => 'image/webp',
                'file_size_bytes' => 187376,
                'file_hash_sha256' => '595aaa8c6a446b6d4e4558589dc394a5f770f6b0dda4fcc3b38346b30d366e8e',
                'width' => 1081,
                'height' => 1600,
            ],
    ];

    /**
     * Gallery definitions: slug => definition.
     * 'images' lists filenames in display order; 'cover' must be one of them.
     */
    private const GALLERIES = [
        'sacred-rituals' => [
            'title' => 'Sacred Rituals',
            'description' => 'The daily rhythm of worship at the temple — sandhyavandanam at dawn, nivedanam, the procession of the deities, and the sacred posts that mark the temple grounds. These are the rituals performed without break, in the same form, in the same place, every day of the year.',
            'cover' => 'journal-pooja-01-sandhyavandanam.webp',
            'is_featured' => true,
            'display_order' => 10,
            'images' => [
                'journal-pooja-01-sandhyavandanam.webp',
                'journal-pooja-02-nivedanam.webp',
                'journal-pooja-03-procession.webp',
                'journal-pooja-04-sacred-post.webp',
                'journal-pooja-05-temple-entrance.webp',
            ],
        ],
        'sacred-festivals' => [
            'title' => 'Sacred Festivals',
            'description' => 'The major festivals that draw the community together through the year — Ganesh Chaturthi, Varalakshmi, Kumbhabhishekam, Brahmotsavam, Kalyanam, and the rest of the festival calendar. Each occasion carries its own rituals, its own preparations, and its own particular joy.',
            'cover' => 'journal-festivals-01-girija-kalyana.webp',
            'is_featured' => true,
            'display_order' => 20,
            'images' => [
                'journal-festivals-01-girija-kalyana.webp',
                'journal-festivals-03-ganesh-chaturthi.webp',
                'journal-kalyanam-01.webp',
                'journal-kalyanam-02.webp',
                'journal-kalyanam-03.webp',
                'journal-kumbhabhishekam-01.webp',
                'events-slide-1-ganesh-chaturthi.webp',
                'events-slide-2-varalakshmi.webp',
                'events-slide-3-sandhyavandanam.webp',
                'events-slide-4-nivedanam.webp',
                'events-slide-5-kumbhabhishekam.webp',
                'events-slide-6-kalyanam.webp',
                'events-slide-7-cultural-evenings.webp',
                'events-slide-8-brahmotsavam.webp',
            ],
        ],
        'community-cultural' => [
            'title' => 'Community & Cultural',
            'description' => 'The people and offerings that surround the temple — visitors, scholars, devotees, and performers who come to honour the lineage. Bharatanatyam recitals, literary tributes, village gatherings, and the small ceremonies that hold the community together.',
            'cover' => 'journal-awards-01-chaganti.webp',
            'is_featured' => true,
            'display_order' => 30,
            'images' => [
                'journal-awards-01-chaganti.webp',
                'journal-awards-02-vivekananda.webp',
                'journal-awards-03-shishya-celebration.webp',
                'journal-awards-04-sarvabhouma.webp',
                'journal-awards-05-south-indian.webp',
                'journal-awards-06-ganap-sachidananda.webp',
                'journal-community-01-village-group.webp',
                'journal-community-02-ceremony-with-priest.webp',
                'journal-community-03-ashram-boys.webp',
                'journal-community-04-ashram-adults.webp',
                'journal-community-05-villagers.webp',
                'journal-community-06-booklets.webp',
                'journal-cultural-01-bharatanatyam-tribute.webp',
                'journal-cultural-02-bharatanatyam-guru.webp',
                'journal-cultural-03-bharatanatyam-hyderabad.webp',
                'journal-cultural-04-literary-tribute.webp',
                'journal-cultural-05-sangam-collage.webp',
            ],
        ],
    ];

    public function run(): void
    {
        $adapter = $this->container->make(PersistenceAdapterContract::class);
        $mediaRepo = $this->container->make(CmsMediaAssetRepositoryContract::class);

        $this->command->info('GallerySeeder: starting idempotent seed (3 galleries, 36 images).');

        // Order matters: file_assets → cms_media_assets (FK) → galleries (FK to media) → gallery_images (FKs to both)
        $fileAssetIds = $this->seedFileAssets($adapter);
        $cmsMediaIds = $this->seedCmsMediaAssets($mediaRepo, $fileAssetIds);
        $galleryIds = $this->seedGalleries($adapter, $cmsMediaIds);
        $imageCount = $this->seedGalleryImages($adapter, $galleryIds, $cmsMediaIds);

        $this->command->info("GallerySeeder: complete — seeded 3 galleries and {$imageCount} images.");
    }

    /**
     * Insert one file_assets row per image. Returns [filename => file_asset_id].
     *
     * @return array<string, string>
     */
    private function seedFileAssets(PersistenceAdapterContract $adapter): array
    {
        $this->command->info('  → file_assets');
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $ids = [];

        foreach (self::FILE_METADATA as $filename => $meta) {
            $fileAssetId = 'file_asset_'.UlidGenerator::generate();
            $ids[$filename] = $fileAssetId;

            $r = $adapter->execute(
                "INSERT INTO file_assets (
                    id, owner_type, owner_id, original_filename,
                    storage_disk, storage_path, mime_type,
                    file_size_bytes, file_hash_sha256,
                    purpose, is_public, is_archived, archived_at,
                    metadata, uploaded_at,
                    created_at, updated_at, deleted_at, uploaded_by
                ) VALUES (
                    :id, :owner_type, :owner_id, :original_filename,
                    :storage_disk, :storage_path, :mime_type,
                    :file_size_bytes, :file_hash_sha256,
                    :purpose, :is_public, :is_archived, :archived_at,
                    :metadata, :uploaded_at,
                    :now, :now, :deleted_at, :uploaded_by
                )
                ON CONFLICT (id) DO NOTHING",
                [
                    'id'                 => $fileAssetId,
                    'owner_type'         => 'cms_media',
                    'owner_id'           => $fileAssetId,
                    'original_filename'  => $filename,
                    'storage_disk'       => 'public',
                    'storage_path'       => $meta['storage_path'],
                    'mime_type'          => $meta['mime_type'],
                    'file_size_bytes'    => $meta['file_size_bytes'],
                    'file_hash_sha256'   => $meta['file_hash_sha256'],
                    'purpose'            => 'gallery',
                    'is_public'          => 'true',
                    'is_archived'        => 'false',
                    'archived_at'        => null,
                    'metadata'           => '{}',
                    'uploaded_at'        => $now,
                    'now'                => $now,
                    'deleted_at'         => null,
                    'uploaded_by'        => 'system',
                ]
            );
            if ($r->isFailure()) {
                $this->command->error("    ! file_asset {$filename}: ".$r->error());
            }
        }

        return $ids;
    }

    /**
     * Insert one cms_media_assets row per image (typed overlay on file_assets).
     * Uses the repository so alt_text + published_at invariants are validated.
     *
     * @param  array<string, string>  $fileAssetIds
     * @return array<string, string>   [filename => cms_media_id]
     */
    private function seedCmsMediaAssets(
        CmsMediaAssetRepositoryContract $mediaRepo,
        array $fileAssetIds,
    ): array {
        $this->command->info('  → cms_media_assets');
        $ids = [];
        $now = new DateTimeImmutable();

        foreach (self::FILE_METADATA as $filename => $meta) {
            $cmsMediaId = 'cms_media_'.UlidGenerator::generate();
            $ids[$filename] = $cmsMediaId;

            try {
                $record = CmsMediaAssetRecord::create(
                    id: $cmsMediaId,
                    fileAssetId: $fileAssetIds[$filename],
                    mediaType: PublicMediaType::GALLERY_IMAGE,
                    state: PublicMediaState::PUBLISHED,
                    altText: self::altTextFor($filename),
                    caption: null,
                    credit: null,
                    width: $meta['width'],
                    height: $meta['height'],
                    focalX: null,
                    focalY: null,
                    variantGroupId: null,
                    publishedAt: $now,
                    archivedAt: null,
                    createdBy: 'system',
                    updatedBy: 'system',
                );
                $mediaRepo->save($record);
            } catch (Throwable $e) {
                $this->command->error("    ! cms_media_asset {$filename}: ".$e->getMessage());
            }
        }

        return $ids;
    }

    /**
     * Insert one galleries row per gallery. cover_image_file_id references
     * the cms_media_assets row that backs the cover image.
     *
     * @param  array<string, string>  $cmsMediaIds
     * @return array<string, string>   [slug => gallery_id]
     */
    private function seedGalleries(
        PersistenceAdapterContract $adapter,
        array $cmsMediaIds,
    ): array {
        $this->command->info('  → galleries');
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $ids = [];

        foreach (self::GALLERIES as $slug => $g) {
            $galleryId = 'gallery_'.UlidGenerator::generate();
            $ids[$slug] = $galleryId;
            $coverMediaId = $cmsMediaIds[$g['cover']] ?? null;

            $r = $adapter->execute(
                "INSERT INTO galleries (
                    id, slug, title, description, cover_image_file_id,
                    state, display_order, is_featured, published_at, metadata,
                    created_at, updated_at, deleted_at, created_by, updated_by
                ) VALUES (
                    :id, :slug, :title, :description, :cover,
                    :state, :display_order, :is_featured, :published_at, :metadata,
                    :now, :now, :deleted_at, :created_by, :updated_by
                )
                ON CONFLICT (id) DO NOTHING",
                [
                    'id'           => $galleryId,
                    'slug'         => $slug,
                    'title'        => $g['title'],
                    'description'  => $g['description'],
                    'cover'        => $coverMediaId,
                    'state'        => 'published',
                    'display_order'=> $g['display_order'],
                    'is_featured'  => $g['is_featured'] ? 'true' : 'false',
                    'published_at' => $now,
                    'metadata'     => '{}',
                    'now'          => $now,
                    'deleted_at'   => null,
                    'created_by'   => 'system',
                    'updated_by'   => 'system',
                ]
            );
            if ($r->isFailure()) {
                $this->command->error("    ! gallery {$slug}: ".$r->error());
            }
        }

        return $ids;
    }

    /**
     * Insert gallery_images rows linking images to galleries. Returns count.
     *
     * @param  array<string, string>  $galleryIds
     * @param  array<string, string>  $cmsMediaIds
     */
    private function seedGalleryImages(
        PersistenceAdapterContract $adapter,
        array $galleryIds,
        array $cmsMediaIds,
    ): int {
        $this->command->info('  → gallery_images');
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $count = 0;

        foreach (self::GALLERIES as $slug => $g) {
            $galleryId = $galleryIds[$slug] ?? null;
            if ($galleryId === null) {
                continue;
            }

            $order = 0;
            foreach ($g['images'] as $filename) {
                $imageId = 'gallery_image_'.UlidGenerator::generate();
                $fileAssetId = $cmsMediaIds[$filename] ?? null;

                $r = $adapter->execute(
                    "INSERT INTO gallery_images (
                        id, gallery_id, file_asset_id, title, caption, alt_text,
                        photographer_credit, taken_at, display_order, is_featured,
                        state, published_at, metadata,
                        created_at, updated_at, deleted_at, created_by, updated_by
                    ) VALUES (
                        :id, :gallery_id, :file_asset_id, :title, :caption, :alt_text,
                        :photographer_credit, :taken_at, :display_order, :is_featured,
                        :state, :published_at, :metadata,
                        :now, :now, :deleted_at, :created_by, :updated_by
                    )
                    ON CONFLICT (id) DO NOTHING",
                    [
                        'id'                 => $imageId,
                        'gallery_id'         => $galleryId,
                        'file_asset_id'      => $fileAssetId,
                        'title'              => self::altTextFor($filename),
                        'caption'            => null,
                        'alt_text'           => self::altTextFor($filename),
                        'photographer_credit'=> null,
                        'taken_at'           => null,
                        'display_order'      => $order,
                        'is_featured'        => $order === 0 ? 'true' : 'false',
                        'state'              => 'published',
                        'published_at'       => $now,
                        'metadata'           => '{}',
                        'now'                => $now,
                        'deleted_at'         => null,
                        'created_by'         => 'system',
                        'updated_by'         => 'system',
                    ]
                );
                if ($r->isFailure()) {
                    $this->command->error("    ! gallery_image {$slug}/{$filename}: ".$r->error());
                } else {
                    $count++;
                }
                $order++;
            }
        }

        return $count;
    }

    /**
     * Human-readable alt text derived from the filename slug.
     * Strips the section marker (journal, events) and leading number,
     * replaces dashes with spaces, and title-cases the result.
     */
    private static function altTextFor(string $filename): string
    {
        $base = preg_replace('/\.webp$/', '', $filename) ?? $filename;
        $parts = explode('-', $base);
        $drop = ['journal', 'events'];
        $parts = array_values(array_filter($parts, static fn ($p) => !in_array($p, $drop, true)));
        if (count($parts) > 0 && preg_match('/^\d+$/', $parts[0]) === 1) {
            array_shift($parts);
        }
        $text = implode(' ', $parts);
        $text = str_replace('-', ' ', $text);

        return ucwords($text);
    }
}