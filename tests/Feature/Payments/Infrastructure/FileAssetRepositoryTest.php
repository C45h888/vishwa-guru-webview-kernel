<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\ValueObjects\FileAssetRecord;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for FileAssetRepository — concrete impl of
 * FileAssetRepositoryContract. Mirrors the DonorRepositoryTest pattern.
 *
 * Doctrine: the repository constructor takes PersistenceAdapterContract;
 * the test base class (InfrastructureTestCase) provides a working
 * adapter against the in-memory SQLite DB with the canonical V1 schema
 * loaded via the migrations.
 */
final class FileAssetRepositoryTest extends InfrastructureTestCase
{
    private FileAssetRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FileAssetRepository($this->adapter);
    }

    private function makeFileAsset(
        string $id = '01HZX0000000000000000000FA1',
        string $ownerType = 'receipt',
        string $ownerId = '01HZX00000000000000000OWN1',
        string $hash = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
        ?string $purpose = 'receipt_pdf',
    ): FileAssetRecord {
        return FileAssetRecord::create(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            originalFilename: 'receipt.pdf',
            storageDisk: 'local',
            storagePath: 'receipts/' . $id . '.pdf',
            mimeType: 'application/pdf',
            fileSizeBytes: 1024,
            fileHashSha256: $hash,
            purpose: $purpose,
        );
    }

    #[Test]
    public function save_and_find_by_id_round_trip(): void
    {
        $file = $this->makeFileAsset();

        $this->repo->save($file);

        $found = $this->repo->findById($file->id());
        $this->assertNotNull($found);
        $this->assertSame($file->id(), $found->id());
        $this->assertSame($file->ownerType(), $found->ownerType());
        $this->assertSame($file->ownerId(), $found->ownerId());
        $this->assertSame($file->fileHashSha256(), $found->fileHashSha256());
        $this->assertSame($file->mimeType(), $found->mimeType());
        $this->assertSame($file->fileSizeBytes(), $found->fileSizeBytes());
        $this->assertSame($file->purpose(), $found->purpose());
    }

    #[Test]
    public function find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->repo->findById('nonexistent-id');
        $this->assertNull($found);
    }

    #[Test]
    public function find_by_hash_returns_matching_file_asset(): void
    {
        $file = $this->makeFileAsset(hash: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->repo->save($file);

        $found = $this->repo->findByHash('aaaa' . str_repeat('a', 60));
        $this->assertNotNull($found);
        $this->assertSame($file->id(), $found->id());
    }

    #[Test]
    public function find_by_hash_returns_null_when_no_match(): void
    {
        $this->repo->save($this->makeFileAsset());

        $found = $this->repo->findByHash(str_repeat('f', 64));
        $this->assertNull($found);
    }

    #[Test]
    public function find_by_owner_returns_all_matching_files(): void
    {
        $ownerType = 'receipt';
        $ownerId = '01HZX00000000000000000OWN1';

        $this->repo->save($this->makeFileAsset(
            id: '01HZX0000000000000000000FA1',
            ownerType: $ownerType,
            ownerId: $ownerId,
            hash: '1111111111111111111111111111111111111111111111111111111111111111',
        ));
        $this->repo->save($this->makeFileAsset(
            id: '01HZX0000000000000000000FA2',
            ownerType: $ownerType,
            ownerId: $ownerId,
            hash: '2222222222222222222222222222222222222222222222222222222222222222',
        ));

        $found = $this->repo->findByOwner($ownerType, $ownerId);
        $this->assertCount(2, $found);
    }

    #[Test]
    public function find_by_owner_with_no_match_returns_empty(): void
    {
        $this->repo->save($this->makeFileAsset(ownerId: 'someone-else'));

        $found = $this->repo->findByOwner('receipt', 'no-such-owner');
        $this->assertSame([], $found);
    }

    #[Test]
    public function find_by_purpose_returns_files_with_matching_purpose(): void
    {
        $this->repo->save($this->makeFileAsset(
            id: '01HZX0000000000000000000FA1',
            purpose: 'receipt_pdf',
            hash: '1111111111111111111111111111111111111111111111111111111111111111',
        ));
        $this->repo->save($this->makeFileAsset(
            id: '01HZX0000000000000000000FA2',
            purpose: 'certificate_80g',
            hash: '2222222222222222222222222222222222222222222222222222222222222222',
        ));

        $receipts = $this->repo->findByPurpose('receipt_pdf');
        $this->assertCount(1, $receipts);
        $this->assertSame('receipt_pdf', $receipts[0]->purpose());
    }
}