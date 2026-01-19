<?php

namespace JDZ\FontManager\Tests\General;

use PHPUnit\Framework\TestCase;
use JDZ\FontManager\FontVariant;

class FontVariantTest extends TestCase
{
    private FontVariant $variant;

    protected function setUp(): void
    {
        $this->variant = new FontVariant();
    }

    public function testFontVariantCanBeInstantiated(): void
    {
        $this->assertInstanceOf(FontVariant::class, $this->variant);
    }

    public function testSetBasePath(): void
    {
        $path = '/path/to/fonts';
        $result = $this->variant->setBasePath($path);

        $this->assertSame($this->variant, $result);
    }

    public function testJsonSerialize(): void
    {
        $data = $this->variant->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('family', $data);
    }

    public function testSetsId(): void
    {
        $this->variant->sets(['id' => 'regular']);
        $data = $this->variant->jsonSerialize();

        $this->assertEquals('regular', $data['id']);
    }

    public function testSetsFamily(): void
    {
        $this->variant->sets(['family' => 'Test Font']);
        $data = $this->variant->jsonSerialize();

        $this->assertEquals('Test Font', $data['family']);
    }

    public function testSetsStyle(): void
    {
        $this->variant->sets(['style' => 'italic']);
        $data = $this->variant->jsonSerialize();

        $this->assertEquals('italic', $data['style']);
    }

    public function testSetsWeight(): void
    {
        $this->variant->sets(['weight' => '700']);
        $data = $this->variant->jsonSerialize();

        $this->assertEquals('700', $data['weight']);
    }

    public function testSetsDisplay(): void
    {
        $this->variant->sets(['display' => 'swap']);
        $data = $this->variant->jsonSerialize();

        $this->assertEquals('swap', $data['display']);
    }

    public function testIsInstalledDefaultsFalse(): void
    {
        $this->assertFalse($this->variant->isInstalled());
    }

    public function testIsInstalledCanBeSet(): void
    {
        $this->variant->isInstalled(true);
        $this->assertTrue($this->variant->isInstalled());
    }

    public function testGetPath(): void
    {
        $this->variant->setBasePath('/path/to/fonts');
        $this->variant->sets(['id' => 'regular']);

        $this->assertEquals('/path/to/fonts/regular', $this->variant->getPath());
    }

    public function testGetFiles(): void
    {
        $files = $this->variant->getFiles();

        $this->assertIsArray($files);
    }
}
