<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repositories\ChartOfAccountTreeRepository;
use App\Infrastructure\Models\ChartOfAccount;
use Illuminate\Support\Facades\Cache;

final class CachedChartOfAccountTreeRepository implements ChartOfAccountTreeRepository
{
    public function tree(int $tenantId): array
    {
        $key = $this->key($tenantId);
        $cached = Cache::get($key);

        if (is_array($cached) && array_is_list($cached)) {
            return $cached;
        }

        return Cache::lock($key.':lock', 10)->block(5, function () use ($key, $tenantId): array {
            $cached = Cache::get($key);

            if (is_array($cached) && array_is_list($cached)) {
                return $cached;
            }

            $tree = $this->buildTree($tenantId);
            Cache::forever($key, $tree);

            return $tree;
        });
    }

    public function invalidate(int $tenantId): void
    {
        Cache::forget($this->key($tenantId));
    }

    private function key(int $tenantId): string
    {
        return "tenant:{$tenantId}:coa:v1";
    }

    /**
     * @return list<array{
     *     id: int,
     *     parent_id: int|null,
     *     code: string,
     *     name: string,
     *     type: string,
     *     is_system: bool,
     *     children: list<mixed>
     * }>
     */
    private function buildTree(int $tenantId): array
    {
        $accounts = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('code')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'code', 'name', 'type', 'is_system']);
        $childrenByParent = [];

        foreach ($accounts as $account) {
            $parentId = $account->parent_id === null ? 0 : (int) $account->parent_id;
            $childrenByParent[$parentId][] = [
                'id' => $account->getKey(),
                'parent_id' => $account->parent_id === null ? null : (int) $account->parent_id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => (string) $account->getRawOriginal('type'),
                'is_system' => $account->is_system,
            ];
        }

        return $this->nest($childrenByParent, 0);
    }

    /**
     * @param  array<int, list<array{id: int, parent_id: int|null, code: string, name: string, type: string, is_system: bool}>>  $childrenByParent
     * @return list<array{id: int, parent_id: int|null, code: string, name: string, type: string, is_system: bool, children: list<mixed>}>
     */
    private function nest(array $childrenByParent, int $parentId): array
    {
        return array_map(function (array $account) use ($childrenByParent): array {
            $account['children'] = $this->nest($childrenByParent, $account['id']);

            return $account;
        }, $childrenByParent[$parentId] ?? []);
    }
}
