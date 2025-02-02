<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager;

use JDZ\FontManager\Exceptions\FontException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class FontVariant implements \JsonSerializable
{
  private string $id = '';
  private string $family = '';
  private string $style = 'normal';
  private string $weight = '';
  private string $display = '';

  private bool $installed = false;
  private string $basePath = '';
  private array $files = [];

  public function jsonSerialize(): mixed
  {
    $data = [];
    $data['id'] = $this->id;
    $data['family'] = $this->family;

    if ('' !== $this->style) {
      $data['style'] = $this->style;
    }

    if ($this->weight) {
      $data['weight'] = $this->weight;
    }

    if ('' !== $this->display) {
      $data['display'] = $this->display;
    }

    if (true === $this->installed) {
      $data['installed'] = $this->installed;
    }

    if ($this->files) {
      $data['files'] = $this->files;
    }

    return $data;
  }

  public function setBasePath(string $path)
  {
    $this->basePath = $path;
    return $this;
  }

  public function isInstalled(?bool $installed = null): bool
  {
    if (null !== $installed) {
      $this->installed = $installed;
    }
    return $this->installed;
  }

  public function getPath(): string
  {
    return $this->basePath . '/' . $this->id;
  }

  public function getFiles(): array
  {
    return $this->files;
  }

  public function sets(array $data)
  {
    foreach ($data as $key => $value) {
      if (\property_exists($this, $key)) {
        $this->$key = $value;
      }
    }
    return $this;
  }

  public function getId(): string
  {
    return $this->id;
  }

  public function getFile(string $ext): string
  {
    return $this->files[$ext];
  }

  public function hasFile(string $ext): bool
  {
    return isset($this->files[$ext]);
  }

  public function addFile(string $ext, string $filename)
  {
    $this->files[$ext] = $filename;
    return $this;
  }

  /**
   * Check the font variant
   * - has at least a TTF file
   * - create the woff file if missing
   * - create the woff2 file if missing
   * - check if all required formats are present
   */
  public function check(string $fontId, array $formats, array $subsets = [])
  {
    if (false === $this->hasFile('ttf')) {
      throw new FontException('Missing TTF file');
    }

    $filename = $fontId . '-' . $this->getId();

    if (false === $this->hasFile('woff') && in_array('woff', $formats)) {
      $this->ttf2woff($this->getPath() . '/' . $this->getFile('ttf'), $this->getPath() . '/' . $filename . '.woff', $this->subsetsUnicodes($subsets), 'woff');
      $this->addFile('woff', $filename . '.woff');
    }

    if (false === $this->hasFile('woff2') && in_array('woff2', $formats)) {
      $this->ttf2woff($this->getPath() . '/' . $this->getFile('ttf'), $this->getPath() . '/' . $filename . '.woff2', $this->subsetsUnicodes($subsets), 'woff2');
      $this->addFile('woff2', $filename . '.woff2');
    }

    foreach ($formats as $format) {
      if (empty($this->files[$format])) {
        throw new FontException('Missing ' . $format . ' format file');
      }
    }

    $this->installed = true;
  }

  public function toFont(): object
  {
    $data = [];
    $data['id'] = $this->id;
    $data['family'] = $this->family;
    $data['style'] = $this->style;
    $data['weight'] = $this->weight;
    $data['display'] = $this->display;
    $data['files'] = [];
    foreach ($this->files as $format => $file) {
      $data['files'][$format] = $this->basePath . '/' . $this->id . '/' . $file;
    }

    return (object)$data;
  }

  public function toFile(): array
  {
    $data = [];
    $data['id'] = $this->id;
    $data['family'] = $this->family;
    if ($this->style) {
      $data['style'] = $this->style;
    }
    if ($this->weight) {
      $data['weight'] = $this->weight;
    }
    if ($this->display) {
      $data['display'] = $this->display;
    }
    return $data;
  }

  private function subsetsUnicodes(array $subsets): string
  {
    $range = '';
    foreach ($subsets as $subset) {
      switch ($subset) {
        case 'cyrillic-ext':
          $range .= "U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F";
          break;
        case 'cyrillic':
          $range .= "+0400-045F, U+0490-0491, U+04B0-04B1, U+2116";
          break;
        case 'greek-ext':
          $range .= "U+1F00-1FFF";
          break;
        case 'greek':
          $range .= "U+0370-03FF";
          break;
        case 'latin-ext':
          $range .= "U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF";
          break;
        case 'latin':
          $range .= "U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD";
          break;
      }
    }
    return $range;
  }

  private function ttf2woff(string $ttfPath, string $targetPath, string $range, string $flavor): bool
  {
    \ob_start();

    $cmd = [
      'pyftsubset',
      $ttfPath,
      '--output-file=' . $targetPath,
      '--flavor=' . $flavor,
      '--layout-features="*"',
      '--with-zopfli',
      '--unicodes="' . $range . '"',
    ];

    $output = null;
    $code = null;
    \exec(implode(' ', $cmd), $output, $code);

    //$debug = \ob_get_clean();
    \ob_end_clean();

    if (\file_exists($targetPath)) {
      $fs = new Filesystem();
      $fs->chmod($targetPath, 0755);
      return true;
    }

    return false;
  }
}
