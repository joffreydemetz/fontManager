<?php

namespace JDZ\FontManager\Tests\General;

use PHPUnit\Framework\TestCase;
use JDZ\FontManager\FontsDb;
use JDZ\FontManager\Providers\Provider;

class FontsDbTest extends TestCase
{
    private string $testFontsPath;

    protected function setUp(): void
    {
        $this->testFontsPath = sys_get_temp_dir() . '/test-fonts-' . uniqid();
        mkdir($this->testFontsPath);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testFontsPath)) {
            $this->removeDirectory($this->testFontsPath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testFontsDbCanBeInstantiated(): void
    {
        $db = new FontsDb($this->testFontsPath);

        $this->assertInstanceOf(FontsDb::class, $db);
    }

    public function testFontsDbWithCustomFormats(): void
    {
        $formats = ['woff2', 'woff'];
        $db = new FontsDb($this->testFontsPath, $formats);

        $this->assertInstanceOf(FontsDb::class, $db);
    }

    public function testAddProvider(): void
    {
        $db = new FontsDb($this->testFontsPath);
        $provider = $this->createMock(Provider::class);

        $result = $db->addProvider($provider);

        $this->assertSame($db, $result);
    }

    public function testLoadThrowsExceptionWhenFontsPathNotExists(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fonts folder not found');

        $nonExistentPath = sys_get_temp_dir() . '/non-existent-path-' . uniqid();
        $db = new FontsDb($nonExistentPath);
        $db->load();
    }

    public function testLoadSucceedsWithExistingPath(): void
    {
        // Create fonts.yml file
        file_put_contents($this->testFontsPath . '/fonts.yml', '[]');

        $db = new FontsDb($this->testFontsPath);
        $result = $db->load();

        $this->assertSame($db, $result);
    }

    public function testLoadDistantFonts(): void
    {
        file_put_contents($this->testFontsPath . '/fonts.yml', '[]');

        $db = new FontsDb($this->testFontsPath);
        $db->load();

        $result = $db->loadDistantFonts();

        $this->assertSame($db, $result);
    }
}
