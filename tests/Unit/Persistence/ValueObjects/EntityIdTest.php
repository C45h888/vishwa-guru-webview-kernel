<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EntityIdTest extends TestCase
{
    public function testGenerateProducesValidEntityId(): void
    {
        $id = EntityId::generate('donation');
        $this->assertSame('donation', $id->entityType());
        $this->assertSame(26, strlen($id->ulid()));
        $this->assertStringStartsWith('donation_', $id->value());
    }

    public function testFromStringParsesValidEntityId(): void
    {
        $original = EntityId::generate('transaction');
        $parsed = EntityId::fromString($original->value());

        $this->assertTrue($original->equals($parsed));
        $this->assertSame('transaction', $parsed->entityType());
        $this->assertSame($original->ulid(), $parsed->ulid());
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EntityId::fromString('not_a_valid_entity_id');
    }

    public function testEmptyEntityTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EntityId('', '01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }

    public function testInvalidEntityTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EntityId('Donation', '01ARZ3NDEKTSV4RRFFQ69G5FAV'); // Uppercase not allowed
    }

    public function testInvalidUlidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EntityId('donation', 'NOT-A-VALID-ULID-AT-ALL');
    }

    public function testEquals(): void
    {
        $a = EntityId::generate('donor');
        $b = EntityId::fromString($a->value());
        $c = EntityId::generate('donor');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testDifferentEntityTypesAreDistinct(): void
    {
        $donation = EntityId::generate('donation');
        $transaction = EntityId::generate('transaction');

        $this->assertFalse($donation->equals($transaction));
    }

    public function testSnakeCaseEntityTypesAreValid(): void
    {
        $id = new EntityId('trust_document', '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $this->assertSame('trust_document', $id->entityType());
    }
}