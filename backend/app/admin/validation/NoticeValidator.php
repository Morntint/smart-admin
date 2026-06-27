<?php

namespace app\admin\validation;

/**
 * 系统通知验证器
 */
class NoticeValidator extends BaseValidator
{
    protected array $rules = [
        'id'         => 'integer|min:1',
        'user_id'    => 'required|integer|min:1',
        'type'       => 'required|integer|in:1,2,3,4',
        'level'      => 'string|in:info,success,warning,danger',
        'title'      => 'required|string|max:200',
        'content'    => 'string|max:5000',
        'biz_type'   => 'string|max:50',
        'biz_id'     => 'string|max:100',
        'link'       => 'string|max:500',
        'is_read'    => 'in:0,1',
        'expire_time' => 'date',
        // 批量发送
        'user_ids'   => 'array',
        'user_ids.*' => 'integer|min:1',
    ];

    protected array $scenes = [
        'create'  => ['user_id', 'type', 'level', 'title', 'content', 'biz_type', 'biz_id', 'link', 'expire_time'],
        'update'  => ['id', 'type', 'level', 'title', 'content', 'biz_type', 'biz_id', 'link', 'expire_time'],
        'batch'   => ['user_ids', 'type', 'level', 'title', 'content', 'biz_type', 'biz_id', 'link', 'expire_time'],
        'read'    => ['id'],
        'page'    => ['user_id', 'type', 'level', 'is_read', 'keyword'],
    ];

    protected array $messages = [
        'user_id.required'   => '接收用户ID不能为空',
        'user_id.integer'    => '接收用户ID必须是整数',
        'type.required'      => '通知类型不能为空',
        'type.in'            => '通知类型值不正确',
        'level.in'           => '通知级别值不正确',
        'title.required'     => '通知标题不能为空',
        'title.max'          => '通知标题不能超过200个字符',
        'content.max'        => '通知内容不能超过5000个字符',
        'biz_type.max'       => '业务类型长度不能超过50个字符',
        'biz_id.max'         => '业务ID长度不能超过100个字符',
        'link.max'           => '跳转链接长度不能超过500个字符',
        'user_ids.array'     => '用户ID列表必须是数组',
        'user_ids.required'  => '请选择接收用户',
    ];

    protected array $attributes = [
        'user_id'    => '接收用户',
        'type'       => '通知类型',
        'level'      => '通知级别',
        'title'      => '通知标题',
        'content'    => '通知内容',
        'biz_type'   => '业务类型',
        'biz_id'     => '业务ID',
        'link'       => '跳转链接',
        'expire_time' => '过期时间',
        'user_ids'   => '接收用户列表',
    ];
}
