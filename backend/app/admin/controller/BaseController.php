<?php

namespace app\admin\controller;

use app\admin\service\PermissionService;
use app\common\traits\ApiResponse;
use support\Request;

/**
 * Admin 控制器基类
 *
 * 通用职责：
 *  - 通过 ApiResponse Trait 提供统一响应格式
 *  - 注入由 AuthMiddleware 写入到 Request 的当前登录用户信息
 *  - 暴露常用的当前用户 / 权限 / 时间工具方法
 *
 * 设计约定：
 *  - 控制器只做参数收集与响应包装；业务规则全部交给 Service
 *  - 异常通过 BusinessException / 框架异常上抛，由全局 Handler 统一处理
 *  - 不在控制器中直接操作 Model，避免破坏分层
 */
abstract class BaseController
{
    use ApiResponse;

    /** 当前登录用户 ID（未登录时为 0） */
    protected int $userId = 0;

    /**
     * 当前登录用户信息
     *
     * @var array<string,mixed>
     */
    protected array $adminUser = [];

    public function __construct()
    {
        $req = request();
        $this->userId    = (int) ($req->admin_user_id ?? 0);
        $this->adminUser = (array) ($req->admin_user ?? []);
    }

    /**
     * 获取分页参数（含上限保护）。
     *
     * @return array{page:int,limit:int}
     */
    protected function pageParams(Request $request, int $defaultLimit = 15, int $maxLimit = 100): array
    {
        return [
            'page'  => max(1, (int) $request->get('page', 1)),
            'limit' => min($maxLimit, max(1, (int) $request->get('limit', $defaultLimit))),
        ];
    }

    /**
     * 获取当前服务器时间（Y-m-d H:i:s）。
     */
    protected function now(): string
    {
        return now_datetime();
    }

    /**
     * 判断当前用户是否为超级管理员。
     */
    protected function isSuperAdmin(): bool
    {
        return PermissionService::getInstance()->isSuperAdmin($this->userId);
    }

    /**
     * 判断当前用户是否拥有指定权限（满足其一即可）。
     */
    protected function hasPermission(string ...$permissions): bool
    {
        return PermissionService::getInstance()->hasAnyPermission($this->userId, $permissions);
    }

    /**
     * 清除当前用户（或指定用户）的权限缓存。
     */
    protected function clearPermissionCache(?int $userId = null): void
    {
        clear_permission_cache($userId ?? $this->userId);
    }

    /**
     * 渲染分页结果为标准响应。
     *
     * 推荐控制器使用：
     *   $r = $service->pageList($request);
     *   return $this->pageResponse($r);
     *
     * @param array{list:iterable,total:int,page:int,limit:int} $result
     */
    protected function pageResponse(array $result): \support\Response
    {
        return $this->paginate($result['list'], $result['total'], $result['page'], $result['limit']);
    }
}
