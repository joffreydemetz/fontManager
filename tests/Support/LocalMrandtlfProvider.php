<?php

namespace JDZ\FontManager\Tests\Support;

use JDZ\FontManager\Providers\MrandtlfProvider;

/**
 * MrandtlfProvider pointed at a local path instead of the google-webfonts-helper
 * API: fetchList() reads the path itself, fetchInfos($id) reads "<path>/<id>",
 * both through curl and a file:// URL. No network.
 */
final class LocalMrandtlfProvider extends MrandtlfProvider
{
    public function __construct(string $path)
    {
        $this->providerUrl = Files::url($path);
    }
}
