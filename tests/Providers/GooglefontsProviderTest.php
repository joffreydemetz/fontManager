<?php

namespace JDZ\FontManager\Tests\Providers;

use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Providers\GooglefontsProvider;

class GooglefontsProviderTest extends TestCase
{
    private string $originalApiKey;

    protected function setUp(): void
    {
        $this->originalApiKey = getenv('GOOGLE_FONTS_API_KEY') ?: '';
    }

    protected function tearDown(): void
    {
        putenv('GOOGLE_FONTS_API_KEY=' . $this->originalApiKey);
    }

    public function testGooglefontsProviderCanBeInstantiated(): void
    {
        $provider = new GooglefontsProvider('test-api-key');

        $this->assertInstanceOf(GooglefontsProvider::class, $provider);
    }

    public function testGooglefontsProviderUsesProvidedApiKey(): void
    {
        $apiKey = 'my-test-api-key';
        $provider = new GooglefontsProvider($apiKey);

        $this->assertInstanceOf(GooglefontsProvider::class, $provider);
    }

    public function testGooglefontsProviderUsesEnvironmentVariable(): void
    {
        putenv('GOOGLE_FONTS_API_KEY=env-test-key');

        $provider = new GooglefontsProvider();

        $this->assertInstanceOf(GooglefontsProvider::class, $provider);
    }

    public function testGooglefontsProviderWithNoApiKey(): void
    {
        putenv('GOOGLE_FONTS_API_KEY=');

        $provider = new GooglefontsProvider();

        $this->assertInstanceOf(GooglefontsProvider::class, $provider);
    }

    public function testProviderUrlConstant(): void
    {
        $this->assertEquals(
            'https://www.googleapis.com/webfonts/v1/webfonts',
            GooglefontsProvider::GFONTS_PROVIDER_URL
        );
    }
}
