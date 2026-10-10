<?php

namespace JDZ\FontManager\Tests\Support;

use JDZ\FontManager\Providers\Provider;

/**
 * In-memory font provider: serves a canned catalog and canned font infos,
 * counts the catalog requests and records every infos request. No network.
 *
 * FontsDb::addProvider() takes the abstract Provider, not ProviderInterface,
 * so this extends Provider and answers its fetchList() / fetchInfos() hooks:
 * Provider::list() and Provider::infos() run for real on top of it.
 */
final class FakeProvider extends Provider
{
    public int $listCalls = 0;

    /** @var list<array{0: string, 1: string}> [id, family] of every fetchInfos() call */
    public array $infosRequests = [];

    /**
     * @param list<array<string, mixed>>           $catalog fetchList() rows: id, family, version, lastModified, category, variants, subsets
     * @param array<string, array<string, mixed>>  $infos   fetchInfos() row per font id; its 'fontVariants' map holds one array per variant
     */
    public function __construct(
        private readonly array $catalog = [],
        private readonly array $infos = [],
    ) {}

    protected function fetchList(): array
    {
        $this->listCalls++;

        // fresh objects on every call: FontsDb writes into what it receives
        return array_map(static fn(array $row): object => (object)$row, $this->catalog);
    }

    protected function fetchInfos(string $id, string $family): object|false
    {
        $this->infosRequests[] = [$id, $family];

        if (!isset($this->infos[$id])) {
            return false;
        }

        $font = (object)$this->infos[$id];
        $font->fontVariants = array_map(static fn(array $variant): object => (object)$variant, $font->fontVariants ?? []);

        return $font;
    }
}
