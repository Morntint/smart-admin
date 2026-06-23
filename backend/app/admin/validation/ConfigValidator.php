<?php

namespace app\admin\validation;

/**
 * 系统配置验证器
 */
class ConfigValidator extends BaseValidator
{
    protected array $rules = [
        'id'       => 'integer|min:1',
        'name'     => 'required|string|max:100',
        'key'      => 'required|string|regex:/^[a-z_]+$/|max:100',
        'value'    => 'string',
        'type'     => 'string|in:string,number,boolean,json',
        'group'    => 'string|max:50',
        'options'  => 'string|max:500',
        'sort'     => 'integer|min:0',
        'remark'   => 'string|max:255',
        'configs'  => 'array',
    ];

    protected array $scenes = [
        'create'      => ['name', 'key', 'value', 'type', 'group', 'options', 'sort', 'remark'],
        'update'      => ['id', 'name', 'value', 'type', 'group', 'options', 'sort', 'remark'],
        'batchUpdate' => ['configs'],
    ];

    protected array $messages = [
        'name.required'   => '配置名称不能为空',
        'name.max'        => '配置名称不能超过100个字符',
        'key.required'    => '配置键名不能为空',
        'key.regex'       => '配置键名只能包含小写字母和下划线',
        'key.max'         => '配置键名不能超过100个字符',
        'type.in'         => '配置类型值不正确',
        'group.max'       => '配置分组不能超过50个字符',
        'options.max'     => '配置选项不能超过500个字符',
        'sort.integer'    => '排序必须是整数',
        'configs.array'   => '配置数据必须是数组',
        'configs.required' => '请提供配置数据',
    ];

    protected array $attributes = [
        'name'     => '配置名称',
        'key'      => '配置键名',
        'value'    => '配置值',
        'type'     => '配置类型',
        'group'    => '配置分组',
        'options'  => '配置选项',
        'sort'     => '排序',
        'remark'   => '备注',
        'configs'  => '配置数据',
    ];
}
