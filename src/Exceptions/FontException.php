<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager\Exceptions;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class FontException extends \Exception
{
    protected string $errorMessage = 'Font Error';
    protected string $fontFamily = '';
    protected string|int|null $fontWeight = null;
    protected ?string $fontStyle = null;
    protected ?string $fontVariant = null;
    protected ?array $fontSubsets = null;
    protected bool $distantLoaded = false;

    public function setDistantLoaded(bool $distantLoaded = true)
    {
        $this->distantLoaded = $distantLoaded;
        return $this;
    }

    public function setFontData(
        string $fontFamily,
        ?string $fontWeight = null,
        ?string $fontStyle = null,
        ?string $fontVariant = null,
        ?array $fontSubsets = null
    ) {
        $this->fontFamily = $fontFamily;
        $this->fontWeight = $fontWeight;
        $this->fontStyle = $fontStyle;
        $this->fontVariant = $fontVariant;
        $this->fontSubsets = $fontSubsets;
        return $this;
    }

    public function getFontError(): string
    {
        $data = [];
        $data[] = 'Font: ' . $this->fontFamily;
        if ($this->fontWeight) {
            $data[] = 'Weight: ' . $this->fontWeight;
        }
        if ($this->fontStyle) {
            $data[] = 'Style: ' . $this->fontStyle;
        }
        if ($this->fontVariant) {
            $data[] = 'Variant: ' . $this->fontVariant;
        }
        if ($this->fontSubsets) {
            $data[] = 'Subsets: ' . implode(',', $this->fontSubsets);
        }
        return $this->errorMessage . ' .. '
            . $this->message
            . ($data ? "\n" . implode(' - ', $data) : '');
    }
}
