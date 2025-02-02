<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager;

use JDZ\FontManager\FontVariant;
use JDZ\FontManager\Exceptions\VariantNotAvailableException;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Font implements \JsonSerializable
{
  private string $id = '';
  private string $family = '';
  private string $category = '';
  private string $version = '';
  private string $lastModified = '';
  private bool $local = false;
  private array $subsets = [];
  private array $variants = [];

  private string $basePath = '';
  private bool $installed = false;
  private array $fontVariants = [];

  public function jsonSerialize(): mixed
  {
    $data = [];
    $data['id'] = $this->id;
    $data['family'] = $this->family;

    if ('' !== $this->category) {
      $data['category'] = $this->category;
    }

    if ('' !== $this->version) {
      $data['version'] = $this->version;
    }

    if ('' !== $this->lastModified) {
      $data['lastModified'] = $this->lastModified;
    }

    if (true === $this->local) {
      $data['local'] = $this->local;
    }

    if (true === $this->installed) {
      $data['installed'] = $this->installed;
    }

    if (!empty($this->subsets)) {
      $data['subsets'] = $this->subsets;
    }

    if (!empty($this->variants)) {
      $data['variants'] = $this->variants;
    }

    if (!empty($this->fontVariants)) {
      $data['fontVariants'] = $this->fontVariants;
    }

    return $data;
  }

  public function setBasePath(string $path)
  {
    $path = rtrim($path, '/');
    $this->basePath = $path;
    return $this;
  }

  public function isLocal(?bool $local = null): bool
  {
    if (null !== $local) {
      $this->local = $local;
    }
    return $this->local;
  }

  public function isInstalled(?bool $installed = null): bool
  {
    if (null !== $installed) {
      $this->installed = $installed;
    }
    return $this->installed;
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
    $this->checkId();

    return $this->id;
  }

  public function getFamily(): string
  {
    return $this->family;
  }

  public function getPath(): string
  {
    return $this->basePath . '/' . $this->id;
  }

  public function getFontVariants(): array
  {
    return $this->fontVariants;
  }

  public function getAvailableVariants(): array
  {
    return \array_values($this->variants);
  }

  public function getInstalledVariants(): array
  {
    return \array_keys($this->fontVariants);
  }

  public function checkId()
  {
    if ('' === $this->id && '' !== $this->family) {
      $this->id = str_replace(' ', '-', $this->family);
      $this->id = strtolower($this->id);
    }

    return $this;
  }

  public function checkAvailableSubsets(array $subsets)
  {
    // no subsets .. could be glyphs or icons in the font
    if (empty($this->subsets)) {
      return;
    }

    foreach ($subsets as $subset) {
      if (!in_array($subset, $this->subsets)) {
        throw new \Exception('Subset ' . $subset . ' is not available');
      }
    }
  }

  public function addVariant(string $id)
  {
    if (!in_array($id, $this->variants)) {
      $this->variants[] = $id;
    }
    return $this;
  }

  public function hasVariant(string $id): bool
  {
    return in_array($id, $this->variants);
  }

  public function addFontVariant(FontVariant $fontVariant)
  {
    $fontVariant->setBasePath($this->getPath());

    $id = $fontVariant->getId();

    $this->addVariant($id);

    $this->fontVariants[$id] = $fontVariant;

    return $this;
  }

  public function hasFontVariants(): bool
  {
    foreach ($this->fontVariants as $fontVariant) {
      if (true === $fontVariant->isInstalled()) {
        return true;
      }
    }
    return false;
  }

  public function hasFontVariant(string $id): bool
  {
    return isset($this->fontVariants[$id]);
  }

  public function getFontVariant(string $id): FontVariant
  {
    if (isset($this->fontVariants[$id])) {
      return $this->fontVariants[$id];
    }
    throw (new VariantNotAvailableException())
      ->setFontData($this->family, null, null, $id)
      ->setAvailableVariants($this->getAvailableVariants());
  }

  public function toFont(string $variantId): object
  {
    $data = $this->getFontVariant($variantId)->toFont();
    $data->id = $this->id;
    if (!$data->family) {
      $data->family = $this->family;
    }
    $data->version = $this->version;
    $data->local = $this->local;
    return $data;
  }

  public function toFile(): array
  {
    $data = [];
    $data['id'] = $this->id;
    $data['family'] = $this->family;

    if (true === $this->local) {
      $data['local'] = $this->local;
    }

    if ('' !== $this->category) {
      $data['category'] = $this->category;
    }

    if ('' !== $this->version) {
      $data['version'] = $this->version;
    }

    if ('' !== $this->lastModified) {
      $data['lastModified'] = $this->lastModified;
    }

    return $data;
  }

  public function toStorage(): array
  {
    $data = [];

    if (true === $this->local) {
      $data['local'] = $this->local;
    }

    $data['id'] = $this->id;
    $data['family'] = $this->family;
    $data['category'] = $this->category;
    $data['version'] = $this->version;
    $data['lastModified'] = $this->lastModified;
    $data['variants'] = \array_values($this->variants);

    if ($this->subsets) {
      $data['subsets'] = \array_values($this->subsets);
    }

    return $data;
  }
}
