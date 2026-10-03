# JDZ Fonts Manager
Utility to manage a local web fonts database

## Description

`FontsDb` keeps a folder of web fonts indexed by YAML files. It fetches font
families from remote providers (Google Fonts, google-webfonts-helper), copies the
TTF files of the variants you ask for, converts them to WOFF / WOFF2 subsetted to
the requested character sets, and gives back each variant's properties and file
paths so you can write the `@font-face` rules. Fonts you drop into the folder
yourself (local fonts) are indexed the same way.

## Installation

```bash
composer require jdz/fontmanager
```

## Requirements

- PHP >= 8.2
- `ext-curl` (fetching font lists and files from the providers)
- `symfony/yaml` ^7.4
- `symfony/filesystem` ^7.4
- `tecnickcom/tc-lib-pdf-font` ^2.0
- Python with *fonttools* — the WOFF / WOFF2 conversion shells out to `pyftsubset`, which must be on the `PATH`:

```bash
pip install fonttools[woff]
```

Optional: `symfony/dotenv` to load `GOOGLE_FONTS_API_KEY` from a `.env` file (see `.env.dist`).

## Usage

```php
use JDZ\FontManager\FontsDb;
use JDZ\FontManager\Providers\GooglefontsProvider;
use JDZ\FontManager\Providers\MrandtlfProvider;
use JDZ\FontManager\Exceptions\FontException;

$fontsDb = new FontsDb(__DIR__ . '/fonts', ['ttf', 'woff2', 'woff']);

$fontsDb
    ->addProvider(new MrandtlfProvider())
    ->addProvider(new GooglefontsProvider()) // API key from $_ENV['GOOGLE_FONTS_API_KEY'], or pass it
    ->load()                                  // index the local folder (it must exist)
    ->loadDistantFonts();                     // add the provider catalogs (needed before install())

$subsets = ['latin', 'latin-ext'];

try {
    $fontsDb->install('Roboto', 300, null, $subsets);
    $fontsDb->install('Montserrat/700italic@latin,latin-ext');
} catch (FontException $e) {
    echo $e->getFontError();
}

$font = $fontsDb->get('Roboto', 'light', null, $subsets);
// (object) [id, family, style, weight, display, version, local,
//           files => ['ttf' => '…', 'woff2' => '…', 'woff' => '…']]
// or false when the variant is not installed
```

A full walk-through (check, install, get, with and without the provider catalogs)
is in [examples/example.php](examples/example.php).

### Querying a font

Every query method takes `(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null)`:

- `$weight` — `100`…`900`, or `extralight` (100), `light` (300), `bold` (700), `extrabold` (900), `regular`; a `…italic` suffix sets the style (`'700italic'`).
- The same query can be packed into the family string: `'Family/variant@subset1,subset2'` (`'Roboto/regular@latin'`, `'Montserrat/700italic@latin,latin-ext'`).

## API

### `JDZ\FontManager\FontsDb`

| Method | Description |
|---|---|
| `__construct(string $fontsPath, array $formats = ['ttf', 'woff2', 'woff'])` | The fonts folder and the formats every installed variant must have |
| `addProvider(Provider $provider): self` | Register a remote font provider |
| `load(bool $prefetch = false): self` | Index `fonts.yml` and the font folders; `true` also loads the provider catalogs and saves |
| `loadDistantFonts(bool $save = false): self` | Load the provider catalogs (once) |
| `isAvailable(...): bool` | The font is known, the variant and subsets exist |
| `isInstalled(...): bool` | Available, and its files are present in every format |
| `check(...): void` | Same as `isInstalled()`, but throws a `FontException` subclass saying what is missing |
| `install(...): void` | Download the variant's TTF from the first provider that has it, generate the missing formats, save the index |
| `has(...): bool` / `get(...): object\|false` | Whether an installed variant exists / its data and file paths |
| `save(): void` | Write the YAML index files (also called on destruct) |

### Providers (`JDZ\FontManager\Providers\`)

| Class | Source |
|---|---|
| `GooglefontsProvider(?string $googleFontsApiKey = null)` | Google Fonts Developer API (`https://www.googleapis.com/webfonts/v1/webfonts`); without an argument the key is read from `$_ENV['GOOGLE_FONTS_API_KEY']` |
| `MrandtlfProvider` | google-webfonts-helper API (`https://gwfh.mranftl.com/api/fonts`) |
| `Provider` | Abstract base: implement `fetchList(): array` and `fetchInfos(string $id, string $family): object\|false` to add a source |

### Exceptions (`JDZ\FontManager\Exceptions\`)

`FontException` (with `getFontError()`), `FontNotAvailableException` (unknown
family), `VariantNotAvailableException` (unknown variant; lists the available ones).

## Folder layout

```
fonts/
├── fonts.yml                     # index of the installed fonts
└── roboto/
    ├── font.yml                  # family: id, family, version, category, …
    └── 300/
        ├── font.yml              # variant: id, family, style, weight, display
        ├── roboto-300.ttf
        ├── roboto-300.woff2
        └── roboto-300.woff
```

A local font is a folder laid out the same way by hand (`local: true` in its
`font.yml`, a TTF in each variant folder); the missing WOFF / WOFF2 files are
generated when the folder is loaded. Local fonts are never installed from a provider.

## Tests

```bash
composer test
```

## Changelog

- **2.1.0** — Example uses a generic custom font name.
- **2.0.2** — Test coverage configuration.
- **2.0.1** — PHPUnit 11.
- **2.0.0** — PHP >= 8.2, Symfony ^7.4, `ext-curl` declared; return types throughout; `FontsDb::loadDistantFonts()`; the `GooglefontsProvider` API key is optional (falls back to `GOOGLE_FONTS_API_KEY`, `symfony/dotenv` suggested); `Provider::fecthInfos()` renamed `fetchInfos()` and made abstract with `fetchList()`; test suite.

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Author

(c) Joffrey Demetz <joffrey.demetz@gmail.com>
