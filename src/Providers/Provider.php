<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager\Providers;

use JDZ\FontManager\Providers\ProviderInterface;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Provider implements ProviderInterface
{
  protected string $providerUrl;

  public function list(): array
  {
    $fonts = [];
    foreach ($this->fetchList() as $item) {
      $fonts[$item->id] = $this->formatFont($item);
    }

    return $fonts;
  }

  public function infos(string $id, string $family): object|false
  {
    if (false === ($data = $this->fecthInfos($id, $family))) {
      return false;
    }

    return $this->formatFont($data);
  }

  protected function fetchList(): array
  {
    return [];
  }

  protected function fecthInfos(string $id, string $family): object|false
  {
    return false;
  }

  protected function getFont(array $fontData): object
  {
    static $fonts;
    if (!isset($fonts)) {
      $fonts = [];
    }

    $fontData = (object)$fontData;

    if (isset($fonts[$fontData->id])) {
      $fonts[$fontData->id] = $fontData;
    }

    return $fonts[$fontData->id];
  }

  protected function formatFont(object $data): object
  {
    //d($data);
    $font = new \stdClass;
    $font->id = $data->id;
    $font->family = $data->family;
    $font->version = $data->version;
    $font->lastModified = $data->lastModified;
    $font->category = $data->category;

    if (!empty($data->variants)) {
      $font->variants = $data->variants;
    }

    if (!empty($data->subsets)) {
      $font->subsets = $data->subsets;
    }

    if (!empty($data->fontVariants)) {
      $font->fontVariants = $data->fontVariants;
    }

    return $font;
  }

  protected function formatFontVariant(object $data): object
  {
    $fontVariant = new \stdClass;
    $fontVariant->id = $data->id;
    $fontVariant->style = $data->style ?? 'normal';
    $fontVariant->weight = $data->weight ?? '';
    $fontVariant->display = $data->display ?? 'swap';
    $fontVariant->files = [];
    return $fontVariant;
  }

  protected function formatVariantParams(string $variant): array
  {
    if (empty($variant) || 'regular' === $variant) {
      $variant = 'regular';
      $weight = '400';
      $style = 'normal';
    } elseif ('italic' === $variant) {
      $weight = '400';
      $style = 'italic';
    } elseif (preg_match("/^(\d{3})italic$/", $variant, $m)) {
      $weight = (string)$m[1];
      $style = 'italic';
    } else {
      $weight = $variant;
      $style = 'normal';
    }

    return [$weight, $style, $variant];
  }
}
