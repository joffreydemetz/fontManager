<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager;

use JDZ\FontManager\Font;
use JDZ\FontManager\FontVariant;
use JDZ\FontManager\Exceptions\FontException;
use JDZ\FontManager\Exceptions\FontNotAvailableException;
use JDZ\FontManager\Exceptions\VariantNotAvailableException;
use JDZ\FontManager\Providers\Provider;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class FontsDb
{
  private string $fontsPath;
  private array $formats;
  private array $providers = [];
  private array $fonts = [];
  private bool $distantLoaded = false;

  public function __construct(string $fontsPath, array $formats = ['ttf', 'woff2', 'woff'])
  {
    $fontsPath = str_replace('\\', '/', $fontsPath);
    $this->fontsPath = rtrim($fontsPath, '/');

    $this->formats = $formats;
  }

  public function __destruct()
  {
    $this->save();
  }

  public function addProvider(Provider $provider)
  {
    $this->providers[] = $provider;
    return $this;
  }

  public function load(bool $prefetch = false)
  {
    if (!\is_dir($this->fontsPath)) {
      throw new \RuntimeException('Fonts folder not found in ' . $this->fontsPath);
    }

    $this->loadFromYml();
    $this->loadFromFolder();

    if (true === $prefetch) {
      $this->loadFromProviders();
    }

    $this->save();

    return $this;
  }

  /**
   * check if a font is available
   * - font exists in local database
   * - variant is available
   * - subsets are available
   */
  public function isAvailable(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null): bool
  {
    list($family, $id, $weight, $style, $variantId, $subsets) = $this->parseQueryParams($family, $weight, $style, $subsets);

    // font not in local database
    if (!isset($this->fonts[$id])) {
      return false;
    }

    // variant not available
    if (false === $this->fonts[$id]->hasVariant($variantId)) {
      return false;
    }

    try {
      $this->fonts[$id]->checkAvailableSubsets($subsets);
    } catch (\Throwable $e) {
      // font not available with one of the requested subsets
      return false;
    }

    return true;
  }

  /**
   * check if a font is installed
   * - is available
   *   - font exists in local database
   *   - variant is available
   *   - subsets are available
   * - font variant exists
   * - font variant is installed
   */
  public function isInstalled(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null): bool
  {
    if (false === $this->isAvailable($family, $weight, $style, $subsets)) {
      return false;
    }

    list($family, $id, $weight, $style, $variantId, $subsets) = $this->parseQueryParams($family, $weight, $style, $subsets);

    // font variant not available
    if (false === $this->fonts[$id]->hasVariant($variantId)) {
      return false;
    }

    // font variant not installed
    if (false === $this->fonts[$id]->hasFontVariant($variantId)) {
      return false;
    }

    // check if the font variant is installed properly
    try {
      $this->fonts[$id]->getFontVariant($variantId)
        ->check($family, $this->formats, $subsets);
    } catch (\Throwable $e) {
      return false;
    }

    // font variant not completely installed
    if (false === $this->fonts[$id]->getFontVariant($variantId)->isInstalled()) {
      return false;
    }

    return true;
  }

  /**
   * Check if requested font variant is available and installed
   * @throws  FontException
   */
  public function check(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null)
  {
    list($family, $id, $weight, $style, $variantId, $subsets) = $this->parseQueryParams($family, $weight, $style, $subsets);

    // font not in local database
    if (!isset($this->fonts[$id])) {
      throw (new FontNotAvailableException())
        ->setDistantLoaded($this->distantLoaded)
        ->setFontData($family, $weight, $style, $variantId, $subsets);
    }

    // font variant is not available
    if (false === $this->isAvailable($family, $weight, $style, $subsets)) {
      throw (new VariantNotAvailableException())
        ->setFontData($family, $weight, $style, $variantId, $subsets)
        ->setAvailableVariants($this->fonts[$id]->getAvailableVariants());
    }

    $installed = $this->isInstalled($family, $weight, $style, $subsets);

    $this->fonts[$id]->checkAvailableSubsets($subsets);

    if (false === $installed) {
      throw (new FontException('Font variant is not installed but is available'))
        ->setFontData($family, $weight, $style, $variantId, $subsets);
    }

    // check the font variant files
    // @throws FontExcepton when a required file is missing
    $this->fonts[$id]->getFontVariant($variantId)
      ->check($family, $this->formats, $subsets);
  }

  /**
   * Install a font
   * - check if the font is already installed
   * - ignore local fonts
   * - fetch font infos from providers
   * - check if the variant is available
   * - copy the font files
   * - check evrything is ok
   * - save the YML files
   * @throws FontException
   */
  public function install(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null)
  {
    // already installed
    try {
      $this->check($family, $weight, $style, $subsets);
      return;
    } catch (\Throwable $e) {
    }

    list($family, $id, $weight, $style, $variantId, $subsets) = $this->parseQueryParams($family, $weight, $style, $subsets);

    if (!isset($this->fonts[$id]) && true === $this->distantLoaded) {
      throw (new FontNotAvailableException('Font is not available via any provider ..'))
        ->setDistantLoaded(true)
        ->setFontData($family, $weight, $style, $variantId, $subsets);
    }

    $font = $this->fonts[$id];

    // local font cannot be installed
    if (true === $font->isLocal()) {
      throw (new FontException('Font is local and cannot be installed ..'))
        ->setFontData($family, $weight, $style, $variantId, $subsets);
    }

    $infos = false;

    foreach ($this->providers as $provider) {
      if (false !== ($infos = $provider->infos($id, $family, $this->formats))) {
        break;
      }
    }

    if (false === $infos) {
      throw (new FontNotAvailableException('Font is not available via any provider ..'))
        ->setDistantLoaded($this->distantLoaded)
        ->setFontData($family, $weight, $style, $variantId, $subsets);
    }

    if (!in_array($variantId, $infos->variants)) {
      throw (new VariantNotAvailableException())
        ->setFontData($family, $weight, $style, $variantId, $subsets)
        ->setAvailableVariants($this->fonts[$id]->getAvailableVariants());
    }

    $variantData = $infos->fontVariants[$variantId];

    if ('' === $variantData->id) {
      $variantData->id = $this->formatVariantId($variantData->weight, $variantData->style);

      if ('' === $variantData->id) {
        $variantData->id = $this->formatFamilyId($variantData->family);
      }
    }

    if (true === $font->hasFontVariant($variantData->id)) {
      $fontVariant = $font->getFontVariant($variantData->id);

      try {

        $fontVariant->check($font->getId(), $this->formats, $subsets);
        // already has all the needed the files
        return;
      } catch (\Throwable $e) {
        // missing files
      }
    } else {
      $fontVariant = $this->createFontVariant($variantData);
    }

    $basePath = $this->fontsPath . '/' . $font->getId() . '/' . $fontVariant->getId() . '/';

    // copy necessary distant files
    foreach ($variantData->files as $ext => $distFile) {
      if (!in_array($ext, $this->formats)) {
        continue;
      }

      if (true === $fontVariant->hasFile($ext)) {
        continue;
      }

      $basePath = $this->fontsPath . '/' . $font->getId() . '/' . $fontVariant->getId() . '/';
      $filename = $font->getId() . '-' . $fontVariant->getId();

      $fs = new Filesystem();
      $fs->copy($distFile, $basePath . $filename . '.' . $ext);
      $fs->chmod($basePath . $filename . '.' . $ext, 0755);
      $fontVariant->addFile($ext, $filename . '.' . $ext);
    }

    $fontVariant->check($font->getId(), $this->formats, $subsets);

    $font->addFontVariant($fontVariant);
    $fontVariant->isInstalled(true);
    $font->isInstalled(true);

    $this->save();
  }

  /**
   * Get a FontVariant object
   * - font variant properties
   * - font variant format files paths
   */
  public function get(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null): object|false
  {
    if (false === $this->has($family, $weight, $style, $subsets)) {
      return false;
    }

    list($family, $id, $weight, $style, $variantId, $subsets) = $this->parseQueryParams($family, $weight, $style, $subsets);

    return $this->fonts[$id]->toFont($variantId);
  }

  public function has(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null): bool
  {
    list($family, $id, $weight, $style, $variantId, $subsets) = $this->parseQueryParams($family, $weight, $style, $subsets);

    if (!isset($this->fonts[$id])) {
      return false;
    }

    if (false === $this->isInstalled($family, $weight, $style, $subsets)) {
      return false;
    }

    return true;
  }

  public function save()
  {
    $fonts = [];
    foreach ($this->fonts as $font) {
      if (false === $font->isInstalled()) {
        continue;
      }

      $fonts[] = $font;

      $this->dumpYaml($this->fontsPath . '/' . $font->getId() . '/font.yml', $font->toFile());

      foreach ($font->getFontVariants() as $variant) {
        if (false === $variant->isInstalled()) {
          continue;
        }

        $this->dumpYaml($this->fontsPath . '/' . $font->getId() . '/' . $variant->getId() . '/font.yml', $variant->toFile());
      }
    }

    $fonts = \array_map(fn($font) => $font->toStorage(), $fonts);

    $this->dumpYaml($this->fontsPath . '/fonts.yml', $fonts);
  }

  private function loadFromYml()
  {
    $fonts = (array)$this->getYaml($this->fontsPath . '/fonts.yml');

    foreach ($fonts as $font) {
      $this->createFont((object)$font);
    }
  }

  private function loadFromFolder()
  {
    foreach (new \DirectoryIterator($this->fontsPath . '/') as $folderFI) {
      if (true === $folderFI->isDot() || false === $folderFI->isDir()) {
        continue;
      }

      $fontData = $this->getYaml($folderFI->getPathname() . '/font.yml');

      if (empty($fontData)) {
        continue;
      }

      $fontData = (object)$fontData;

      $font = $this->createFont($fontData);

      foreach (new \DirectoryIterator($folderFI->getPathname() . '/') as $fontFI) {
        if (true === $fontFI->isDot() || false === $fontFI->isDir()) {
          continue;
        }

        $variantData = $this->getYaml($fontFI->getPathname() . '/font.yml');

        if (empty($variantData)) {
          continue;
        }

        $variantData = (object)$variantData;
        $variantData->weight = $variantData->weight ?? '';
        $variantData->style = $variantData->style ?? '';
        $variantData->family = $variantData->family ?? $font->getFamily();
        unset($variantData->filename);

        $variantData->id = (string)$variantData->id;
        $variantData->weight = (string)$variantData->weight;
        $variantData->style = (string)$variantData->style;

        if ('' === $variantData->id) {
          $variantData->id = $this->formatVariantId($variantData->weight, $variantData->style);

          if ('' === $variantData->id) {
            $variantData->id = $this->formatFamilyId($variantData->family);
          }
        }

        $variant = $this->createFontVariant($variantData);

        foreach (new \DirectoryIterator($fontFI->getPathname() . '/') as $variantFI) {
          if (true === $variantFI->isDot() || true === $variantFI->isDir() || 'font.yml' === $variantFI->getFilename()) {
            continue;
          }
          $variant->addFile($variantFI->getExtension(), $variantFI->getFilename());
        }

        $font->addFontVariant($variant);

        try {
          $variant->check($font->getId(), $this->formats);
          $font->isInstalled(true);
        } catch (\Throwable $e) {
        }
      }

      $this->fonts[$font->getId()] = $font;
    }
  }

  private function loadFromProviders()
  {
    if (true === $this->distantLoaded) {
      return;
    }

    foreach ($this->providers as $provider) {
      $fonts = $provider->list();

      foreach ($fonts as $fontData) {
        if (!isset($this->fonts[$fontData->id])) {
          $font = $this->createFont($fontData);
          $this->fonts[$font->getId()] = $font;
        } else {
          $font = $this->fonts[$fontData->id];
        }
        foreach ($fontData->variants as $variant) {
          $font->addVariant($variant);
        }
      }
    }

    $this->distantLoaded = true;
  }

  private function createFont(object $fontData): Font
  {
    if (isset($this->fonts[$fontData->id])) {
      return $this->fonts[$fontData->id];
    }

    if (empty($fontData->version)) {
      $fontData->version = 'V1';
    }

    $font = new Font();

    $font->sets([
      'local' => $fontData->local ?? false,
      'id' => $fontData->id,
      'family' => $fontData->family,
      'version' => $fontData->version,
      'category' => $fontData->category ?? '',
      'lastModified' => $fontData->lastModified ?? '',
      'variants' =>  $fontData->variants ?? [],
      'subsets' => $fontData->subsets ?? [],
    ]);

    $font->setBasePath($this->fontsPath);
    $font->checkId();

    if (isset($this->fonts[$font->getId()])) {
      return $this->fonts[$font->getId()];
    }

    $this->fonts[$font->getId()] = $font;

    return $font;
  }

  private function createFontVariant(object $variantData): FontVariant
  {
    $variant = new FontVariant();

    $variant->sets([
      'id' => $variantData->id,
      'family' => $variantData->family,
      'style' => $variantData->style ?? 'normal',
      'weight' => $variantData->weight ?? '',
      'display' => $variantData->display ?? '',
    ]);

    return $variant;
  }

  private function getYaml(string $path): array
  {
    $data = [];

    if (\file_exists($path)) {
      try {
        $data = Yaml::parseFile($path);
        if (empty($data)) {
          $data = [];
        }
      } catch (\Throwable $e) {
        throw new \Exception('Unable to parse the YAML file in ' . $path . ' .. ' . $e->getMessage());
      }
    }

    return $data;
  }

  private function dumpYaml(string $path, mixed $data, int $maxLevel = 3)
  {
    try {
      $fs = new Filesystem();
      $fs->dumpFile($path, Yaml::dump((array)$data), $maxLevel);
      $fs->chmod($path, 0755);
    } catch (\Throwable $e) {
      throw new \Exception('Error dumping the Yml file .. ' . $e->getMessage());
    }
  }

  private function parseQueryParams(string $family, string|int|null $weight = null, ?string $style = null, ?array $subsets = null): array
  {
    // extract subsets from family
    if (false !== \strpos($family, '@')) {
      $parts = explode('@', $family, 2);
      $family = $parts[0];
      if ('' !== $parts[1]) {
        $subsets = explode(',', $parts[1]);
      }
    }

    // extract variant from family
    if (false !== \strpos($family, '/')) {
      $parts = explode('/', $family, 2);
      $family = $parts[0];
      $weight = $parts[1];
    }

    if (null !== $weight) {
      $weight = (string)$weight;

      if ($weight) {
        if (preg_match("/^(.+)italic$/", $weight, $m)) {
          $weight = $m[1];
          $style = 'italic';
        }
      }
    }

    switch ($weight) {
      case 'extralight':
        $weight = '100';
        break;
      case 'light':
        $weight = '300';
        break;
      case 'bold':
        $weight = '700';
        break;
      case 'extrabold':
        $weight = '900';
        break;
      case 'regular':
        $weight = '';
        $style = '';
        break;
    }

    return [
      $this->formatFamilyName($family),
      $this->formatFamilyId($family),
      $weight,
      $style,
      $this->formatVariantId((string)$weight, (string)$style),
      $subsets ?? [],
    ];
  }

  private function formatFamilyName(string $id): string
  {
    $name = \str_replace('-', ' ', $id);
    $name = \ucwords($name);
    return $name;
  }

  private function formatFamilyId(string $name): string
  {
    $id = \str_replace(' ', '-', $name);
    $id = \strtolower($id);
    return $id;
  }

  private function formatVariantId(string $weight, string $style): string
  {
    if ('' === $style && '' === $weight) {
      return 'regular';
    }

    if ('italic' === $style) {
      if ('400' === $weight) {
        return 'italic';
      }
      return $weight . 'italic';
    }

    if ('400' === $weight) {
      return 'regular';
    }

    return $weight;
  }
}
