<?php

namespace app\admin\validation;

/**
 * 日志验证器
 */
class LogValidator extends BaseValidator
{
    protected array $rules = [
        'id'         => 'integer|min:1',
        'ids'        => 'array',
        'ids.*'      => 'integer|min:1',
        'days'       => 'integer|min:1|max:365',
        'keyword'    => 'string|max:100',
        'module'     => 'string|max:50',
        'status'     => 'string|in:0,1',
        'login_type' => 'string|in:1,2',
        'start_date' => 'date_format:Y-m-d',
        'end_date'   => 'date_format:Y-m-d',
    ];

    protected array $scenes = [
        'batchDelete' => ['ids'],
        'clear'       => ['days'],
    ];

    protected array $messages = [
        'ids.array'                => 'ID列表必须是数组',
        'ids.required'             => '请选择要删除的日志',
        'days.integer'             => '天数必须是整数',
        'days.min'                 => '天数不能少于1天',
        'days.max'                 => '天数不能超过365天',
        'keyword.max'              => '关键词不能超过100个字符',
        'module.max'               => '模块名称不能超过50个字符',
        'status.in'                => '状态值不正确',
        'login_type.in'            => '登录类型值不正确',
        'start_date.date_format'   => '开始日期格式不正确',
        'end_date.date_format'     => '结束日期格式不正确',
    ];

    protected array $attributes = [
        'ids'        => 'ID列表',
        'days'       => '保留天数',
        'keyword'    => '关键词',
        'module'     => '模块',
        'status'     => '状态',
        'login_type' => '登录类型',
        'start_date' => '开始日期',
        'end_date'   => '结束日期',
    ];
}
