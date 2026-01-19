<?php

namespace JDZ\FontManager\Tests\General;

use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Font;

class FontTest extends TestCase
{
    private Font $font;

    protected function setUp(): void
    {
        $this->font = new Font();
    }

    public function testFontCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Font::class, $this->font);
    }

    public function testSetBasePath(): void
    {
        $path = '/path/to/fonts';
        $result = $this->font->setBasePath($path);

        $this->assertSame($this->font, $result);
    }

    public function testJsonSerialize(): void
    {
        $data = $this->font->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('family', $data);
    }

    public function testSetsId(): void
    {
        $this->font->sets(['id' => 'test-font']);
        $data = $this->font->jsonSerialize();

        $this->assertEquals('test-font', $data['id']);
    }

    public function testSetsFamily(): void
    {
        $this->font->sets(['family' => 'Test Font']);
        $data = $this->font->jsonSerialize();

        $this->assertEquals('Test Font', $data['family']);
    }

    public function testSetsCategory(): void
    {
        $this->font->sets(['category' => 'sans-serif']);
        $data = $this->font->jsonSerialize();

        $this->assertEquals('sans-serif', $data['category']);
    }

    public function testSetsVersion(): void
    {
        $this->font->sets(['version' => 'v1.0']);
        $data = $this->font->jsonSerialize();

        $this->assertEquals('v1.0', $data['version']);
    }

    public function testIsLocal(): void
    {
        $this->font->isLocal(true);
        $data = $this->font->jsonSerialize();

        $this->assertTrue($data['local']);
    }

    public function testIsInstalled(): void
    {
        $this->font->isInstalled(true);
        $data = $this->font->jsonSerialize();

        $this->assertTrue($data['installed']);
    }

    public function testSetsSubsets(): void
    {
        $subsets = ['latin', 'latin-ext'];
        $this->font->sets(['subsets' => $subsets]);
        $data = $this->font->jsonSerialize();

        $this->assertEquals($subsets, $data['subsets']);
    }

    public function testSetsVariants(): void
    {
        $variants = ['regular', 'italic', '700'];
        $this->font->sets(['variants' => $variants]);
        $data = $this->font->jsonSerialize();

        $this->assertEquals($variants, $data['variants']);
    }
}
