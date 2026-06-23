<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\model\SysDict;
use app\model\SysDictData;
use support\Request;

/**
 * 字典业务服务
 *
 * 提供字典类型与字典数据的管理：
 *  - 字典类型：name + code（唯一）
 *  - 字典数据：dict_id + value（同一字典内 value 唯一）
 *  - 删除字典类型时级联删除其下数据
 */
class DictService extends BaseService
{
    protected string $modelClass = SysDict::class;

    /**
     * 字典分页列表（含 data_count）。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function pageList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyFilters($query, filters: [
            'status' => $request->get('status', ''),
        ]);
        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['name', 'code']
        );

        $result = $this->paginate($query, $request);
        $ids    = $result['list']->pluck('id')->all();

        $dataCounts = SysDictData::whereIn('dict_id', $ids)
                                 ->groupBy('dict_id')
                                 ->selectRaw('dict_id, COUNT(*) as cnt')
                                 ->pluck('cnt', 'dict_id');

        $result['list']->each(fn(SysDict $d) => $d->data_count = $dataCounts[$d->id] ?? 0);
        return $result;
    }

    /**
     * 字典详情（含 data）。
     */
    public function detail(int $id): SysDict
    {
        /** @var SysDict $dict */
        $dict = $this->findOrFail($id, [], '字典不存在');
        $dict->data = SysDictData::where('dict_id', $id)
                                 ->where('status', SysDictData::STATUS_NORMAL)
                                 ->orderBy('sort', 'asc')
                                 ->get();
        return $dict;
    }

    /**
     * 按 code 获取字典数据列表（前端选项常用）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function dataByCode(string $code): array
    {
        $dict = SysDict::findByCode($code);
        if (!$dict) {
            throw BusinessException::notFound('字典不存在');
        }
        return SysDictData::where('dict_id', $dict->id)
                          ->where('status', SysDictData::STATUS_NORMAL)
                          ->orderBy('sort', 'asc')
                          ->get(['value', 'label', 'type'])
                          ->toArray();
    }

    /**
     * 创建字典。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $operatorId): SysDict
    {
        $code = trim((string) ($data['code'] ?? ''));
        $this->assertUnique('code', $code, null, '字典编码已存在');

        return SysDict::create([
            'name'       => trim((string) ($data['name'] ?? '')),
            'code'       => $code,
            'type'       => trim((string) ($data['type'] ?? 'string')) ?: 'string',
            'status'     => (int) ($data['status'] ?? SysDict::STATUS_NORMAL),
            'remark'     => trim((string) ($data['remark'] ?? '')),
            'created_by' => $operatorId,
            'created_at' => $this->now(),
        ]);
    }

    /**
     * 更新字典。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysDict
    {
        /** @var SysDict $dict */
        $dict = $this->findOrFail($id, [], '字典不存在');
        $dict->fill([
            'name'       => trim((string) ($data['name'] ?? '')),
            'type'       => trim((string) ($data['type'] ?? 'string')) ?: 'string',
            'status'     => (int) ($data['status'] ?? SysDict::STATUS_NORMAL),
            'remark'     => trim((string) ($data['remark'] ?? '')),
            'updated_by' => $operatorId,
            'updated_at' => $this->now(),
        ])->save();
        return $dict;
    }

    /**
     * 删除字典（级联删除字典数据）。
     */
    public function delete(int $id): void
    {
        $this->findOrFail($id, [], '字典不存在');
        $this->transaction(function () use ($id) {
            SysDictData::where('dict_id', $id)->delete();
            SysDict::where('id', $id)->delete();
        });
    }

    /**
     * 批量获取字典数据（按 code 列表）。
     *
     * @param string[] $codes
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function batchByCodes(array $codes): array
    {
        $dicts   = SysDict::whereIn('code', $codes)->get()->keyBy('code');
        $dictIds = $dicts->pluck('id')->all();
        $allData = SysDictData::whereIn('dict_id', $dictIds)
                              ->where('status', SysDictData::STATUS_NORMAL)
                              ->orderBy('sort', 'asc')
                              ->get(['dict_id', 'value', 'label', 'type'])
                              ->groupBy('dict_id');

        $result = [];
        foreach ($codes as $code) {
            $dict = $dicts->get($code);
            $result[$code] = $dict
                ? $allData->get($dict->id, collect())->toArray()
                : [];
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // 字典数据
    // -------------------------------------------------------------------------

    /**
     * 字典数据分页列表（按 dict_id，支持标签/键值/状态筛选）。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function dataPageList(Request $request): array
    {
        $dictId = (int) $request->get('dict_id', 0);
        $query  = SysDictData::where('dict_id', $dictId);

        // 按数据标签 / 数据键值模糊筛选，按状态精确筛选
        $this->applyFilters(
            $query,
            filters: ['status' => $request->get('status', '')],
            like:    [
                'label' => (string) $request->get('label', ''),
                'value' => (string) $request->get('value', ''),
            ]
        );

        return $this->paginate($query, $request);
    }

    /**
     * 创建字典数据。
     *
     * @param array<string,mixed> $data
     */
    public function dataCreate(array $data, int $operatorId): SysDictData
    {
        $dictId = (int) ($data['dict_id'] ?? 0);
        $value  = trim((string) ($data['value'] ?? ''));

        if (SysDictData::where('dict_id', $dictId)->where('value', $value)->exists()) {
            throw BusinessException::conflict('字典键值已存在');
        }
        return SysDictData::create([
            'dict_id'    => $dictId,
            'label'      => trim((string) ($data['label'] ?? '')),
            'value'      => $value,
            'type'       => trim((string) ($data['type']  ?? '')) ?: null,
            'sort'       => (int) ($data['sort']   ?? 0),
            'status'     => (int) ($data['status'] ?? SysDictData::STATUS_NORMAL),
            'remark'     => trim((string) ($data['remark'] ?? '')),
            'created_by' => $operatorId,
            'created_at' => $this->now(),
        ]);
    }

    /**
     * 更新字典数据。
     *
     * @param array<string,mixed> $data
     */
    public function dataUpdate(int $id, array $data, int $operatorId): SysDictData
    {
        $item  = $this->findSysDictDataOrFail($id);
        $value = trim((string) ($data['value'] ?? ''));

        if (SysDictData::where('dict_id', $item->dict_id)
                       ->where('value', $value)
                       ->where('id', '<>', $id)->exists()) {
            throw BusinessException::conflict('字典键值已存在');
        }

        $item->fill([
            'label'      => trim((string) ($data['label'] ?? '')),
            'value'      => $value,
            'type'       => trim((string) ($data['type']  ?? '')) ?: null,
            'sort'       => (int) ($data['sort']   ?? 0),
            'status'     => (int) ($data['status'] ?? SysDictData::STATUS_NORMAL),
            'remark'     => trim((string) ($data['remark'] ?? '')),
            'updated_by' => $operatorId,
            'updated_at' => $this->now(),
        ])->save();
        return $item;
    }

    /**
     * 删除字典数据。
     */
    public function dataDelete(int $id): void
    {
        $this->findSysDictDataOrFail($id)->delete();
    }

    /**
     * 字典数据找不到时抛业务异常。
     */
    private function findSysDictDataOrFail(int $id): SysDictData
    {
        $model = SysDictData::find($id);
        if (!$model) {
            throw BusinessException::notFound('字典数据不存在');
        }
        return $model;
    }
}
