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
    protected ?string $fontWeight = null;
    protected ?string $fontStyle = null;
    protected ?string $fontVariant = null;
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
        ?string $fontVariant = null
    ) {
        $this->fontFamily = $fontFamily;
        $this->fontWeight = $fontWeight;
        $this->fontStyle = $fontStyle;
        $this->fontVariant = $fontVariant;
        return $this;
    }

    public function getFontError(): string
    {
        $data = [];
        $data[] = 'F: ' . $this->fontFamily;
        if ($this->fontWeight) {
            $data[] = 'W: ' . $this->fontWeight;
        }
        if ($this->fontStyle) {
            $data[] = 'S: ' . $this->fontStyle;
        }
        if ($this->fontVariant) {
            $data[] = 'V: ' . $this->fontVariant;
        }
        return $this->errorMessage . ' .. '
            . $this->message
            . ($data ? "\n" . 'Data: ' . implode(' - ', $data) : '');
    }
}
