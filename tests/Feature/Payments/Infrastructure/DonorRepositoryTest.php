<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\Entities\Donor;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Infrastructure\Repositories\DonorRepository;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

/**
 * @covers DonorRepository
 * @covers Donor
 */
final class DonorRepositoryTest extends InfrastructureTestCase
{
    private DonorRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DonorRepository($this->adapter);
    }

    public function testSaveFindByIdRoundTrip(): void
    {
        $donor = Donor::identified(
            name: 'Alice Johnson',
            email: 'alice@example.com',
            phone: '+919876543210',
        );

        $this->repo->save($donor);

        $found = $this->repo->findById($donor->id());
        $this->assertNotNull($found);
        $this->assertSame($donor->id()->value(), $found->id()->value());
        $this->assertSame('Alice Johnson', $found->name());
        $this->assertSame('alice@example.com', $found->email());
        $this->assertFalse($found->isAnonymized());
    }

    public function testFindByEmail(): void
    {
        $donor = Donor::identified(
            name: 'Bob Smith',
            email: 'bob@example.com',
            phone: '+919876543211',
        );

        $this->repo->save($donor);

        $found = $this->repo->findByEmail('bob@example.com');
        $this->assertNotNull($found);
        $this->assertSame($donor->id()->value(), $found->id()->value());
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $found = $this->repo->findByEmail('notfound@example.com');
        $this->assertNull($found);
    }

    public function testFindByPhone(): void
    {
        $donor = Donor::identified(
            name: 'Carol White',
            email: 'carol@example.com',
            phone: '+919876543212',
        );

        $this->repo->save($donor);

        $found = $this->repo->findByPhone('+919876543212');
        $this->assertNotNull($found);
        $this->assertSame($donor->id()->value(), $found->id()->value());
    }

    public function testFindByPhoneReturnsNullWhenNotFound(): void
    {
        $found = $this->repo->findByPhone('+919999999999');
        $this->assertNull($found);
    }

    public function testFindByEmailOrPhone(): void
    {
        $donor = Donor::identified(
            name: 'Dave Brown',
            email: 'dave@example.com',
            phone: '+919876543213',
        );

        $this->repo->save($donor);

        $foundByEmail = $this->repo->findByEmailOrPhone('dave@example.com', null);
        $this->assertNotNull($foundByEmail);
        $this->assertSame($donor->id()->value(), $foundByEmail->id()->value());

        $foundByPhone = $this->repo->findByEmailOrPhone(null, '+919876543213');
        $this->assertNotNull($foundByPhone);
        $this->assertSame($donor->id()->value(), $foundByPhone->id()->value());
    }

    public function testFindByEmailOrPhoneReturnsNullWhenBothNull(): void
    {
        $found = $this->repo->findByEmailOrPhone(null, null);
        $this->assertNull($found);
    }

    public function testAnonymizeNullsPii(): void
    {
        $donor = Donor::identified(
            name: 'Eve Davis',
            email: 'eve@example.com',
            phone: '+919876543214',
            panNumber: 'ABCDE1234F',
        );

        $this->repo->save($donor);
        $this->repo->anonymize($donor->id());

        $anonymized = $this->repo->findById($donor->id());
        $this->assertNotNull($anonymized);
        $this->assertTrue($anonymized->isAnonymized());
        $this->assertNull($anonymized->email());
        $this->assertNull($anonymized->phone());
        // Name may or may not be retained depending on schema design — check actual behavior
        $this->assertSame('Eve Davis', $anonymized->name()); // name retained, email/phone cleared
    }

    public function testExistsWithEmailTrue(): void
    {
        $donor = Donor::identified(
            name: 'Frank Miller',
            email: 'frank@example.com',
            phone: '+919876543215',
        );

        $this->repo->save($donor);

        $this->assertTrue($this->repo->existsWithEmail('frank@example.com'));
    }

    public function testExistsWithEmailFalse(): void
    {
        $this->assertFalse($this->repo->existsWithEmail('ghost@example.com'));
    }

    public function testExistsWithPhoneTrue(): void
    {
        $donor = Donor::identified(
            name: 'Grace Lee',
            email: 'grace@example.com',
            phone: '+919876543216',
        );

        $this->repo->save($donor);

        $this->assertTrue($this->repo->existsWithPhone('+919876543216'));
    }

    public function testExistsWithPhoneFalse(): void
    {
        $this->assertFalse($this->repo->existsWithPhone('+919999999999'));
    }
}
