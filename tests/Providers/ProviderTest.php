<?php

namespace JDZ\FontManager\Tests\Providers;

use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Providers\Provider;
use JDZ\FontManager\Tests\Support\Arrays;
use JDZ\FontManager\Tests\Support\FakeProvider;

class ProviderTest extends TestCase
{
    public function testListKeysTheFormattedFontsById(): void
    {
        $provider = new FakeProvider([
            [
                'id' => 'open-sans',
                'family' => 'Open Sans',
                'version' => 'v40',
                'lastModified' => '2024-05-01',
                'category' => 'sans-serif',
                'variants' => ['regular', '700'],
                'subsets' => ['latin'],
                'kind' => 'webfonts#webfont',
            ],
            [
                'id' => 'icons',
                'family' => 'Icons',
                'version' => 'V1',
                'lastModified' => '',
                'category' => '',
                'variants' => [],
                'subsets' => [],
            ],
        ]);

        $this->assertSame(
            [
                'open-sans' => [
                    'id' => 'open-sans',
                    'family' => 'Open Sans',
                    'version' => 'v40',
                    'lastModified' => '2024-05-01',
                    'category' => 'sans-serif',
                    'variants' => ['regular', '700'],
                    'subsets' => ['latin'],
                ],
                'icons' => ['id' => 'icons', 'family' => 'Icons', 'version' => 'V1', 'lastModified' => '', 'category' => ''],
            ],
            Arrays::export($provider->list())
        );
    }

    public function testInfosReturnsFalseWhenFetchFails(): void
    {
        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['fetchList', 'fetchInfos'])
            ->getMock();

        $provider->expects($this->once())
            ->method('fetchInfos')
            ->with('test-id', 'Test Family')
            ->willReturn(false);

        $result = $provider->infos('test-id', 'Test Family');

        $this->assertFalse($result);
    }

    public function testInfosReturnsObjectWhenFetchSucceeds(): void
    {
        $mockData = (object)[
            'id' => 'test-font',
            'family' => 'Test Font',
            'version' => 'v1.0',
            'lastModified' => '2026-01-01',
            'category' => 'sans-serif',
            'variants' => ['regular'],
            'subsets' => ['latin']
        ];

        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['fetchList', 'fetchInfos'])
            ->getMock();

        $provider->expects($this->once())
            ->method('fetchInfos')
            ->with('test-font', 'Test Font')
            ->willReturn($mockData);

        $result = $provider->infos('test-font', 'Test Font');

        $this->assertSame(
            [
                'id' => 'test-font',
                'family' => 'Test Font',
                'version' => 'v1.0',
                'lastModified' => '2026-01-01',
                'category' => 'sans-serif',
                'variants' => ['regular'],
                'subsets' => ['latin'],
            ],
            get_object_vars($result)
        );
    }
}
