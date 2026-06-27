<?php

namespace app\admin\controller;

use app\admin\service\AnnouncementService;
use app\admin\validation\AnnouncementValidator;
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
 * 系统公告管理（后台）
 *
 * 路由前缀：/admin/announcement
 *  - 管理端接口：分页/新建/编辑/发布/下线/删除
 *  - 前台公开接口：/admin/announcement/active、/admin/announcement/active/{id}
 *
 * 路由声明顺序约束：FastRoute 要求静态段必须早于变量段被注册，
 * 否则 /admin/announcement/batch 会被 /admin/announcement/{id} 覆盖
 * 而抛出 BadRouteException("is shadowed by previously defined variable route")。
 * 因此本控制器中所有静态路径（/active、/batch）都集中在 /{id} 路径之前声明。
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class AnnouncementController extends BaseController
{
    private AnnouncementService $announcementService;

    public function __construct()
    {
        parent::__construct();
        $this->announcementService = AnnouncementService::getInstance();
    }

    /**
     * 公告分页列表（管理端）。
     */
    #[Get('/announcement')]
    #[Validate(rules: [
        'page'       => 'integer|min:1',
        'limit'      => 'integer|min:1|max:100',
        'keyword'    => 'string|max:100',
        'category'   => 'string|in:notice,announcement,activity,maintenance',
        'level'      => 'string|in:info,important,urgent',
        'status'     => 'string|in:0,1,2',
        'is_top'     => 'string|in:0,1',
        'start_date' => 'date_format:Y-m-d',
        'end_date'   => 'date_format:Y-m-d',
    ])]
    public function index(Request $request): Response
    {
        return $this->pageResponse(
            $this->announcementService->pageList($request)
        );
    }

    /**
     * 前台有效公告列表（无需管理权限，已发布且在有效期内）。
     */
    #[Get('/announcement/active')]
    #[Validate(rules: [
        'limit'    => 'integer|min:1|max:50',
        'category' => 'string|in:notice,announcement,activity,maintenance',
    ])]
    public function active(Request $request): Response
    {
        return $this->success(
            $this->announcementService->activeList($request)
        );
    }

    /**
     * 批量删除（静态路径，必须早于 /{id} 注册）。
     */
    #[Delete('/announcement/batch')]
    #[RequiresPermission('system:announcement:del')]
    #[Validate(rules: ['ids' => 'required|array', 'ids.*' => 'integer|min:1'])]
    public function batchDestroy(Request $request): Response
    {
        $count = $this->announcementService->batchDelete(
            (array) $request->post('ids', []),
            $this->userId
        );
        return $this->success(['count' => $count], '删除成功');
    }

    /**
     * 新建公告。
     */
    #[Post('/announcement')]
    #[RequiresPermission('system:announcement:add')]
    #[Validate(validator: AnnouncementValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $ann = $this->announcementService->create($request->post(), $this->userId);
        return $this->success(['id' => $ann->id], '创建成功');
    }

    /**
     * 公告详情（管理端）。
     */
    #[Get('/announcement/{id}')]
    #[RequiresPermission('system:announcement:list')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->announcementService->detail($id));
    }

    /**
     * 前台公告详情。
     */
    #[Get('/announcement/active/{id}')]
    public function publicShow(Request $request, int $id): Response
    {
        return $this->success(
            $this->announcementService->publicDetail($id, $this->userId)
        );
    }

    /**
     * 更新公告。
     */
    #[Put('/announcement/{id}')]
    #[RequiresPermission('system:announcement:edit')]
    #[Validate(validator: AnnouncementValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->announcementService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除公告。
     */
    #[Delete('/announcement/{id}')]
    #[RequiresPermission('system:announcement:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->announcementService->delete($id, $this->userId);
        return $this->success(msg: '删除成功');
    }

    /**
     * 发布公告。
     */
    #[Put('/announcement/{id}/publish')]
    #[RequiresPermission('system:announcement:publish')]
    public function publish(Request $request, int $id): Response
    {
        $this->announcementService->publish($id, $this->userId);
        return $this->success(msg: '发布成功');
    }

    /**
     * 下线公告。
     */
    #[Put('/announcement/{id}/offline')]
    #[RequiresPermission('system:announcement:publish')]
    public function offline(Request $request, int $id): Response
    {
        $this->announcementService->offline($id, $this->userId);
        return $this->success(msg: '下线成功');
    }

    /**
     * 切换置顶。
     */
    #[Put('/announcement/{id}/toggle-top')]
    #[RequiresPermission('system:announcement:edit')]
    public function toggleTop(Request $request, int $id): Response
    {
        $ann = $this->announcementService->toggleTop($id, $this->userId);
        return $this->success(['is_top' => (int) $ann->is_top], '操作成功');
    }

    /**
     * 切换弹窗强提示。
     */
    #[Put('/announcement/{id}/toggle-popup')]
    #[RequiresPermission('system:announcement:edit')]
    public function togglePopup(Request $request, int $id): Response
    {
        $ann = $this->announcementService->togglePopup($id, $this->userId);
        return $this->success(['is_popup' => (int) $ann->is_popup], '操作成功');
    }
}
