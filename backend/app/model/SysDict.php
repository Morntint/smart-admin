<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 字典类型模型
 *
 * 表：sys_dict
 *
 * 业务约束：
 *  - code 全局唯一，由小写字母与下划线组成
 *  - 删除字典时级联删除其下数据（DictService 中处理）
 */
class SysDict extends BaseModel
{
    use SoftDeletes;

    public const STATUS_DISABLED = 0;
    public const STATUS_NORMAL   = 1;

    protected $table = 'sys_dict';

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'status',
        'remark',
        'created_by',
        'updated_by',
    ];

    // -------------------------------------------------------------------------
    // 关联关系
    // -------------------------------------------------------------------------

    /**
     * 字典数据。
     */
    public function data(): HasMany
    {
        return $this->hasMany(SysDictData::class, 'dict_id')->orderBy('sort', 'asc');
    }

    /**
     * 启用状态的字典数据。
     */
    public function activeData(): HasMany
    {
        return $this->hasMany(SysDictData::class, 'dict_id')
                    ->where('status', SysDictData::STATUS_NORMAL)
                    ->orderBy('sort', 'asc');
    }

    // -------------------------------------------------------------------------
    // 业务方法
    // -------------------------------------------------------------------------

    /**
     * 根据编码获取字典。
     */
    public static function findByCode(string $code): ?self
    {
        /** @var ?self $dict */
        $dict = static::where('code', $code)->first();
        return $dict;
    }

    /**
     * 根据编码获取字典数据列表。
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getDictData(string $code): array
    {
        $dict = self::findByCode($code);
        return $dict ? $dict->activeData->toArray() : [];
    }

    /**
     * 获取键值对格式的字典数据（用于前端 select 选项）。
     *
     * @return array<int,array{label:string,value:mixed}>
     */
    public static function getDictOptions(string $code): array
    {
        $dict = self::findByCode($code);
        if (!$dict) {
            return [];
        }

        $options = [];
        foreach ($dict->activeData as $item) {
            $options[] = [
                'label' => $item->label,
                'value' => $item->value,
            ];
        }
        return $options;
    }
}
