<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts\Pdf;

use App\Payments\Infrastructure\Receipts\Pdf\DomPdfWrapper;
use PHPUnit\Framework\TestCase;

/**
 * DomPdfWrapper requires barryvdh/laravel-dompdf to be installed.
 * This test is skipped in CI if the package is absent.
 */
class DomPdfWrapperTest extends TestCase
{
    public function testWrapperImplementsPdfWrapper(): void
    {
        // is_subclass_of() (not is_a()) is the correct class-string check
        // against an interface — is_a() with allow_string only matches
        // class ancestry, not interface implementation.
        $this->assertTrue(
            is_subclass_of(DomPdfWrapper::class, \App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper::class),
        );
    }

    public function testRenderMethodExists(): void
    {
        $wrapper = new DomPdfWrapper();
        $this->assertTrue(method_exists($wrapper, 'render'));
    }

    public function testWriteToDiskMethodExists(): void
    {
        $wrapper = new DomPdfWrapper();
        $this->assertTrue(method_exists($wrapper, 'writeToDisk'));
    }

    public function testExistsMethodExists(): void
    {
        $wrapper = new DomPdfWrapper();
        $this->assertTrue(method_exists($wrapper, 'exists'));
    }

    public function testSizeMethodExists(): void
    {
        $wrapper = new DomPdfWrapper();
        $this->assertTrue(method_exists($wrapper, 'size'));
    }

    public function testAllMethodsHaveCorrectSignature(): void
    {
        $r = new \ReflectionMethod(DomPdfWrapper::class, 'render');
        $this->assertSame('string', $r->getReturnType()->getName());
        $this->assertCount(1, $r->getParameters());

        $wd = new \ReflectionMethod(DomPdfWrapper::class, 'writeToDisk');
        $this->assertSame('void', $wd->getReturnType()->getName());
        $this->assertCount(3, $wd->getParameters());

        $ex = new \ReflectionMethod(DomPdfWrapper::class, 'exists');
        $this->assertSame('bool', $ex->getReturnType()->getName());
        $this->assertCount(2, $ex->getParameters());

        $sz = new \ReflectionMethod(DomPdfWrapper::class, 'size');
        $this->assertSame('int', $sz->getReturnType()->getName());
        $this->assertCount(2, $sz->getParameters());
    }
}
