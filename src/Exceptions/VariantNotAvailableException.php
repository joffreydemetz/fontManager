<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager\Exceptions;

use JDZ\FontManager\Exceptions\FontException;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class VariantNotAvailableException extends FontException
{
    protected string $errorMessage = 'Font variant not available';
    protected array $availableVariants = [];

    public function setAvailableVariants(array $availableVariants)
    {
        $this->availableVariants = $availableVariants;
        return $this;
    }

    public function getFontError(): string
    {
        return parent::getFontError()
            . ($this->availableVariants ? "\n" . 'Available variants: ' . implode(', ', $this->availableVariants) : '');
    }
}
