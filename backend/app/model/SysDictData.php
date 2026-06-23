<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 字典数据模型
 *
 * 表：sys_dict_data
 *
 * 业务约束：
 *  - dict_id + value 唯一（同一字典内 value 不可重复）
 */
class SysDictData extends BaseModel
{
    use SoftDeletes;

    public const STATUS_DISABLED = 0;
    public const STATUS_NORMAL   = 1;

    protected $table = 'sys_dict_data';

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'dict_id',
        'label',
        'value',
        'type',
        'sort',
        'status',
        'remark',
        'created_by',
        'updated_by',
    ];

    public function dict(): BelongsTo
    {
        return $this->belongsTo(SysDict::class, 'dict_id');
    }
}
