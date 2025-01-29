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
class MrandtlfProvider extends Provider
{
  const MRANDFTL_PROVIDER_URL = 'https://gwfh.mranftl.com/api/fonts';

  protected string $providerUrl = self::MRANDFTL_PROVIDER_URL;

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

    return (array)$response;
  }

  protected function fecthInfos(string $id, string $family): object|false
  {
    $ch = \curl_init();
    \curl_setopt($ch, \CURLOPT_URL, $this->providerUrl . '/' . $id);
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

    $infos = (object)$infos;

    if (empty($infos)) {
      return false;
    }

    if (empty($infos->variants)) {
      return false;
    }

    $variants = [];
    $fontVariants = [];
    foreach ($infos->variants as $variantInfos) {
      list($weight, $style, $variantId) = $this->formatVariantParams((string)$variantInfos->id);

      $variants[] = $variantId;

      $fontVariants[$variantId] = $this->formatFontVariant((object)[
        'id' => $variantId,
        'family' => $family,
        'weight' => $weight,
        'style' => $style,
      ]);

      foreach (['ttf', 'woff', 'woff2', 'eot', 'svg'] as $format) {
        if (empty($variantInfos->$format)) {
          continue;
        }

        $fontVariants[$variantId]->files[$format] = $variantInfos->$format;
      }
    }

    $infos->variants = $variants;

    $font = $this->formatFont($infos);
    $font->fontVariants = $fontVariants;

    return $font;
  }
}
