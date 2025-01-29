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
class FontNotAvailableException extends FontException
{
    protected string $errorMessage = 'Font not available';

    public function getFontError(): string
    {
        return parent::getFontError()
            . (false === $this->distantLoaded ? "\n" . 'Try to load with prefetch to check online availability' : '');
    }
}
