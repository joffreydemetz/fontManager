<?php

require_once realpath(__DIR__ . '/../vendor/autoload.php');

use JDZ\FontManager\FontsDb;
use JDZ\FontManager\Exceptions\FontException;

$googleFontsApiKey = ''; // ENTER YOUR GOOGLE FONT API KEY HERE
$subsets = ['latin', 'latin-ext'];
$formats = ['ttf', 'woff2', 'woff'];

if (!\is_dir(__DIR__ . '/fonts/')) {
    \mkdir(__DIR__ . '/fonts/', 0777, true);
}

function d($data, bool $exit = true)
{
    echo json_encode($data, \JSON_PRETTY_PRINT);
    if (true === $exit) exit(0);
}

function check(FontsDb $fontsDb, string $font, string|int|null $weight = null, ?string $style = null, ?array $subsets = null)
{
    echo 'check(' . $font . ', ' . ($weight ?? 'null') . ', ' . ($style ?? 'null') .  ', ' . (null === $subsets ? 'null' : '[' . implode(',', $subsets) . ']') . ')' . "\n";
    try {
        $fontsDb->check($font, $weight, $style, $subsets);
        echo 'OK';
    } catch (FontException $e) {
        echo 'KO' . "\n";
        echo $e->getFontError();
    } catch (\Throwable $e) {
        echo 'KO' . "\n";
        echo $e->getMessage();
    }
    echo "\n";
    echo '---' . "\n\n";
    //echo "\n\n";
}

function install(FontsDb $fontsDb, string $font, string|int|null $weight = null, ?string $style = null, ?array $subsets = null)
{
    echo 'install(' . $font . ', ' . ($weight ?? 'null') . ', ' . ($style ?? 'null') .  ', ' . (null === $subsets ? 'null' : '[' . implode(',', $subsets) . ']') . ')' . "\n";
    try {
        $fontsDb->install($font, $weight, $style, $subsets);
        echo 'OK';
    } catch (FontException $e) {
        echo 'KO' . "\n";
        echo $e->getFontError();
    } catch (\Throwable $e) {
        echo 'KO' . "\n";
        echo $e->getMessage();
    }
    echo "\n";
    echo '---' . "\n\n";
    //echo "\n\n";
}

function get(FontsDb $fontsDb, string $font, string|int|null $weight = null, ?string $style = null, ?array $subsets = null)
{
    echo 'get(' . $font . ', ' . ($weight ?? 'null') . ', ' . ($style ?? 'null') .  ', ' . (null === $subsets ? 'null' : '[' . implode(',', $subsets) . ']') . ')' . "\n";
    try {
        $font = $fontsDb->get($font, $weight, $style, $subsets);
        if (false === $font) {
            echo 'KO';
        } else {
            echo \json_encode($font, \JSON_PRETTY_PRINT);
        }
    } catch (FontException $e) {
        echo 'KO' . "\n";
        echo $e->getFontError();
    } catch (\Throwable $e) {
        echo 'KO' . "\n";
        echo $e->getMessage();
    }
    echo "\n";
    echo '---' . "\n\n";
    //echo "\n\n";
}

try {

    $fontsDb = new FontsDb(
        __DIR__ . '/fonts/',
        $formats
    );

    $fontsDb->addProvider(new \JDZ\FontManager\Providers\MrandtlfProvider());
    if ($googleFontsApiKey) {
        $fontsDb->addProvider(new \JDZ\FontManager\Providers\GooglefontsProvider($googleFontsApiKey));
    }

    echo '***** ' . "\n";
    echo 'Without PREFETCH' . "\n";
    $fontsDb->load();
    echo '***** ' . "\n\n";
    echo 'Check fonts' . "\n\n";
    check($fontsDb, 'afcdp-font');
    check($fontsDb, 'Toto Font');
    check($fontsDb, 'Roboto', 'extralight', null, $subsets);
    check($fontsDb, 'Roboto/regular@' . implode(',', $subsets));
    check($fontsDb, 'Roboto', 800, null, $subsets);
    check($fontsDb, 'Roboto', '600', 'italic', $subsets);
    check($fontsDb, 'Lato/regular@' . implode(',', $subsets));
    check($fontsDb, 'Lato/italic@' . implode(',', $subsets));
    check($fontsDb, 'montserrat/light@' . implode(',', $subsets));
    check($fontsDb, 'Montserrat/700italic@' . implode(',', $subsets));
    check($fontsDb, 'Montserrat', 600, null, $subsets);
    check($fontsDb, 'Montserrat', 400, null, $subsets);
    check($fontsDb, 'Montserrat', 'extralight', null, $subsets);

    echo '***** ' . "\n";
    echo 'With PREFETCH' . "\n";
    $fontsDb->load(true);
    echo '***** ' . "\n\n";
    echo 'Check fonts' . "\n\n";
    check($fontsDb, 'afcdp-font');
    check($fontsDb, 'Toto Font');
    check($fontsDb, 'Roboto', 'extralight', null, $subsets);
    check($fontsDb, 'Roboto', '550', 'italic', $subsets);
    check($fontsDb, 'Montserrat/700italic@' . implode(',', $subsets));
    check($fontsDb, 'Montserrat', 600, null, $subsets);
    check($fontsDb, 'Montserrat', 900, null, $subsets);

    echo '***** ' . "\n\n";
    echo 'Install fonts (prefetch)' . "\n\n";
    install($fontsDb, 'afcdp-font');
    install($fontsDb, 'Toto Font');
    install($fontsDb, 'Roboto', 'extralight', null, $subsets);
    install($fontsDb, 'Roboto@' . implode(',', $subsets));
    install($fontsDb, 'Roboto', '550', 'italic', $subsets);
    install($fontsDb, 'Montserrat/700italic@' . implode(',', $subsets));
    install($fontsDb, 'Montserrat', 600, null, $subsets);
    install($fontsDb, 'Montserrat', 900, null, $subsets);

    echo '***** ' . "\n\n";
    echo 'Check fonts again (prefetch)' . "\n\n";
    check($fontsDb, 'Roboto', 'extralight', null, $subsets);
    check($fontsDb, 'Montserrat/700italic@' . implode(',', $subsets));
    check($fontsDb, 'Montserrat', 600, null, $subsets);
    check($fontsDb, 'Montserrat', 900, null, $subsets);

    echo '***** ' . "\n\n";
    echo 'Get font data (prefetch)' . "\n\n";
    get($fontsDb, 'afcdp-font');
    get($fontsDb, 'Roboto', 'extralight', null, $subsets);
    get($fontsDb, 'Montserrat/550italic@' . implode(',', $subsets));
    get($fontsDb, 'Montserrat', 600, null, $subsets);
    get($fontsDb, 'Montserrat', 900, null, $subsets);
} catch (\Throwable $e) {
    //echo $e->getMessage();
    echo (string)$e;
}

exit();
