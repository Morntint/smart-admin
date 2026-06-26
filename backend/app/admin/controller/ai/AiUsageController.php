<?php

namespace app\admin\controller\ai;

use app\admin\controller\BaseController;
use app\admin\service\ai\AiUsageService;
use app\common\attribute\RequiresPermission;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;

/**
 * AI 用量统计
 * 路由前缀：/admin/ai/usage
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AiUsageController extends BaseController
{
    private AiUsageService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = AiUsageService::getInstance();
    }

    /**
     * 用量分页列表
     */
    #[Get('/ai/usage')]
    #[RequiresPermission('ai:usage:list')]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->service->pageList($request));
    }

    /**
     * 用量汇总统计
     */
    #[Get('/ai/usage/summary')]
    #[RequiresPermission('ai:usage:list')]
    public function summary(Request $request): Response
    {
        return $this->success($this->service->summary($request));
    }
}
