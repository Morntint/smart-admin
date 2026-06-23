<?php

namespace support;

/**
 * 扩展 Request，为 AuthMiddleware 注入的用户信息提供类型提示
 *
 * @property int   $admin_user_id  当前登录用户 ID（由 AuthMiddleware 注入）
 * @property array $admin_user     当前登录用户信息数组（由 AuthMiddleware 注入）
 */
class Request extends \Webman\Http\Request
{
}
