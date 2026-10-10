<?php

namespace JDZ\FontManager\Tests\General;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use JDZ\FontManager\FontsDb;
use JDZ\FontManager\Exceptions\FontException;
use JDZ\FontManager\Exceptions\FontNotAvailableException;
use JDZ\FontManager\Exceptions\VariantNotAvailableException;
use JDZ\FontManager\Tests\Support\FakeProvider;
use JDZ\FontManager\Tests\Support\Files;
use Symfony\Component\Yaml\Yaml;

class FontsDbTest extends TestCase
{
    // FontsDb::__destruct() saves into its fonts folder: every FontsDb of a test is a
    // local of the test method, so it is destroyed (and writes) before tearDown()
    // removes the whole temp root
    private string $root;
    private string $fontsPath;
    private string $remotePath;

    protected function setUp(): void
    {
        $this->root = Files::tempDir();
        $this->fontsPath = $this->root . '/fonts';
        $this->remotePath = $this->root . '/remote';
        mkdir($this->fontsPath);
        mkdir($this->remotePath);
    }

    protected function tearDown(): void
    {
        Files::removeTree($this->root);
    }

    public function testLoadThrowsExceptionWhenFontsPathNotExists(): void
    {
        $db = new FontsDb($this->fontsPath . '/missing');

        $e = $this->thrownBy(fn() => $db->load());

        $this->assertSame(
            [\RuntimeException::class, 'Fonts folder not found in ' . $this->fontsPath . '/missing'],
            [$e::class, $e->getMessage()]
        );
    }

    public function testInstallUnknownFontWithoutDistantLoadThrows(): void
    {
        $provider = new FakeProvider();
        $db = (new FontsDb($this->fontsPath))->addProvider($provider)->load();

        // no loadDistantFonts() call: install() must ask the providers itself
        // and report the font as unavailable, not read an undefined key
        $e = $this->thrownBy(fn() => $db->install('No Such Family', 400, 'normal'));

        $this->assertSame(
            [
                FontNotAvailableException::class,
                'Font is not available via any provider ..',
                "Font not available .. Font is not available via any provider ..\nFont: No Such Family - Weight: 400 - Style: normal - Variant: regular",
                1,
            ],
            [$e::class, $e->getMessage(), $e->getFontError(), $provider->listCalls]
        );
    }

    /**
     * Each row queries 'Roboto Mono', indexed with no variant at all: check() finds the
     * font by its id, then reports the parsed query in a VariantNotAvailableException.
     */
    #[DataProvider('parsedQueries')]
    public function testCheckReportsTheParsedQuery(array $query, string $parsed): void
    {
        $this->seed();
        $db = (new FontsDb($this->fontsPath))->load();

        $e = $this->thrownBy(fn() => $db->check(...$query));

        $this->assertSame(
            [VariantNotAvailableException::class, "Font variant not available .. \n" . $parsed],
            [$e::class, $e->getFontError()]
        );
    }

    public static function parsedQueries(): array
    {
        return [
            'family name' => [['Roboto Mono'], 'Font: Roboto Mono - Variant: regular'],
            'family id' => [['roboto-mono'], 'Font: Roboto Mono - Variant: regular'],
            'lower-case family name' => [['roboto mono'], 'Font: Roboto Mono - Variant: regular'],
            'weight in the query' => [['Roboto Mono/500'], 'Font: Roboto Mono - Weight: 500 - Variant: 500'],
            'italic weight in the query' => [['Roboto Mono/500italic'], 'Font: Roboto Mono - Weight: 500 - Style: italic - Variant: 500italic'],
            'regular in the query' => [['Roboto Mono/regular'], 'Font: Roboto Mono - Variant: regular'],
            'extralight' => [['Roboto Mono/extralight'], 'Font: Roboto Mono - Weight: 100 - Variant: 100'],
            'light' => [['Roboto Mono/light'], 'Font: Roboto Mono - Weight: 300 - Variant: 300'],
            'bold' => [['Roboto Mono/bold'], 'Font: Roboto Mono - Weight: 700 - Variant: 700'],
            'extrabold' => [['Roboto Mono/extrabold'], 'Font: Roboto Mono - Weight: 900 - Variant: 900'],
            'named weight with italic' => [['Roboto Mono/lightitalic'], 'Font: Roboto Mono - Weight: 300 - Style: italic - Variant: 300italic'],
            'weight argument' => [['Roboto Mono', 500], 'Font: Roboto Mono - Weight: 500 - Variant: 500'],
            'named weight argument' => [['Roboto Mono', 'bold'], 'Font: Roboto Mono - Weight: 700 - Variant: 700'],
            'weight 400 is regular' => [['Roboto Mono', 400], 'Font: Roboto Mono - Weight: 400 - Variant: regular'],
            'weight 400 italic is italic' => [['Roboto Mono', 400, 'italic'], 'Font: Roboto Mono - Weight: 400 - Style: italic - Variant: italic'],
            'italic style without weight' => [['Roboto Mono', null, 'italic'], 'Font: Roboto Mono - Style: italic - Variant: italic'],
            'weight and italic style arguments' => [['Roboto Mono', 700, 'italic'], 'Font: Roboto Mono - Weight: 700 - Style: italic - Variant: 700italic'],
            'query weight wins over the argument' => [['Roboto Mono/700', 300], 'Font: Roboto Mono - Weight: 700 - Variant: 700'],
            'style argument applies to the query weight' => [['Roboto Mono/700', null, 'italic'], 'Font: Roboto Mono - Weight: 700 - Style: italic - Variant: 700italic'],
            'subsets in the query' => [['Roboto Mono@latin,latin-ext'], 'Font: Roboto Mono - Variant: regular - Subsets: latin,latin-ext'],
            'subsets argument' => [['Roboto Mono', null, null, ['greek']], 'Font: Roboto Mono - Variant: regular - Subsets: greek'],
            'query subsets win over the argument' => [['Roboto Mono@cyrillic', null, null, ['latin']], 'Font: Roboto Mono - Variant: regular - Subsets: cyrillic'],
            'empty query subsets keep the argument' => [['Roboto Mono@', null, null, ['latin']], 'Font: Roboto Mono - Variant: regular - Subsets: latin'],
            'full query' => [['roboto-mono/700italic@latin,greek'], 'Font: Roboto Mono - Weight: 700 - Style: italic - Variant: 700italic - Subsets: latin,greek'],
        ];
    }

    /**
     * [isAvailable(), isInstalled(), has(), get() !== false] of a query on the seeded folder.
     */
    #[DataProvider('queryAnswers')]
    public function testQueryAnswers(array $query, bool $available, bool $installed): void
    {
        $this->seed();
        $db = (new FontsDb($this->fontsPath))->load();

        $this->assertSame(
            [$available, $installed, $installed, $installed],
            [$db->isAvailable(...$query), $db->isInstalled(...$query), $db->has(...$query), false !== $db->get(...$query)]
        );
    }

    public static function queryAnswers(): array
    {
        return [
            'installed variant' => [['Open Sans/700italic'], true, true],
            'installed variant, declared subset' => [['Open Sans/700italic@latin-ext'], true, true],
            'declared variant without a folder' => [['Open Sans/700'], true, false],
            'variant folder without a ttf' => [['Open Sans/300'], true, false],
            'undeclared variant' => [['Open Sans/900'], false, false],
            'undeclared subset' => [['Open Sans@cyrillic'], false, false],
            'unknown family' => [['Lato'], false, false],
            'folder without font.yml' => [['not-a-font'], false, false],
            'font without subsets takes any subset' => [['Glyphicons/halflings@greek'], true, true],
            'unknown custom variant' => [['Glyphicons/social'], false, false],
        ];
    }

    #[DataProvider('installedVariantQueries')]
    public function testCheckAcceptsAnInstalledVariant(array $query): void
    {
        $this->seed();
        $db = (new FontsDb($this->fontsPath))->load();

        $this->expectNotToPerformAssertions();

        $db->check(...$query);
    }

    public static function installedVariantQueries(): array
    {
        return [
            'family name' => [['Open Sans']],
            'family id' => [['open-sans']],
            'lower-case name with subsets' => [['open sans@latin,latin-ext']],
            'italic alone in the query' => [['Open Sans/italic']],
            'weight 400 italic' => [['Open Sans', 400, 'italic']],
            'weight 400 normal' => [['Open Sans', '400', 'normal']],
            'bolditalic in the query' => [['Open Sans/bolditalic']],
            'custom variant name' => [['Glyphicons/halflings']],
        ];
    }

    #[DataProvider('checkFailures')]
    public function testCheckRejects(array $query, string $class, string $message, string $fontError): void
    {
        $this->seed();
        $db = (new FontsDb($this->fontsPath))->load();

        $e = $this->thrownBy(fn() => $db->check(...$query));

        $this->assertSame([$class, $message, $fontError], [$e::class, $e->getMessage(), $e->getFontError()]);
    }

    public static function checkFailures(): array
    {
        $variants = "\nAvailable variants: regular, italic, 700, 700italic, 300";

        return [
            'unknown family' => [
                ['Lato/700'],
                FontNotAvailableException::class,
                '',
                "Font not available .. \nFont: Lato - Weight: 700 - Variant: 700\nTry to load with prefetch to check online availability",
            ],
            'undeclared variant' => [
                ['Open Sans/900'],
                VariantNotAvailableException::class,
                '',
                "Font variant not available .. \nFont: Open Sans - Weight: 900 - Variant: 900" . $variants,
            ],
            'undeclared subset' => [
                ['Open Sans@cyrillic'],
                VariantNotAvailableException::class,
                '',
                "Font variant not available .. \nFont: Open Sans - Variant: regular - Subsets: cyrillic" . $variants,
            ],
            'declared variant without a folder' => [
                ['Open Sans', 700],
                FontException::class,
                'Font variant is not installed but is available',
                "Font Error .. Font variant is not installed but is available\nFont: Open Sans - Weight: 700 - Variant: 700",
            ],
            'variant folder without a ttf' => [
                ['Open Sans/300'],
                FontException::class,
                'Font variant is not installed but is available',
                "Font Error .. Font variant is not installed but is available\nFont: Open Sans - Weight: 300 - Variant: 300",
            ],
        ];
    }

    #[DataProvider('installedVariants')]
    public function testGetReturnsTheVariantData(array $query, array $expected): void
    {
        $this->seed();
        $db = (new FontsDb($this->fontsPath))->load();

        $expected['files'] = array_map(fn(string $file): string => $this->fontsPath . '/' . $file, $expected['files']);

        $this->assertSame($expected, $this->variantData($db->get(...$query)));
    }

    public static function installedVariants(): array
    {
        return [
            'provider font' => [['Open Sans/700italic'], [
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'style' => 'italic',
                'weight' => '700',
                'display' => 'swap',
                'files' => [
                    'ttf' => 'open-sans/700italic/open-sans-700italic.ttf',
                    'woff' => 'open-sans/700italic/open-sans-700italic.woff',
                    'woff2' => 'open-sans/700italic/open-sans-700italic.woff2',
                ],
                'version' => 'v40',
                'local' => false,
            ]],
            'local font, custom variant' => [['Glyphicons/halflings'], [
                'id' => 'glyphicons',
                'family' => 'Glyphicons',
                'style' => '',
                'weight' => '',
                'display' => '',
                'files' => [
                    'ttf' => 'glyphicons/halflings/glyphicons-halflings-regular.ttf',
                    'woff' => 'glyphicons/halflings/glyphicons-halflings-regular.woff',
                    'woff2' => 'glyphicons/halflings/glyphicons-halflings-regular.woff2',
                ],
                'version' => 'V1',
                'local' => true,
            ]],
        ];
    }

    public function testConstructorNormalisesTheFontsPath(): void
    {
        $this->seed();
        $db = (new FontsDb(str_replace('/', '\\', $this->fontsPath) . '\\'))->load();

        $this->assertSame(
            $this->fontsPath . '/glyphicons/halflings/glyphicons-halflings-regular.ttf',
            $db->get('Glyphicons/halflings')->files['ttf']
        );
    }

    public function testLoadDerivesAMissingVariantIdFromWeightAndStyle(): void
    {
        $this->writeYaml('open-sans/font.yml', ['id' => 'open-sans', 'family' => 'Open Sans']);
        $this->writeVariant('open-sans', '700italic', ['id' => '', 'style' => 'italic', 'weight' => 700], ['ttf', 'woff', 'woff2']);
        $db = (new FontsDb($this->fontsPath))->load();

        $this->assertSame(
            [
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'style' => 'italic',
                'weight' => '700',
                'display' => '',
                'files' => [
                    'ttf' => $this->fontsPath . '/open-sans/700italic/open-sans-700italic.ttf',
                    'woff' => $this->fontsPath . '/open-sans/700italic/open-sans-700italic.woff',
                    'woff2' => $this->fontsPath . '/open-sans/700italic/open-sans-700italic.woff2',
                ],
                'version' => 'V1',
                'local' => false,
            ],
            $this->variantData($db->get('Open Sans/700italic'))
        );
    }

    public function testLoadRejectsAnUnparsableIndex(): void
    {
        $index = $this->fontsPath . '/fonts.yml';
        file_put_contents($index, "fonts: [unclosed\n");
        $parseError = $this->thrownBy(fn() => Yaml::parseFile($index));
        $db = new FontsDb($this->fontsPath);

        $e = $this->thrownBy(fn() => $db->load());

        $this->assertSame(
            [\Exception::class, 'Unable to parse the YAML file in ' . $index . ' .. ' . $parseError->getMessage()],
            [$e::class, $e->getMessage()]
        );
    }

    public function testSaveReportsAnUnwritableFontsPath(): void
    {
        $path = $this->root . '/a-file';
        file_put_contents($path, '');
        $db = new FontsDb($path);

        $e = $this->thrownBy(fn() => $db->save());

        // a folder in its place, so that the destructor's own save() succeeds
        unlink($path);
        mkdir($path);

        $this->assertSame(
            [\Exception::class, 'Error dumping the Yml file .. Failed to create "' . $path . '": mkdir(): File exists'],
            [$e::class, $e->getMessage()]
        );
    }

    public function testInstallCopiesTheProviderFilesAndWritesTheIndex(): void
    {
        $provider = $this->openSansProvider();
        $db = (new FontsDb($this->fontsPath))->addProvider($provider)->load();

        $db->install('Open Sans/700italic@latin');

        $this->assertSame([['open-sans', 'Open Sans']], $provider->infosRequests);
        $this->assertSame([
            'fonts.yml' => [[
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'category' => 'sans-serif',
                'version' => 'v1',
                'lastModified' => '2024-01-01',
                'variants' => ['regular', '700italic'],
                'subsets' => ['latin', 'latin-ext'],
            ]],
            'open-sans/700italic/font.yml' => ['id' => '700italic', 'family' => 'Open Sans', 'style' => 'italic', 'weight' => '700', 'display' => 'swap'],
            'open-sans/700italic/open-sans-700italic.ttf' => 'ttf bytes',
            'open-sans/700italic/open-sans-700italic.woff' => 'woff bytes',
            'open-sans/700italic/open-sans-700italic.woff2' => 'woff2 bytes',
            'open-sans/font.yml' => ['id' => 'open-sans', 'family' => 'Open Sans', 'category' => 'sans-serif', 'version' => 'v1', 'lastModified' => '2024-01-01'],
        ], $this->folderContents());
    }

    public function testInstalledVariantSurvivesAReload(): void
    {
        $db = (new FontsDb($this->fontsPath))->addProvider($this->openSansProvider())->load();
        $db->install('Open Sans/700italic');
        $installed = $this->variantData($db->get('Open Sans/700italic'));
        unset($db);

        $reloaded = (new FontsDb($this->fontsPath))->load();

        $this->assertSame($installed, $this->variantData($reloaded->get('Open Sans/700italic')));
    }

    public function testInstallDerivesAMissingVariantIdFromWeightAndStyle(): void
    {
        $infos = self::infos('open-sans', 'Open Sans', ['700italic' => ['italic', '700', [
            'ttf' => $this->remoteFile('open-sans.ttf', 'ttf bytes'),
            'woff' => $this->remoteFile('open-sans.woff', 'woff bytes'),
            'woff2' => $this->remoteFile('open-sans.woff2', 'woff2 bytes'),
        ]]]);
        $infos['fontVariants']['700italic']['id'] = '';
        $provider = new FakeProvider([self::catalogFont('open-sans', 'Open Sans', ['700italic'])], ['open-sans' => $infos]);
        $db = (new FontsDb($this->fontsPath))->addProvider($provider)->load();

        $db->install('Open Sans/700italic');

        $this->assertSame(
            [
                'fonts.yml',
                'open-sans/700italic/font.yml',
                'open-sans/700italic/open-sans-700italic.ttf',
                'open-sans/700italic/open-sans-700italic.woff',
                'open-sans/700italic/open-sans-700italic.woff2',
                'open-sans/font.yml',
            ],
            array_keys($this->folderContents())
        );
    }

    public function testInstallOfAnInstalledVariantAsksNoProvider(): void
    {
        $this->seed();
        $provider = new FakeProvider([self::catalogFont('open-sans', 'Open Sans', ['regular'])]);
        $db = (new FontsDb($this->fontsPath))->addProvider($provider)->load();

        $db->install('Open Sans/700italic');

        $this->assertSame([0, []], [$provider->listCalls, $provider->infosRequests]);
    }

    public function testInstallCompletesAHalfInstalledVariant(): void
    {
        $this->writeYaml('open-sans/font.yml', ['id' => 'open-sans', 'family' => 'Open Sans']);
        $this->writeYaml('open-sans/700italic/font.yml', ['id' => '700italic', 'family' => 'Open Sans', 'style' => 'italic', 'weight' => '700']);
        file_put_contents($this->fontsPath . '/open-sans/700italic/OpenSans-BoldItalic.ttf', 'local ttf');
        $provider = new FakeProvider([], [
            'open-sans' => self::infos('open-sans', 'Open Sans', ['700italic' => ['italic', '700', [
                'ttf' => $this->remoteFile('open-sans.ttf', 'remote ttf'),
                'eot' => $this->remoteFile('open-sans.eot', 'remote eot'),
            ]]]),
        ]);
        $db = (new FontsDb($this->fontsPath, ['ttf', 'eot']))->addProvider($provider)->load();

        $db->install('Open Sans/700italic');

        $this->assertSame([
            'fonts.yml' => [[
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'category' => '',
                'version' => 'V1',
                'lastModified' => '',
                'variants' => ['700italic'],
            ]],
            'open-sans/700italic/OpenSans-BoldItalic.ttf' => 'local ttf',
            'open-sans/700italic/font.yml' => ['id' => '700italic', 'family' => 'Open Sans', 'style' => 'italic', 'weight' => '700'],
            'open-sans/700italic/open-sans-700italic.eot' => 'remote eot',
            'open-sans/font.yml' => ['id' => 'open-sans', 'family' => 'Open Sans', 'version' => 'V1'],
        ], $this->folderContents());
    }

    #[DataProvider('installFailures')]
    public function testInstallRejects(array $query, array $index, ?array $catalog, array $infos, string $class, string $message, string $fontError): void
    {
        if ([] !== $index) {
            $this->writeYaml('fonts.yml', $index);
        }
        $db = new FontsDb($this->fontsPath);
        if (null !== $catalog) {
            $db->addProvider(new FakeProvider($catalog, $infos));
        }
        $db->load();

        $e = $this->thrownBy(fn() => $db->install(...$query));

        $this->assertSame([$class, $message, $fontError], [$e::class, $e->getMessage(), $e->getFontError()]);
    }

    public static function installFailures(): array
    {
        $openSans = self::catalogFont('open-sans', 'Open Sans', ['regular', '700']);

        return [
            'local font' => [
                ['My Font'],
                [['local' => true, 'id' => 'my-font', 'family' => 'My Font', 'variants' => ['regular']]],
                null,
                [],
                FontException::class,
                'Font is local and cannot be installed ..',
                "Font Error .. Font is local and cannot be installed ..\nFont: My Font - Variant: regular",
            ],
            'catalog font no provider serves' => [
                ['Open Sans/700'],
                [],
                [$openSans],
                [],
                FontNotAvailableException::class,
                'Font is not available via any provider ..',
                "Font not available .. Font is not available via any provider ..\nFont: Open Sans - Weight: 700 - Variant: 700",
            ],
            'indexed font, no provider registered' => [
                ['Open Sans/700'],
                [$openSans],
                null,
                [],
                FontNotAvailableException::class,
                'Font is not available via any provider ..',
                "Font not available .. Font is not available via any provider ..\nFont: Open Sans - Weight: 700 - Variant: 700\nTry to load with prefetch to check online availability",
            ],
            'variant the provider does not serve' => [
                ['Open Sans/900'],
                [],
                [$openSans],
                ['open-sans' => self::infos('open-sans', 'Open Sans', ['regular' => ['normal', '400', []], '700' => ['normal', '700', []]])],
                VariantNotAvailableException::class,
                '',
                "Font variant not available .. \nFont: Open Sans - Weight: 900 - Variant: 900\nAvailable variants: regular, 700",
            ],
        ];
    }

    public function testProviderCatalogsAreLoadedOnce(): void
    {
        $provider = new FakeProvider([self::catalogFont('lobster', 'Lobster', ['regular'])]);
        $db = (new FontsDb($this->fontsPath))->addProvider($provider)->load(true);

        $db->loadDistantFonts();

        $this->assertSame([1, true], [$provider->listCalls, $db->isAvailable('Lobster')]);
    }

    public function testProviderVariantsAreMergedIntoAnIndexedFont(): void
    {
        $this->seed();
        $provider = new FakeProvider([self::catalogFont('open-sans', 'Open Sans', ['regular', '800'])]);
        $db = (new FontsDb($this->fontsPath))->addProvider($provider)->load();
        $before = $db->isAvailable('Open Sans/800');

        $db->loadDistantFonts();
        $e = $this->thrownBy(fn() => $db->check('Open Sans/900'));

        $this->assertSame(
            [false, true, "Font variant not available .. \nFont: Open Sans - Weight: 900 - Variant: 900\nAvailable variants: regular, italic, 700, 700italic, 300, 800"],
            [$before, $db->isAvailable('Open Sans/800'), $e->getFontError()]
        );
    }

    /**
     * The seeded fonts folder:
     * - fonts.yml: open-sans (4 variants, 2 subsets), roboto-mono (no variant)
     * - open-sans/: regular, italic, 700italic installed; 300 with a woff2 only; notes/ without font.yml
     * - glyphicons/ (local, no subsets): halflings installed
     * - not-a-font/ without font.yml
     * Every installed variant has its ttf, woff and woff2, so nothing shells out to pyftsubset.
     */
    private function seed(): void
    {
        $this->writeYaml('fonts.yml', [
            [
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'category' => 'sans-serif',
                'version' => 'v40',
                'lastModified' => '2024-05-01',
                'variants' => ['regular', 'italic', '700', '700italic'],
                'subsets' => ['latin', 'latin-ext'],
            ],
            ['id' => 'roboto-mono', 'family' => 'Roboto Mono', 'variants' => []],
        ]);

        $this->writeYaml('open-sans/font.yml', ['id' => 'open-sans', 'family' => 'Open Sans', 'category' => 'sans-serif', 'version' => 'v40', 'lastModified' => '2024-05-01']);
        foreach (['regular' => ['normal', '400'], 'italic' => ['italic', '400'], '700italic' => ['italic', '700']] as $variant => [$style, $weight]) {
            $this->writeVariant('open-sans', $variant, ['id' => $variant, 'family' => 'Open Sans', 'style' => $style, 'weight' => $weight, 'display' => 'swap'], ['ttf', 'woff', 'woff2']);
        }
        $this->writeVariant('open-sans', '300', ['id' => '300', 'family' => 'Open Sans', 'style' => 'normal', 'weight' => '300', 'display' => 'swap'], ['woff2']);
        mkdir($this->fontsPath . '/open-sans/notes');

        $this->writeYaml('glyphicons/font.yml', ['id' => 'glyphicons', 'family' => 'Glyphicons', 'local' => true, 'version' => 'V1']);
        $this->writeVariant('glyphicons', 'halflings', ['id' => 'halflings', 'family' => 'Glyphicons'], ['ttf', 'woff', 'woff2'], 'glyphicons-halflings-regular');

        mkdir($this->fontsPath . '/not-a-font');
    }

    /** A provider listing open-sans and lobster, serving the 700italic files of open-sans from the remote folder */
    private function openSansProvider(): FakeProvider
    {
        return new FakeProvider(
            [
                self::catalogFont('open-sans', 'Open Sans', ['regular', '700italic'], ['latin', 'latin-ext']),
                self::catalogFont('lobster', 'Lobster', ['regular']),
            ],
            [
                'open-sans' => self::infos('open-sans', 'Open Sans', ['700italic' => ['italic', '700', [
                    'ttf' => $this->remoteFile('open-sans-bold-italic.ttf', 'ttf bytes'),
                    'woff2' => $this->remoteFile('open-sans-bold-italic.woff2', 'woff2 bytes'),
                    'woff' => $this->remoteFile('open-sans-bold-italic.woff', 'woff bytes'),
                    'eot' => $this->remoteFile('open-sans-bold-italic.eot', 'eot bytes'),
                ]]]),
            ]
        );
    }

    private static function catalogFont(string $id, string $family, array $variants, array $subsets = []): array
    {
        return [
            'id' => $id,
            'family' => $family,
            'version' => 'v1',
            'lastModified' => '2024-01-01',
            'category' => 'sans-serif',
            'variants' => $variants,
            'subsets' => $subsets,
        ];
    }

    /** @param array<string, array{0: string, 1: string, 2: array<string, string>}> $variants variant id => [style, weight, files] */
    private static function infos(string $id, string $family, array $variants): array
    {
        $fontVariants = [];
        foreach ($variants as $variantId => [$style, $weight, $files]) {
            $fontVariants[$variantId] = ['id' => (string)$variantId, 'style' => $style, 'weight' => $weight, 'display' => 'swap', 'files' => $files];
        }

        return [
            'id' => $id,
            'family' => $family,
            'version' => 'v1',
            'lastModified' => '2024-01-01',
            'category' => 'sans-serif',
            'variants' => array_map('strval', array_keys($variants)),
            'fontVariants' => $fontVariants,
        ];
    }

    private function writeYaml(string $path, array $data): void
    {
        $file = $this->fontsPath . '/' . $path;
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0777, true);
        }
        file_put_contents($file, Yaml::dump($data, 4));
    }

    /** A variant folder: its font.yml, plus one "<basename>.<ext>" file per format */
    private function writeVariant(string $fontId, string $folder, array $yml, array $formats, ?string $basename = null): void
    {
        $this->writeYaml($fontId . '/' . $folder . '/font.yml', $yml);
        foreach ($formats as $ext) {
            file_put_contents($this->fontsPath . '/' . $fontId . '/' . $folder . '/' . ($basename ?? $fontId . '-' . $folder) . '.' . $ext, $ext . ' bytes');
        }
    }

    private function remoteFile(string $name, string $content): string
    {
        file_put_contents($this->remotePath . '/' . $name, $content);

        return $this->remotePath . '/' . $name;
    }

    /** get()'s object as an array, files sorted by format (folder listings are not ordered alike on every OS) */
    private function variantData(object $font): array
    {
        $data = get_object_vars($font);
        ksort($data['files']);

        return $data;
    }

    /** Every file under the fonts folder by relative path: parsed YAML for the indexes, raw content otherwise */
    private function folderContents(): array
    {
        $contents = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->fontsPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr(str_replace('\\', '/', $file->getPathname()), \strlen($this->fontsPath) + 1);
            $contents[$path] = 'yml' === $file->getExtension()
                ? Yaml::parseFile($file->getPathname())
                : file_get_contents($file->getPathname());
        }
        ksort($contents, \SORT_STRING);

        return $contents;
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

    // --- regressions ---

    /**
     * The destructor saved whatever was in memory: an index never loaded (or whose
     * load failed) was rewritten as an empty list, its variants and subsets lost.
     */
    public function testAnIndexNeverLoadedIsLeftAsItWas(): void
    {
        $this->writeYaml('fonts.yml', [['id' => 'lobster', 'family' => 'Lobster', 'variants' => ['regular']]]);
        $before = file_get_contents($this->fontsPath . '/fonts.yml');

        $db = new FontsDb($this->fontsPath);
        unset($db);

        $this->assertSame($before, file_get_contents($this->fontsPath . '/fonts.yml'));
    }

    public function testAnIndexThatFailedToLoadIsLeftAsItWas(): void
    {
        file_put_contents($this->fontsPath . '/fonts.yml', "fonts: [unclosed\n");

        $db = new FontsDb($this->fontsPath);
        $this->thrownBy(fn() => $db->load());
        unset($db);

        $this->assertSame("fonts: [unclosed\n", file_get_contents($this->fontsPath . '/fonts.yml'));
    }

    /**
     * No weight is 400: formatVariantId('', 'normal') gave '' (not 'regular').
     */
    public function testAVariantWithoutAWeightIsRegular(): void
    {
        $this->writeYaml('fonts.yml', [['id' => 'roboto', 'family' => 'Roboto', 'variants' => ['regular']]]);

        $db = (new FontsDb($this->fontsPath))->load();

        $this->assertTrue($db->isAvailable('Roboto', null, 'normal'));
    }

    /**
     * A variant font.yml without an id key used to be an "Undefined property" warning.
     */
    public function testAVariantFileWithoutAnIdIsNamedFromItsWeightAndStyle(): void
    {
        $this->writeYaml('fonts.yml', [['id' => 'roboto', 'family' => 'Roboto', 'variants' => ['700']]]);
        $this->writeYaml('roboto/font.yml', ['id' => 'roboto', 'family' => 'Roboto']);
        $this->writeVariant('roboto', '700', ['family' => 'Roboto', 'weight' => '700'], ['ttf', 'woff', 'woff2']);

        $db = (new FontsDb($this->fontsPath))->load();

        $this->assertTrue($db->isInstalled('Roboto', 700));
    }

    /**
     * install() returned quietly for a subset the font does not have.
     */
    public function testInstallRefusesASubsetTheFontDoesNotHave(): void
    {
        $this->seed();
        $db = (new FontsDb($this->fontsPath))->addProvider($this->openSansProvider())->load();

        $e = $this->thrownBy(fn() => $db->install('Open Sans/700italic@cyrillic'));

        $this->assertSame([\Exception::class, 'Subset cyrillic is not available'], [$e::class, $e->getMessage()]);
    }
}
