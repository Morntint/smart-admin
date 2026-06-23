<?php

namespace app\admin\validation;

/**
 * 角色验证器
 */
class RoleValidator extends BaseValidator
{
    protected array $rules = [
        'id'                => 'integer|min:1',
        'name'              => 'required|string|max:50',
        'code'              => 'required|string|regex:/^[a-z_]+$/|max:50',
        'sort'              => 'integer|min:0',
        'status'            => 'in:0,1',
        'data_scope'        => 'in:1,2,3,4,5',
        'data_scope_depts'  => 'array',
        'data_scope_depts.*' => 'integer|min:1',
        'remark'            => 'string|max:255',
        'menu_ids'          => 'array',
        'menu_ids.*'        => 'integer|min:1',
    ];

    protected array $scenes = [
        'create'      => ['name', 'code', 'sort', 'status', 'data_scope', 'data_scope_depts', 'remark', 'menu_ids'],
        'update'      => ['id', 'name', 'code', 'sort', 'status', 'data_scope', 'data_scope_depts', 'remark', 'menu_ids'],
        'assignMenus' => ['menu_ids'],
        'dataScope'   => ['data_scope', 'data_scope_depts'],
    ];

    protected array $messages = [
        'name.required'     => '角色名称不能为空',
        'name.max'          => '角色名称不能超过50个字符',
        'code.required'     => '角色代码不能为空',
        'code.regex'        => '角色代码只能包含小写字母和下划线',
        'code.max'          => '角色代码不能超过50个字符',
        'sort.integer'      => '排序必须是整数',
        'status.in'         => '状态值不正确',
        'data_scope.in'     => '数据范围值不正确',
        'data_scope.required' => '请选择数据范围',
        'menu_ids.array'    => '菜单ID列表必须是数组',
    ];

    protected array $attributes = [
        'name'              => '角色名称',
        'code'              => '角色代码',
        'sort'              => '排序',
        'status'            => '状态',
        'data_scope'        => '数据范围',
        'data_scope_depts'  => '自定义数据范围部门',
        'remark'            => '备注',
        'menu_ids'          => '菜单权限',
    ];
}
