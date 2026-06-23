<?php

namespace app\admin\validation;

/**
 * 登录验证器
 */
class LoginValidator extends BaseValidator
{
    protected array $rules = [
        'username' => 'required|string|regex:/^[a-zA-Z0-9_]{3,32}$/|max:50',
        'password' => 'required|string|min:6|max:50',
        'captcha'  => 'string|max:10',
    ];

    protected array $scenes = [
        'login'   => ['username', 'password'],
        'captcha' => ['captcha'],
    ];

    protected array $messages = [
        'username.required' => '请输入用户名',
        'username.regex'    => '用户名格式不正确',
        'password.required' => '请输入密码',
        'password.min'      => '密码不能少于6位',
        'captcha.required'  => '请输入验证码',
        'captcha.size'      => '验证码长度不正确',
    ];

    protected array $attributes = [
        'username' => '用户名',
        'password' => '密码',
        'captcha'  => '验证码',
    ];
}
