<?php

namespace JDZ\FontManager\Tests\Providers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Tests\Support\Arrays;
use JDZ\FontManager\Tests\Support\Files;
use JDZ\FontManager\Tests\Support\LocalMrandtlfProvider;

/**
 * The provider's own curl + parsing code runs against local files (file:// URLs,
 * see LocalMrandtlfProvider): committed fixtures for real API responses, temp
 * files for the broken ones. No network.
 */
class MrandtlfProviderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Files::tempDir();
    }

    protected function tearDown(): void
    {
        Files::removeTree($this->root);
    }

    public function testListFormatsTheCatalog(): void
    {
        $provider = new LocalMrandtlfProvider(self::fixture('fonts.json'));

        $this->assertSame(
            [
                'open-sans' => [
                    'id' => 'open-sans',
                    'family' => 'Open Sans',
                    'version' => 'v40',
                    'lastModified' => '2024-05-01',
                    'category' => 'sans-serif',
                    'variants' => ['300', 'regular', 'italic', '700', '700italic'],
                    'subsets' => ['cyrillic', 'greek', 'latin', 'latin-ext'],
                ],
                'roboto-mono' => [
                    'id' => 'roboto-mono',
                    'family' => 'Roboto Mono',
                    'version' => 'v23',
                    'lastModified' => '2024-02-29',
                    'category' => 'monospace',
                    'variants' => ['regular', '700'],
                    'subsets' => ['latin'],
                ],
            ],
            Arrays::export($provider->list())
        );
    }

    #[DataProvider('unusableLists')]
    public function testListFailureNamesTheRequestedUrl(?string $body): void
    {
        $path = $this->root . '/fonts.json';
        if (null !== $body) {
            file_put_contents($path, $body);
        }
        $provider = new LocalMrandtlfProvider($path);

        $e = $this->thrownBy(fn() => $provider->list());

        $this->assertSame(
            [\Exception::class, 'Error updating font list from ' . Files::url($path)],
            [$e::class, $e->getMessage()]
        );
    }

    public static function unusableLists(): array
    {
        return [
            'unreachable' => [null],
            'empty body' => [''],
        ];
    }

    public function testInfosCollectsTheFilesOfEveryVariant(): void
    {
        $provider = new LocalMrandtlfProvider(self::fixture('fonts'));
        $url = 'https://fonts.gstatic.com/l/open-sans-v40-latin-';

        $this->assertSame(
            [
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'version' => 'v40',
                'lastModified' => '2024-05-01',
                'category' => 'sans-serif',
                'variants' => ['300', 'regular', '700italic'],
                'subsets' => ['cyrillic', 'greek', 'latin', 'latin-ext'],
                'fontVariants' => [
                    '300' => ['id' => '300', 'style' => 'normal', 'weight' => '300', 'display' => 'swap', 'files' => [
                        'ttf' => $url . '300.ttf',
                        'woff' => $url . '300.woff',
                        'woff2' => $url . '300.woff2',
                        'eot' => $url . '300.eot',
                        'svg' => $url . '300.svg',
                    ]],
                    'regular' => ['id' => 'regular', 'style' => 'normal', 'weight' => '400', 'display' => 'swap', 'files' => [
                        'ttf' => $url . 'regular.ttf',
                        'woff2' => $url . 'regular.woff2',
                    ]],
                    '700italic' => ['id' => '700italic', 'style' => 'italic', 'weight' => '700', 'display' => 'swap', 'files' => [
                        'ttf' => $url . '700italic.ttf',
                        'woff2' => $url . '700italic.woff2',
                    ]],
                ],
            ],
            Arrays::export($provider->infos('open-sans', 'Open Sans'))
        );
    }

    #[DataProvider('unusableInfos')]
    public function testInfosReturnsFalseOnAnUnusableResponse(?string $body): void
    {
        mkdir($this->root . '/fonts');
        if (null !== $body) {
            file_put_contents($this->root . '/fonts/open-sans', $body);
        }
        $provider = new LocalMrandtlfProvider($this->root . '/fonts');

        $this->assertFalse($provider->infos('open-sans', 'Open Sans'));
    }

    public static function unusableInfos(): array
    {
        return [
            'unreachable' => [null],
            'empty body' => [''],
            'JSON string' => ['"Not Found"'],
            'invalid JSON' => ['{"id": "open-sans", '],
            'empty array' => ['[]'],
            'font without variants' => ['{"id": "open-sans", "family": "Open Sans", "variants": []}'],
        ];
    }

    private static function fixture(string $name): string
    {
        return realpath(__DIR__ . '/../Fixtures/mranftl/' . $name);
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
