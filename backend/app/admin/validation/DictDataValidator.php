<?php

namespace app\admin\validation;

/**
 * 字典数据验证器
 */
class DictDataValidator extends BaseValidator
{
    protected array $rules = [
        'id'      => 'integer|min:1',
        'dict_id' => 'required|integer|min:1',
        'label'   => 'required|string|max:100',
        'value'   => 'required|string|max:255',
        'type'    => 'string|max:50',
        'sort'    => 'integer|min:0',
        'status'  => 'in:0,1',
        'remark'  => 'string|max:255',
    ];

    protected array $scenes = [
        'create' => ['dict_id', 'label', 'value', 'type', 'sort', 'status', 'remark'],
        'update' => ['id', 'label', 'value', 'type', 'sort', 'status', 'remark'],
    ];

    protected array $messages = [
        'dict_id.required' => '字典ID不能为空',
        'dict_id.integer'  => '字典ID必须是整数',
        'label.required'   => '字典标签不能为空',
        'label.max'        => '字典标签不能超过100个字符',
        'value.required'   => '字典键值不能为空',
        'value.max'        => '字典键值不能超过255个字符',
        'type.max'         => '类型不能超过50个字符',
        'sort.integer'     => '排序必须是整数',
        'status.in'        => '状态值不正确',
    ];

    protected array $attributes = [
        'dict_id' => '字典ID',
        'label'   => '字典标签',
        'value'   => '字典键值',
        'type'    => '类型',
        'sort'    => '排序',
        'status'  => '状态',
        'remark'  => '备注',
    ];
}
