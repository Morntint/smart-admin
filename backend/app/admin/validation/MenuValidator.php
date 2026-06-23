<?php

namespace app\admin\validation;

/**
 * 菜单验证器
 */
class MenuValidator extends BaseValidator
{
    protected array $rules = [
        'id'          => 'integer|min:1',
        'parent_id'   => 'integer|min:0',
        'name'        => 'required|string|max:50',
        'icon'        => 'string|max:50',
        'path'        => 'string|max:255',
        'component'   => 'string|max:255',
        'redirect'    => 'string|max:255',
        'type'        => 'in:1,2,3',
        'is_external' => 'in:0,1',
        'is_cache'    => 'in:0,1',
        'is_visible'  => 'in:0,1',
        'sort'        => 'integer|min:0',
        'permission'  => 'string|max:100',
        'status'      => 'in:0,1',
        'remark'      => 'string|max:255',
        'ids'         => 'required|array',
        'ids.*'       => 'integer|min:1',
    ];

    protected array $scenes = [
        'create'      => ['parent_id', 'name', 'icon', 'path', 'component', 'redirect', 'type', 'is_external', 'is_cache', 'is_visible', 'sort', 'permission', 'status', 'remark'],
        'update'      => ['id', 'parent_id', 'name', 'icon', 'path', 'component', 'redirect', 'type', 'is_external', 'is_cache', 'is_visible', 'sort', 'permission', 'status', 'remark'],
        'batchStatus' => ['ids', 'status'],
    ];

    protected array $messages = [
        'name.required'    => '菜单名称不能为空',
        'name.max'         => '菜单名称不能超过50个字符',
        'type.in'          => '菜单类型值不正确',
        'permission.max'   => '权限标识不能超过100个字符',
        'ids.required'     => '请选择要操作的菜单',
        'ids.array'        => '菜单ID列表必须是数组',
        'status.required'  => '请选择状态',
        'status.in'        => '状态值不正确',
    ];

    protected array $attributes = [
        'parent_id'   => '上级菜单',
        'name'        => '菜单名称',
        'icon'        => '菜单图标',
        'path'        => '路由地址',
        'component'   => '组件路径',
        'redirect'    => '重定向地址',
        'type'        => '菜单类型',
        'is_external' => '是否外链',
        'is_cache'    => '是否缓存',
        'is_visible'  => '是否显示',
        'sort'        => '排序',
        'permission'  => '权限标识',
        'status'      => '状态',
        'remark'      => '备注',
    ];
}
