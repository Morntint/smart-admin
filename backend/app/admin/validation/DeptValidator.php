<?php

namespace app\admin\validation;

/**
 * 部门验证器
 */
class DeptValidator extends BaseValidator
{
    protected array $rules = [
        'id'        => 'integer|min:1',
        'parent_id' => 'integer|min:0',
        'name'      => 'required|string|max:50',
        'leader'    => 'string|max:50',
        'mobile'    => 'string|regex:/^1[3-9]\d{9}$/',
        'email'     => 'string|email|max:100',
        'sort'      => 'integer|min:0',
        'status'    => 'in:0,1',
        'remark'    => 'string|max:255',
    ];

    protected array $scenes = [
        'create' => ['parent_id', 'name', 'leader', 'mobile', 'email', 'sort', 'status', 'remark'],
        'update' => ['id', 'parent_id', 'name', 'leader', 'mobile', 'email', 'sort', 'status', 'remark'],
    ];

    protected array $messages = [
        'name.required' => '部门名称不能为空',
        'name.max'      => '部门名称不能超过50个字符',
        'leader.max'    => '负责人姓名不能超过50个字符',
        'mobile.regex'  => '手机号格式不正确',
        'email.email'   => '邮箱格式不正确',
        'email.max'     => '邮箱不能超过100个字符',
        'sort.integer'  => '排序必须是整数',
        'status.in'     => '状态值不正确',
    ];

    protected array $attributes = [
        'parent_id' => '上级部门',
        'name'      => '部门名称',
        'leader'    => '负责人',
        'mobile'    => '联系电话',
        'email'     => '邮箱',
        'sort'      => '排序',
        'status'    => '状态',
        'remark'    => '备注',
    ];
}
