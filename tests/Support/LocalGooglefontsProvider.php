<?php

namespace JDZ\FontManager\Tests\Support;

use JDZ\FontManager\Providers\GooglefontsProvider;

/**
 * GooglefontsProvider pointed at a local file instead of the Google Fonts API:
 * its curl calls read the file:// URL (curl drops the ?key=...&family=... query
 * for file://), so fetchList() / fetchInfos() parse a fixture. No network.
 */
final class LocalGooglefontsProvider extends GooglefontsProvider
{
    public function __construct(string $responsePath, ?string $googleFontsApiKey = null)
    {
        // set before the parent constructor, which appends ?key=... to it
        $this->providerUrl = Files::url($responsePath);

        parent::__construct($googleFontsApiKey);
    }
}
