<?php

namespace JDZ\FontManager\Tests\Providers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Tests\Support\Arrays;
use JDZ\FontManager\Tests\Support\Files;
use JDZ\FontManager\Tests\Support\LocalGooglefontsProvider;

/**
 * The provider's own curl + parsing code runs against local files (file:// URLs,
 * see LocalGooglefontsProvider): committed fixtures for real API responses,
 * temp files for the broken ones. No network.
 */
class GooglefontsProviderTest extends TestCase
{
    // GooglefontsProvider reads $_ENV, not getenv(): putenv() would change nothing
    private ?string $originalApiKey;
    private string $root;

    protected function setUp(): void
    {
        $this->originalApiKey = $_ENV['GOOGLE_FONTS_API_KEY'] ?? null;
        $this->root = Files::tempDir();
    }

    protected function tearDown(): void
    {
        if (null === $this->originalApiKey) {
            unset($_ENV['GOOGLE_FONTS_API_KEY']);
        } else {
            $_ENV['GOOGLE_FONTS_API_KEY'] = $this->originalApiKey;
        }

        Files::removeTree($this->root);
    }

    public function testListFormatsTheCatalog(): void
    {
        $provider = new LocalGooglefontsProvider(self::fixture('webfonts.json'), 'test-key');

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

    public function testInfosBuildsOneTtfVariantPerFile(): void
    {
        $provider = new LocalGooglefontsProvider(self::fixture('webfonts-open-sans.json'), 'test-key');
        $ttf = 'https://fonts.gstatic.com/s/opensans/v40/open-sans-';

        $this->assertSame(
            [
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'version' => 'v40',
                'lastModified' => '2024-05-01',
                'category' => 'sans-serif',
                'variants' => ['300', 'regular', 'italic', '700', '700italic'],
                'subsets' => ['cyrillic', 'greek', 'latin', 'latin-ext'],
                'fontVariants' => [
                    '300' => ['id' => '300', 'style' => 'normal', 'weight' => '300', 'display' => 'swap', 'files' => ['ttf' => $ttf . '300.ttf']],
                    'regular' => ['id' => 'regular', 'style' => 'normal', 'weight' => '400', 'display' => 'swap', 'files' => ['ttf' => $ttf . 'regular.ttf']],
                    'italic' => ['id' => 'italic', 'style' => 'italic', 'weight' => '400', 'display' => 'swap', 'files' => ['ttf' => $ttf . 'italic.ttf']],
                    '700' => ['id' => '700', 'style' => 'normal', 'weight' => '700', 'display' => 'swap', 'files' => ['ttf' => $ttf . '700.ttf']],
                    '700italic' => ['id' => '700italic', 'style' => 'italic', 'weight' => '700', 'display' => 'swap', 'files' => ['ttf' => $ttf . '700italic.ttf']],
                ],
            ],
            Arrays::export($provider->infos('open-sans', 'Open Sans'))
        );
    }

    #[DataProvider('unusableInfos')]
    public function testInfosReturnsFalseOnAnUnusableResponse(?string $body): void
    {
        $provider = new LocalGooglefontsProvider($this->response($body), 'test-key');

        $this->assertFalse($provider->infos('open-sans', 'Open Sans'));
    }

    public static function unusableInfos(): array
    {
        return [
            'unreachable' => [null],
            'empty body' => [''],
            'JSON string' => ['"Not Found"'],
            'invalid JSON' => ['{"items": ['],
            'API error' => ['{"error": {"code": 400, "message": "API key not valid. Please pass a valid API key.", "status": "INVALID_ARGUMENT"}}'],
            'no matching family' => ['{"kind": "webfonts#webfontList", "items": []}'],
            'family without files' => ['{"kind": "webfonts#webfontList", "items": [{"family": "Open Sans"}]}'],
        ];
    }

    /**
     * The failure message names the requested URL, so it shows which API key the constructor picked.
     */
    #[DataProvider('apiKeys')]
    public function testListFailureNamesTheRequestedUrl(?string $argument, ?string $env, string $key): void
    {
        if (null === $env) {
            unset($_ENV['GOOGLE_FONTS_API_KEY']);
        } else {
            $_ENV['GOOGLE_FONTS_API_KEY'] = $env;
        }
        $path = $this->response(null);
        $provider = new LocalGooglefontsProvider($path, $argument);

        $e = $this->thrownBy(fn() => $provider->list());

        $this->assertSame(
            [\Exception::class, 'Error updating font list from ' . Files::url($path) . '?key=' . $key],
            [$e::class, $e->getMessage()]
        );
    }

    public static function apiKeys(): array
    {
        return [
            'key argument' => ['arg-key', null, 'arg-key'],
            'key from $_ENV' => [null, 'env-key', 'env-key'],
            'argument wins over $_ENV' => ['arg-key', 'env-key', 'arg-key'],
            'empty argument wins over $_ENV' => ['', 'env-key', ''],
            'no key anywhere' => [null, null, ''],
        ];
    }

    public function testListFailsOnAnEmptyResponse(): void
    {
        $path = $this->response('');
        $provider = new LocalGooglefontsProvider($path, 'test-key');

        $e = $this->thrownBy(fn() => $provider->list());

        $this->assertSame(
            [\Exception::class, 'Error updating font list from ' . Files::url($path) . '?key=test-key'],
            [$e::class, $e->getMessage()]
        );
    }

    private static function fixture(string $name): string
    {
        return realpath(__DIR__ . '/../Fixtures/googlefonts/' . $name);
    }

    /** A temp file holding $body; null: a path where nothing exists */
    private function response(?string $body): string
    {
        $path = $this->root . '/response.json';
        if (null !== $body) {
            file_put_contents($path, $body);
        }

        return $path;
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
