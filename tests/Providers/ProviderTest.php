<?php

namespace JDZ\FontManager\Tests\Providers;

use PHPUnit\Framework\TestCase;
use JDZ\FontManager\Providers\Provider;

class ProviderTest extends TestCase
{
    public function testProviderCanBeMocked(): void
    {
        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['fetchList', 'fetchInfos'])
            ->getMock();

        $this->assertInstanceOf(Provider::class, $provider);
    }

    public function testListReturnsArray(): void
    {
        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['fetchList', 'fetchInfos'])
            ->getMock();

        $provider->expects($this->once())
            ->method('fetchList')
            ->willReturn([]);

        $result = $provider->list();

        $this->assertIsArray($result);
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

        $this->assertIsObject($result);
        $this->assertEquals('test-font', $result->id);
        $this->assertEquals('Test Font', $result->family);
    }
}
