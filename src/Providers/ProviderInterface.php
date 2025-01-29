<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\FontManager\Providers;

/**
 * @author  Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface ProviderInterface
{
  public function list(): array;

  public function infos(string $id, string $family): object|false;
}
