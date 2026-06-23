<?php

namespace app\admin\validation;

/**
 * 用户验证器
 *
 * 场景：
 *  - create         创建用户
 *  - update         更新用户
 *  - resetPassword  管理员重置密码
 *  - changePassword 用户自己修改密码
 *  - profile        修改个人资料
 */
class UserValidator extends BaseValidator
{
    protected array $rules = [
        'id'           => 'integer|min:1',
        'username'     => 'required|string|regex:/^[a-zA-Z0-9_]{3,32}$/',
        'password'     => 'required|string|min:6|max:50',
        'nickname'     => 'string|max:30',
        'email'        => 'string|email|max:100',
        'mobile'       => 'string|regex:/^1[3-9]\d{9}$/',
        'sex'          => 'in:0,1,2',
        'status'       => 'in:0,1',
        'dept_id'      => 'integer|min:0',
        'role_ids'     => 'array',
        'role_ids.*'   => 'integer|min:1',
        'remark'       => 'string|max:500',
        'old_password' => 'required|string|min:6|max:50',
        'new_password' => 'required|string|min:6|max:50',
        'avatar'       => 'string|max:255',
    ];

    protected array $scenes = [
        'create'         => ['username', 'password', 'nickname', 'email', 'mobile', 'sex', 'status', 'dept_id', 'role_ids', 'remark'],
        'update'         => ['id', 'nickname', 'email', 'mobile', 'sex', 'status', 'dept_id', 'role_ids', 'remark'],
        'resetPassword'  => ['password'],
        'changePassword' => ['old_password', 'new_password'],
        'profile'        => ['nickname', 'email', 'mobile', 'avatar'],
    ];

    protected array $messages = [
        'username.required'    => '用户名不能为空',
        'username.regex'       => '用户名只能由 3-32 位字母、数字、下划线组成',
        'password.required'    => '密码不能为空',
        'password.min'         => '密码不能少于6位',
        'password.max'         => '密码不能超过50个字符',
        'nickname.max'         => '昵称不能超过30个字符',
        'email.email'          => '邮箱格式不正确',
        'email.max'            => '邮箱不能超过100个字符',
        'mobile.regex'         => '手机号格式不正确',
        'sex.in'               => '性别值不正确',
        'status.in'            => '状态值不正确',
        'role_ids.array'       => '角色ID列表必须是数组',
        'old_password.required' => '请输入原密码',
        'old_password.min'     => '原密码不能少于6位',
        'new_password.required' => '请输入新密码',
        'new_password.min'     => '新密码不能少于6位',
    ];

    protected array $attributes = [
        'username'     => '用户名',
        'password'     => '密码',
        'nickname'     => '昵称',
        'email'        => '邮箱',
        'mobile'       => '手机号',
        'sex'          => '性别',
        'status'       => '状态',
        'dept_id'      => '部门',
        'role_ids'     => '角色',
        'remark'       => '备注',
        'old_password' => '原密码',
        'new_password' => '新密码',
        'avatar'       => '头像',
    ];
}
