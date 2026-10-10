<?php

namespace JDZ\FontManager\Tests\General;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Font;
use JDZ\FontManager\FontVariant;
use JDZ\FontManager\Exceptions\VariantNotAvailableException;

class FontTest extends TestCase
{
    #[DataProvider('ids')]
    public function testGetIdDerivesTheIdFromTheFamily(array $data, string $id): void
    {
        $this->assertSame($id, (new Font())->sets($data)->getId());
    }

    public static function ids(): array
    {
        return [
            'family name' => [['family' => 'Open Sans'], 'open-sans'],
            'mixed-case family name' => [['family' => 'PT Sans Narrow'], 'pt-sans-narrow'],
            'explicit id wins' => [['id' => 'custom-id', 'family' => 'Open Sans'], 'custom-id'],
        ];
    }

    public function testGetPathJoinsTheTrimmedBasePathAndTheId(): void
    {
        $font = (new Font())->sets(['id' => 'roboto'])->setBasePath('/fonts/');

        $this->assertSame('/fonts/roboto', $font->getPath());
    }

    public function testJsonSerializeLeavesOutEmptyFields(): void
    {
        $this->assertSame(['id' => '', 'family' => ''], (new Font())->jsonSerialize());
    }

    public function testJsonEncodeNestsTheFontVariants(): void
    {
        $font = (new Font())->sets([
            'id' => 'roboto',
            'family' => 'Roboto',
            'category' => 'sans-serif',
            'version' => 'v47',
            'lastModified' => '2025-01-08',
            'subsets' => ['latin'],
            'variants' => ['regular'],
        ]);
        $font->isLocal(true);
        $font->isInstalled(true);
        $font->addFontVariant((new FontVariant())->sets(['id' => '700', 'family' => 'Roboto', 'weight' => '700']));

        $this->assertSame(
            '{"id":"roboto","family":"Roboto","category":"sans-serif","version":"v47","lastModified":"2025-01-08","local":true,"installed":true,'
                . '"subsets":["latin"],"variants":["regular","700"],"fontVariants":{"700":{"id":"700","family":"Roboto","style":"normal","weight":"700"}}}',
            json_encode($font)
        );
    }

    #[DataProvider('availableSubsets')]
    public function testCheckAvailableSubsetsAccepts(array $fontSubsets, array $requested): void
    {
        $font = (new Font())->sets(['subsets' => $fontSubsets]);

        $this->expectNotToPerformAssertions();

        $font->checkAvailableSubsets($requested);
    }

    public static function availableSubsets(): array
    {
        return [
            'font without subsets (glyphs, icons)' => [[], ['cyrillic']],
            'declared subsets' => [['latin', 'latin-ext'], ['latin-ext', 'latin']],
            'no subset requested' => [['latin'], []],
        ];
    }

    public function testCheckAvailableSubsetsNamesTheFirstMissingSubset(): void
    {
        $font = (new Font())->sets(['subsets' => ['latin', 'latin-ext']]);

        $e = $this->thrownBy(fn() => $font->checkAvailableSubsets(['latin', 'cyrillic', 'greek']));

        $this->assertSame([\Exception::class, 'Subset cyrillic is not available'], [$e::class, $e->getMessage()]);
    }

    public function testAddVariantKeepsEachVariantOnce(): void
    {
        $font = (new Font())->sets(['variants' => [2 => 'regular']]);

        $font->addVariant('700')->addVariant('regular');

        $this->assertSame(
            [['regular', '700'], true, false],
            [$font->getAvailableVariants(), $font->hasVariant('700'), $font->hasVariant('900')]
        );
    }

    public function testAddFontVariantDeclaresAndRebasesTheVariant(): void
    {
        $font = (new Font())->sets(['id' => 'roboto', 'family' => 'Roboto'])->setBasePath('/fonts');
        $variant = (new FontVariant())->sets(['id' => '700italic']);

        $font->addFontVariant($variant);

        $this->assertSame(
            [['700italic'], ['700italic'], '/fonts/roboto/700italic', true, $variant],
            [$font->getAvailableVariants(), $font->getInstalledVariants(), $variant->getPath(), $font->hasFontVariant('700italic'), $font->getFontVariant('700italic')]
        );
    }

    public function testHasFontVariantsCountsOnlyInstalledVariants(): void
    {
        $font = new Font();
        $variant = (new FontVariant())->sets(['id' => 'regular']);
        $font->addFontVariant($variant);
        $before = $font->hasFontVariants();

        $variant->isInstalled(true);

        $this->assertSame([false, true], [$before, $font->hasFontVariants()]);
    }

    public function testGetFontVariantThrowsForAVariantWithoutFiles(): void
    {
        $font = (new Font())->sets(['family' => 'Roboto', 'variants' => ['regular', '700']]);

        $e = $this->thrownBy(fn() => $font->getFontVariant('900'));

        $this->assertSame(
            [VariantNotAvailableException::class, '', "Font variant not available .. \nFont: Roboto - Variant: 900\nAvailable variants: regular, 700"],
            [$e::class, $e->getMessage(), $e->getFontError()]
        );
    }

    #[DataProvider('variantFamilies')]
    public function testToFontMergesFontAndVariantData(string $variantFamily, string $family): void
    {
        $font = (new Font())->sets(['id' => 'roboto', 'family' => 'Roboto', 'version' => 'v47'])->setBasePath('/fonts');
        $font->isLocal(true);
        $font->addFontVariant(
            (new FontVariant())
                ->sets(['id' => '700', 'family' => $variantFamily, 'weight' => '700', 'display' => 'swap'])
                ->addFile('ttf', 'roboto-700.ttf')
        );

        $this->assertSame(
            [
                'id' => 'roboto',
                'family' => $family,
                'style' => 'normal',
                'weight' => '700',
                'display' => 'swap',
                'files' => ['ttf' => '/fonts/roboto/700/roboto-700.ttf'],
                'version' => 'v47',
                'local' => true,
            ],
            get_object_vars($font->toFont('700'))
        );
    }

    public static function variantFamilies(): array
    {
        return [
            'variant without a family takes the font family' => ['', 'Roboto'],
            'variant family is kept' => ['Roboto Flex', 'Roboto Flex'],
        ];
    }

    public function testToFileAndToStorageShapes(): void
    {
        $font = (new Font())->sets([
            'id' => 'my-font',
            'family' => 'My Font',
            'version' => 'V2',
            'variants' => [3 => 'regular', 5 => 'bold'],
        ]);
        $font->isLocal(true);

        $this->assertSame(
            [
                ['id' => 'my-font', 'family' => 'My Font', 'local' => true, 'version' => 'V2'],
                ['local' => true, 'id' => 'my-font', 'family' => 'My Font', 'category' => '', 'version' => 'V2', 'lastModified' => '', 'variants' => ['regular', 'bold']],
            ],
            [$font->toFile(), $font->toStorage()]
        );
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
