<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\model\SysConfig;
use support\Request;

/**
 * 系统配置业务服务
 *
 * 业务规则：
 *  - 配置 key 全局唯一，由小写字母与下划线组成
 *  - 任意写操作后必须 `SysConfig::clearCache()` 清缓存（getConfig 走静态缓存）
 *  - publicConfig() 返回的字段为对外暴露的白名单，需谨慎扩展
 */
class ConfigService extends BaseService
{
    /** 对外暴露（无需登录）的配置 key 白名单 */
    private const PUBLIC_KEYS = ['sys_name', 'sys_logo', 'sys_version'];

    protected string $modelClass = SysConfig::class;

    /**
     * 配置分页列表。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function pageList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyFilters($query, filters: [
            'group' => $request->get('group', ''),
        ]);
        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['name', 'key']
        );
        return $this->paginate($query, $request);
    }

    /**
     * 配置详情。
     */
    public function detail(int $id): SysConfig
    {
        /** @var SysConfig $cfg */
        $cfg = $this->findOrFail($id, [], '配置不存在');
        return $cfg;
    }

    /**
     * 所有分组名（去重）。
     *
     * @return string[]
     */
    public function groups(): array
    {
        return $this->newQuery()->groupBy('group')->pluck('group')->all();
    }

    /**
     * 按分组获取配置。
     */
    public function byGroup(string $group): iterable
    {
        return $this->newQuery()->where('group', $group)->orderBy('sort', 'asc')->get();
    }

    /**
     * 创建配置。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $operatorId): SysConfig
    {
        $key = trim((string) ($data['key'] ?? ''));
        $this->assertUnique('key', $key, null, '配置键名已存在');

        $config = SysConfig::create([
            'name'       => trim((string) ($data['name']    ?? '')),
            'key'        => $key,
            'value'      => $data['value'] ?? '',
            'type'       => trim((string) ($data['type']    ?? 'string')),
            'group'      => trim((string) ($data['group']   ?? 'default')),
            'options'    => trim((string) ($data['options'] ?? '')) ?: null,
            'sort'       => (int) ($data['sort'] ?? 0),
            'remark'     => trim((string) ($data['remark']  ?? '')),
            'created_by' => $operatorId,
            'created_at' => $this->now(),
        ]);
        SysConfig::clearCache();
        return $config;
    }

    /**
     * 更新配置。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysConfig
    {
        /** @var SysConfig $config */
        $config = $this->findOrFail($id, [], '配置不存在');
        $config->fill([
            'name'       => trim((string) ($data['name']    ?? '')),
            'value'      => $data['value'] ?? '',
            'type'       => trim((string) ($data['type']    ?? 'string')),
            'group'      => trim((string) ($data['group']   ?? 'default')),
            'options'    => trim((string) ($data['options'] ?? '')) ?: null,
            'sort'       => (int) ($data['sort'] ?? 0),
            'remark'     => trim((string) ($data['remark']  ?? '')),
            'updated_by' => $operatorId,
            'updated_at' => $this->now(),
        ])->save();
        SysConfig::clearCache();
        return $config;
    }

    /**
     * 删除配置。
     */
    public function delete(int $id): void
    {
        $this->findOrFail($id, [], '配置不存在')->delete();
        SysConfig::clearCache();
    }

    /**
     * 批量更新配置（按 key => value）。
     *
     * 事务保证：任一条 update 抛错则整批回滚，避免出现「前半 key 已写入、后半失败」
     * 导致前端读到半截配置。clearCache 放到事务外 commit 之后，避免提前清空让其它
     * worker 重新加载了旧值。
     *
     * @param array<string,mixed> $configs
     */
    public function batchUpdate(array $configs, int $operatorId): int
    {
        if ($configs === []) {
            return 0;
        }
        $now      = $this->now();
        $affected = $this->transaction(function () use ($configs, $operatorId, $now): int {
            $count = 0;
            foreach ($configs as $key => $value) {
                $count += SysConfig::where('key', $key)->update([
                    'value'      => $value,
                    'updated_by' => $operatorId,
                    'updated_at' => $now,
                ]);
            }
            return $count;
        });
        SysConfig::clearCache();
        return $affected;
    }

    /**
     * 公开配置（前端门户/登录页常用）。
     *
     * @return array<string,mixed>
     */
    public function publicConfig(): array
    {
        $data = [];
        foreach (self::PUBLIC_KEYS as $k) {
            $data[$k] = SysConfig::getConfig($k);
        }
        return $data;
    }
}
