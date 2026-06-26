<?php

namespace app\admin\controller;

use app\admin\service\LogService;
use app\admin\validation\LogValidator;
use app\common\attribute\RequiresPermission;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;
use support\validation\annotation\Validate;

/**
 * 日志管理（后台）
 *
 * 路由前缀：/admin/log/operation、/admin/log/login
 *
 * 注意：本控制器不会被 OperationLog 中间件记录（中间件已 skip /admin/log/）。
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class LogController extends BaseController
{
    private LogService $logService;

    public function __construct()
    {
        parent::__construct();
        $this->logService = LogService::getInstance();
    }

    // -------------------------------------------------------------------------
    // 操作日志
    // -------------------------------------------------------------------------

    /**
     * 操作日志列表
     */
    #[Get('/log/operation')]
    #[Validate(rules: [
        'page'       => 'integer|min:1',
        'limit'      => 'integer|min:1|max:100',
        'keyword'    => 'string|max:100',
        'module'     => 'string|max:50',
        'method'     => 'string|in:GET,POST,PUT,PATCH,DELETE',
        'status'     => 'string|in:0,1',
        'start_date' => 'date_format:Y-m-d',
        'end_date'   => 'date_format:Y-m-d',
    ])]
    public function operationIndex(Request $request): Response
    {
        return $this->pageResponse($this->logService->operationPageList($request));
    }

    /**
     * 操作日志统计
     */
    #[Get('/log/operation/statistics')]
    #[Validate(rules: ['days' => 'integer|min:1|max:365'])]
    public function operationStatistics(Request $request): Response
    {
        return $this->success(
            $this->logService->operationStatistics((int) $request->get('days', 7))
        );
    }

    /**
     * 操作日志详情
     */
    #[Get('/log/operation/{id}')]
    public function operationShow(Request $request, int $id): Response
    {
        return $this->success($this->logService->operationDetail($id));
    }

    /**
     * 批量删除操作日志
     */
    #[Delete('/log/operation')]
    #[RequiresPermission('system:log:operationDel')]
    #[Validate(validator: LogValidator::class, scene: 'batchDelete')]
    public function operationBatchDelete(Request $request): Response
    {
        $count = $this->logService->operationBatchDelete((array) $request->input('ids', []));
        return $this->success(['count' => $count], '删除成功');
    }

    /**
     * 清理 N 天前的操作日志
     */
    #[Delete('/log/operation/clear')]
    #[RequiresPermission('system:log:operationDel')]
    #[Validate(validator: LogValidator::class, scene: 'clear')]
    public function operationClear(Request $request): Response
    {
        $count = $this->logService->operationClear((int) $request->input('days', 30));
        return $this->success(['count' => $count], '清理成功');
    }

    /**
     * 删除单条操作日志
     */
    #[Delete('/log/operation/{id}')]
    #[RequiresPermission('system:log:operationDel')]
    public function operationDestroy(Request $request, int $id): Response
    {
        $this->logService->operationDelete($id);
        return $this->success(msg: '删除成功');
    }

    // -------------------------------------------------------------------------
    // 登录日志
    // -------------------------------------------------------------------------

    /**
     * 登录日志列表
     */
    #[Get('/log/login')]
    #[Validate(rules: [
        'page'       => 'integer|min:1',
        'limit'      => 'integer|min:1|max:100',
        'keyword'    => 'string|max:100',
        'status'     => 'string|in:0,1',
        'login_type' => 'string|in:1,2',
        'start_date' => 'date_format:Y-m-d',
        'end_date'   => 'date_format:Y-m-d',
    ])]
    public function loginIndex(Request $request): Response
    {
        return $this->pageResponse($this->logService->loginPageList($request));
    }

    /**
     * 登录日志统计
     */
    #[Get('/log/login/statistics')]
    #[Validate(rules: ['days' => 'integer|min:1|max:365'])]
    public function loginStatistics(Request $request): Response
    {
        return $this->success(
            $this->logService->loginStatistics((int) $request->get('days', 7))
        );
    }

    /**
     * 登录日志详情
     */
    #[Get('/log/login/{id}')]
    public function loginShow(Request $request, int $id): Response
    {
        return $this->success($this->logService->loginDetail($id));
    }

    /**
     * 清理 N 天前的登录日志
     */
    #[Delete('/log/login/clear')]
    #[RequiresPermission('system:log:loginDel')]
    #[Validate(validator: LogValidator::class, scene: 'clear')]
    public function loginClear(Request $request): Response
    {
        $count = $this->logService->loginClear((int) $request->input('days', 90));
        return $this->success(['count' => $count], '清理成功');
    }

    /**
     * 删除单条登录日志
     */
    #[Delete('/log/login/{id}')]
    #[RequiresPermission('system:log:loginDel')]
    public function loginDestroy(Request $request, int $id): Response
    {
        $this->logService->loginDelete($id);
        return $this->success(msg: '删除成功');
    }

    /**
     * 批量删除登录日志
     */
    #[Delete('/log/login')]
    #[RequiresPermission('system:log:loginDel')]
    #[Validate(validator: LogValidator::class, scene: 'batchDelete')]
    public function loginBatchDelete(Request $request): Response
    {
        $count = $this->logService->loginBatchDelete((array) $request->input('ids', []));
        return $this->success(['count' => $count], '删除成功');
    }
}
