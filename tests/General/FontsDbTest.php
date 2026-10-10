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
        $db = new FontsDb($this->testFontsPath . '/missing');

        try {
            $db->load();
            $this->fail('load() should throw on a missing fonts folder');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Fonts folder not found', $e->getMessage());
        }

        // FontsDb::__destruct() saves even after a failed load(): destroy it now, so
        // what it writes lands inside the test's temp dir, which tearDown() removes
        unset($db);
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

    public function testInstallUnknownFontWithoutDistantLoadThrows(): void
    {
        file_put_contents($this->testFontsPath . '/fonts.yml', '[]');

        $db = new FontsDb($this->testFontsPath);
        $db->load();

        // no loadDistantFonts() call: install() must ask the providers itself
        // and report the font as unavailable, not read an undefined key
        $this->expectException(\JDZ\FontManager\Exceptions\FontNotAvailableException::class);

        $db->install('No Such Family', 400, 'normal');
    }
}
