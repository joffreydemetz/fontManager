<?php

namespace JDZ\FontManager\Tests\Providers;

use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Providers\MrandtlfProvider;

class MrandtlfProviderTest extends TestCase
{
    private MrandtlfProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new MrandtlfProvider();
    }

    public function testMrandtlfProviderCanBeInstantiated(): void
    {
        $this->assertInstanceOf(MrandtlfProvider::class, $this->provider);
    }

    public function testListReturnsArray(): void
    {
        $result = $this->provider->list();

        $this->assertIsArray($result);
    }
}
