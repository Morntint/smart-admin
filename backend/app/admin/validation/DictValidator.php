<?php

namespace app\admin\validation;

/**
 * 字典验证器
 */
class DictValidator extends BaseValidator
{
    protected array $rules = [
        'id'      => 'integer|min:1',
        'name'    => 'required|string|max:50',
        'code'    => 'required|string|regex:/^[a-z_]+$/|max:50',
        'type'    => 'string|in:string,number,boolean',
        'status'  => 'in:0,1',
        'remark'  => 'string|max:255',
        'codes'   => 'required|array',
        'codes.*' => 'string|max:50',
    ];

    protected array $scenes = [
        'create' => ['name', 'code', 'type', 'status', 'remark'],
        'update' => ['id', 'name', 'type', 'status', 'remark'],
        'batch'  => ['codes'],
    ];

    protected array $messages = [
        'name.required'  => '字典名称不能为空',
        'name.max'       => '字典名称不能超过50个字符',
        'code.required'  => '字典编码不能为空',
        'code.regex'     => '字典编码只能包含小写字母和下划线',
        'code.max'       => '字典编码不能超过50个字符',
        'type.in'        => '字典类型值不正确',
        'status.in'      => '状态值不正确',
        'codes.required' => '请选择字典编码',
        'codes.array'    => '字典编码列表必须是数组',
    ];

    protected array $attributes = [
        'name'   => '字典名称',
        'code'   => '字典编码',
        'type'   => '字典类型',
        'status' => '状态',
        'remark' => '备注',
    ];
}
