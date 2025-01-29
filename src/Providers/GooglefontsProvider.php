<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager\Providers;

use JDZ\FontManager\Providers\Provider;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class GooglefontsProvider extends Provider
{
  const GFONTS_PROVIDER_URL = 'https://www.googleapis.com/webfonts/v1/webfonts';

  protected string $providerUrl = self::GFONTS_PROVIDER_URL;

  public function __construct(string $googleFontsApiKey)
  {
    $this->providerUrl .= '?key=' . $googleFontsApiKey;
  }

  protected function fetchList(): array
  {
    $ch = \curl_init();
    \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, true);
    \curl_setopt($ch, \CURLOPT_HEADER, false);
    \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
    \curl_setopt($ch, \CURLOPT_URL, $this->providerUrl);
    \curl_setopt($ch, \CURLOPT_REFERER, $this->providerUrl);
    \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
    $result = \curl_exec($ch);
    \curl_close($ch);

    if (!$result) {
      throw new \Exception('Error updating font list from ' . $this->providerUrl);
    }

    $response = \json_decode($result);

    $items = (array)$response->items;

    foreach ($items as $item) {
      $item->id = $this->formatFontId($item);
      unset($item->files);
    }

    return $items;
  }

  protected function fecthInfos(string $id, string $family): object|false
  {
    $ch = \curl_init();
    \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, true);
    \curl_setopt($ch, \CURLOPT_HEADER, false);
    \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
    \curl_setopt($ch, \CURLOPT_URL, $this->providerUrl . '&family=' . \urlencode($family));
    // \curl_setopt($ch, \CURLOPT_REFERER, $this->googleFontsUrl);
    \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
    $result = \curl_exec($ch);
    \curl_close($ch);

    if (!$result) {
      return false;
    }

    $infos = \json_decode($result);

    if (\is_string($infos)) {
      return false;
    }

    if (empty($infos)) {
      return false;
    }

    $infos = (object)$infos;

    if (empty($infos->items[0])) {
      return false;
    }

    $infos = (object)$infos->items[0];

    if (empty($infos->files)) {
      return false;
    }

    $infos->id = $this->formatFontId($infos);
    $infos->files = (array)$infos->files;

    $variants = [];
    $fontVariants = [];

    foreach ($infos->files as $variantId => $filename) {
      list($weight, $style, $variantId) = $this->formatVariantParams((string)$variantId);

      $variants[] = $variantId;

      $fontVariants[$variantId] = $this->formatFontVariant((object)[
        'id' => $variantId,
        'family' => $family,
        'weight' => $weight,
        'style' => $style,
      ]);

      $fontVariants[$variantId]->files['ttf'] = $filename;
    }

    $infos->variants = $variants;
    $font = $this->formatFont($infos);
    $font->fontVariants = $fontVariants;
    return $font;
  }

  private function formatFontId(object $data): string
  {
    $id = strtolower($data->family);
    $id = str_replace(' ', '-', $id);
    return $id;
  }
}
