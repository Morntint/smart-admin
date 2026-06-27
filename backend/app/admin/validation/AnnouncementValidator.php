<?php

namespace app\admin\validation;

/**
 * 系统公告验证器
 */
class AnnouncementValidator extends BaseValidator
{
    protected array $rules = [
        'id'           => 'integer|min:1',
        'title'        => 'required|string|max:200',
        'content'      => 'required|string|max:65535',
        'category'     => 'string|in:notice,announcement,activity,maintenance',
        'level'        => 'string|in:info,important,urgent',
        'is_top'       => 'in:0,1',
        'is_popup'     => 'in:0,1',
        'status'       => 'in:0,1,2',
        'effective_at' => 'date',
        'expire_at'    => 'date',
        'sort'         => 'integer|min:0',
        'remark'       => 'string|max:500',
    ];

    protected array $scenes = [
        'create'  => ['title', 'content', 'category', 'level', 'is_top', 'is_popup', 'effective_at', 'expire_at', 'sort', 'remark'],
        'update'  => ['id', 'title', 'content', 'category', 'level', 'is_top', 'is_popup', 'effective_at', 'expire_at', 'sort', 'remark'],
        'status'  => ['id', 'status'],
        'page'    => ['category', 'level', 'status', 'is_top', 'keyword'],
    ];

    protected array $messages = [
        'title.required'     => '公告标题不能为空',
        'title.max'          => '公告标题不能超过200个字符',
        'content.required'   => '公告内容不能为空',
        'content.max'        => '公告内容长度超出限制',
        'category.in'        => '公告分类值不正确',
        'level.in'           => '公告级别值不正确',
        'is_top.in'          => '置顶值不正确',
        'is_popup.in'        => '弹窗值不正确',
        'status.in'          => '状态值不正确',
        'effective_at.date'  => '生效时间格式不正确',
        'expire_at.date'     => '失效时间格式不正确',
        'sort.integer'       => '排序必须是整数',
        'remark.max'         => '备注不能超过500个字符',
    ];

    protected array $attributes = [
        'title'        => '公告标题',
        'content'      => '公告内容',
        'category'     => '公告分类',
        'level'        => '公告级别',
        'is_top'       => '置顶',
        'is_popup'     => '弹窗',
        'status'       => '状态',
        'effective_at' => '生效时间',
        'expire_at'    => '失效时间',
        'sort'         => '排序',
        'remark'       => '备注',
    ];
}
