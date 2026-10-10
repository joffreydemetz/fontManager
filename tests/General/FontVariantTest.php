<?php

namespace JDZ\FontManager\Tests\General;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use JDZ\FontManager\FontVariant;
use JDZ\FontManager\Exceptions\FontException;

class FontVariantTest extends TestCase
{
    private FontVariant $variant;

    protected function setUp(): void
    {
        $this->variant = new FontVariant();
    }

    public function testGetPath(): void
    {
        $this->variant->setBasePath('/path/to/fonts');
        $this->variant->sets(['id' => 'regular']);

        $this->assertSame('/path/to/fonts/regular', $this->variant->getPath());
    }

    #[DataProvider('serialisations')]
    public function testJsonSerialize(array $data, bool $installed, array $files, array $expected): void
    {
        $this->variant->sets($data)->isInstalled($installed);
        foreach ($files as $ext => $file) {
            $this->variant->addFile($ext, $file);
        }

        $this->assertSame($expected, $this->variant->jsonSerialize());
    }

    public static function serialisations(): array
    {
        return [
            'defaults: style normal, empty fields left out' => [[], false, [], ['id' => '', 'family' => '', 'style' => 'normal']],
            'empty style left out' => [['id' => 'regular', 'family' => 'Roboto', 'style' => ''], false, [], ['id' => 'regular', 'family' => 'Roboto']],
            'every field' => [
                ['id' => '700italic', 'family' => 'Roboto', 'style' => 'italic', 'weight' => '700', 'display' => 'swap'],
                true,
                ['ttf' => 'roboto-700italic.ttf', 'woff2' => 'roboto-700italic.woff2'],
                [
                    'id' => '700italic',
                    'family' => 'Roboto',
                    'style' => 'italic',
                    'weight' => '700',
                    'display' => 'swap',
                    'installed' => true,
                    'files' => ['ttf' => 'roboto-700italic.ttf', 'woff2' => 'roboto-700italic.woff2'],
                ],
            ],
        ];
    }

    public function testToFileLeavesOutEmptyValues(): void
    {
        $this->variant->sets(['id' => 'halflings', 'family' => 'Glyphicons', 'style' => '']);
        $this->variant->addFile('ttf', 'glyphicons-halflings.ttf')->isInstalled(true);

        $this->assertSame(['id' => 'halflings', 'family' => 'Glyphicons'], $this->variant->toFile());
    }

    public function testToFontPrefixesTheFilesWithTheVariantFolder(): void
    {
        $this->variant->sets(['id' => '700italic', 'family' => 'Roboto', 'style' => 'italic', 'weight' => '700', 'display' => 'swap']);
        $this->variant->setBasePath('/fonts/roboto');
        $this->variant->addFile('ttf', 'roboto-700italic.ttf')->addFile('woff2', 'roboto-700italic.woff2');

        $this->assertSame(
            [
                'id' => '700italic',
                'family' => 'Roboto',
                'style' => 'italic',
                'weight' => '700',
                'display' => 'swap',
                'files' => [
                    'ttf' => '/fonts/roboto/700italic/roboto-700italic.ttf',
                    'woff2' => '/fonts/roboto/700italic/roboto-700italic.woff2',
                ],
            ],
            get_object_vars($this->variant->toFont())
        );
    }

    /**
     * Every row has the woff / woff2 its formats ask for: check() never shells out to pyftsubset here.
     */
    #[DataProvider('completeVariants')]
    public function testCheckMarksACompleteVariantInstalled(array $files, array $formats): void
    {
        $this->variant->sets(['id' => 'regular']);
        foreach ($files as $ext => $file) {
            $this->variant->addFile($ext, $file);
        }

        $this->variant->check('roboto', $formats, ['latin']);

        $this->assertSame([true, $files], [$this->variant->isInstalled(), $this->variant->getFiles()]);
    }

    public static function completeVariants(): array
    {
        return [
            'ttf only' => [['ttf' => 'a.ttf'], ['ttf']],
            'every web format' => [['ttf' => 'a.ttf', 'woff' => 'a.woff', 'woff2' => 'a.woff2'], ['ttf', 'woff2', 'woff']],
            'more files than formats' => [['ttf' => 'a.ttf', 'eot' => 'a.eot', 'svg' => 'a.svg'], ['ttf', 'eot']],
        ];
    }

    #[DataProvider('incompleteVariants')]
    public function testCheckRejectsAnIncompleteVariant(array $files, array $formats, string $message): void
    {
        $this->variant->sets(['id' => 'regular']);
        foreach ($files as $ext => $file) {
            $this->variant->addFile($ext, $file);
        }

        $e = $this->thrownBy(fn() => $this->variant->check('roboto', $formats));

        $this->assertSame([FontException::class, $message, false], [$e::class, $e->getMessage(), $this->variant->isInstalled()]);
    }

    public static function incompleteVariants(): array
    {
        return [
            'no ttf' => [['woff2' => 'a.woff2'], ['woff2'], 'Missing TTF file'],
            'no ttf, even with no format asked' => [[], [], 'Missing TTF file'],
            'required format missing' => [['ttf' => 'a.ttf'], ['ttf', 'eot'], 'Missing eot format file'],
            'empty file name' => [['ttf' => 'a.ttf', 'woff' => 'a.woff', 'woff2' => ''], ['ttf', 'woff2', 'woff'], 'Missing woff2 format file'],
        ];
    }

    private function thrownBy(callable $action): \Throwable
    {
        try {
            $action();
        } catch (\Throwable $e) {
            return $e;
        }

        $this->fail('Expected an exception, none was thrown');
    }
}
