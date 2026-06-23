<?php

namespace app\admin\controller;

use app\admin\service\ConfigService;
use app\admin\validation\ConfigValidator;
use app\common\attribute\RequiresPermission;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;
use support\validation\annotation\Validate;

/**
 * 系统配置管理（后台）
 *
 * 路由前缀：/admin/config
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class ConfigController extends BaseController
{
    private ConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = ConfigService::getInstance();
    }

    /**
     * 配置分页列表
     */
    #[Get('/config')]
    #[Validate(rules: [
        'page'    => 'integer|min:1',
        'limit'   => 'integer|min:1|max:100',
        'group'   => 'string|max:50',
        'keyword' => 'string|max:100',
    ])]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->configService->pageList($request));
    }

    /**
     * 所有分组名称
     */
    #[Get('/config/groups')]
    public function groups(Request $request): Response
    {
        return $this->success($this->configService->groups());
    }

    /**
     * 按分组获取配置
     */
    #[Get('/config/group/{group}')]
    public function group(Request $request, string $group): Response
    {
        return $this->success($this->configService->byGroup($group));
    }

    /**
     * 配置详情
     */
    #[Get('/config/{id}')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->configService->detail($id));
    }

    /**
     * 创建配置
     */
    #[Post('/config')]
    #[RequiresPermission('system:config:add')]
    #[Validate(validator: ConfigValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $config = $this->configService->create($request->post(), $this->userId);
        return $this->success(['id' => $config->id], '创建成功');
    }

    /**
     * 更新配置
     */
    #[Put('/config/{id}')]
    #[RequiresPermission('system:config:edit')]
    #[Validate(validator: ConfigValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->configService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除配置
     */
    #[Delete('/config/{id}')]
    #[RequiresPermission('system:config:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->configService->delete($id);
        return $this->success(msg: '删除成功');
    }

    /**
     * 批量更新配置
     */
    #[Put('/batch/config')]
    #[RequiresPermission('system:config:edit')]
    #[Validate(validator: ConfigValidator::class, scene: 'batchUpdate')]
    public function batchUpdate(Request $request): Response
    {
        $this->configService->batchUpdate(
            (array) $request->post('configs', []),
            $this->userId
        );
        return $this->success(msg: '更新成功');
    }

    /**
     * 公开配置（无需登录，前端门户使用）
     */
    #[Get('/public/config')]
    public function publicConfig(Request $request): Response
    {
        return $this->success($this->configService->publicConfig());
    }
}
