<?php

namespace app\admin\controller;

use app\admin\service\NoticeService;
use app\admin\validation\NoticeValidator;
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
 * 系统通知管理（后台）
 *
 * 路由前缀：/admin/notice
 *  - 管理端接口：分页/发送/编辑/删除
 *  - 用户中心接口：/admin/notice/my（当前登录用户收件箱）
 *
 * 路由声明顺序约束：FastRoute 要求静态段必须早于变量段被注册，
 * 否则 /admin/notice/batch 会被 /admin/notice/{id} 覆盖
 * 而抛出 BadRouteException("is shadowed by previously defined variable route")。
 * 本控制器中所有静态路径（/my、/my/unread-stats、/batch、/batch-read、/read-all）
 * 都集中在 /{id} 路径之前声明。
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class NoticeController extends BaseController
{
    private NoticeService $noticeService;

    public function __construct()
    {
        parent::__construct();
        $this->noticeService = NoticeService::getInstance();
    }

    /**
     * 通知分页列表（管理端）。
     */
    #[Get('/notice')]
    #[Validate(rules: [
        'page'       => 'integer|min:1',
        'limit'      => 'integer|min:1|max:100',
        'keyword'    => 'string|max:100',
        'type'       => 'string|in:1,2,3,4',
        'level'      => 'string|in:info,success,warning,danger',
        'is_read'    => 'string|in:0,1',
        'user_id'    => 'integer|min:1',
        'start_date' => 'date_format:Y-m-d',
        'end_date'   => 'date_format:Y-m-d',
    ])]
    public function index(Request $request): Response
    {
        return $this->pageResponse(
            $this->noticeService->pageList($request, $this->userId)
        );
    }

    /**
     * 当前登录用户的收件箱（前端用户中心调用）。
     */
    #[Get('/notice/my')]
    #[Validate(rules: [
        'page'    => 'integer|min:1',
        'limit'   => 'integer|min:1|max:100',
        'keyword' => 'string|max:100',
        'type'    => 'string|in:1,2,3,4',
        'level'   => 'string|in:info,success,warning,danger',
        'is_read' => 'string|in:0,1',
    ])]
    public function myInbox(Request $request): Response
    {
        return $this->pageResponse(
            $this->noticeService->myInbox($request, $this->userId)
        );
    }

    /**
     * 当前用户未读数量统计。
     */
    #[Get('/notice/my/unread-stats')]
    public function myUnreadStats(Request $request): Response
    {
        return $this->success(
            $this->noticeService->myUnreadStats($this->userId)
        );
    }

    /**
     * 批量发送通知（静态路径，必须早于 /{id} 注册）。
     */
    #[Post('/notice/batch')]
    #[RequiresPermission('system:notice:add')]
    #[Validate(validator: NoticeValidator::class, scene: 'batch')]
    public function batchStore(Request $request): Response
    {
        $count = $this->noticeService->batchCreate($request->post(), $this->userId);
        return $this->success(['count' => $count], '发送成功');
    }

    /**
     * 批量删除通知（静态路径，必须早于 /{id} 注册）。
     */
    #[Delete('/notice/batch')]
    #[RequiresPermission('system:notice:del')]
    #[Validate(rules: ['ids' => 'required|array', 'ids.*' => 'integer|min:1'])]
    public function batchDestroy(Request $request): Response
    {
        $count = $this->noticeService->batchDelete(
            (array) $request->post('ids', []),
            $this->userId
        );
        return $this->success(['count' => $count], '删除成功');
    }

    /**
     * 批量标记已读（静态路径，必须早于 /{id}/read 注册）。
     *
     * 本接口用于管理端：超管可批量标记任意用户的通知，非超管仅能标记自己的。
     * 用户中心场景请用 /notice/my/batch-read。
     */
    #[Put('/notice/batch-read')]
    #[Validate(rules: ['ids' => 'required|array', 'ids.*' => 'integer|min:1'])]
    public function batchRead(Request $request): Response
    {
        $count = $this->noticeService->batchMarkRead(
            (array) $request->post('ids', []),
            $this->userId
        );
        return $this->success(['count' => $count], '已标记为已读');
    }

    /**
     * 用户中心：批量标记自己的通知为已读。
     */
    #[Put('/notice/my/batch-read')]
    #[Validate(rules: ['ids' => 'required|array', 'ids.*' => 'integer|min:1'])]
    public function myBatchRead(Request $request): Response
    {
        $count = $this->noticeService->batchMarkRead(
            (array) $request->post('ids', []),
            $this->userId,
            enforceUserId: $this->userId
        );
        return $this->success(['count' => $count], '已标记为已读');
    }

    /**
     * 当前用户全部标记已读（静态路径，必须早于 /{id}/... 变量路径注册）。
     */
    #[Put('/notice/read-all')]
    public function readAll(Request $request): Response
    {
        $count = $this->noticeService->markAllRead($this->userId);
        return $this->success(['count' => $count], '已全部标记为已读');
    }

    /**
     * 单条发送通知。
     */
    #[Post('/notice')]
    #[RequiresPermission('system:notice:add')]
    #[Validate(validator: NoticeValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $notice = $this->noticeService->create($request->post(), $this->userId);
        return $this->success(['id' => $notice->id], '发送成功');
    }

    /**
     * 通知详情。
     */
    #[Get('/notice/{id}')]
    #[RequiresPermission('system:notice:list')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->noticeService->detail($id));
    }

    /**
     * 当前用户查看自己的通知详情（无权限控制，仅限本人）。
     * 读取后自动标记已读。
     */
    #[Get('/notice/my/{id}')]
    public function myShow(Request $request, int $id): Response
    {
        $notice = $this->noticeService->detail($id);
        if ((int) $notice->user_id !== $this->userId) {
            return $this->error('无权查看该通知', 403);
        }
        // 阅后即标已读
        if ((int) $notice->is_read === 0) {
            $this->noticeService->markRead($id, $this->userId);
            $notice->refresh();
        }
        return $this->success($notice);
    }

    /**
     * 更新通知。
     */
    #[Put('/notice/{id}')]
    #[RequiresPermission('system:notice:edit')]
    #[Validate(validator: NoticeValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->noticeService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除通知。
     */
    #[Delete('/notice/{id}')]
    #[RequiresPermission('system:notice:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->noticeService->delete($id, $this->userId);
        return $this->success(msg: '删除成功');
    }

    /**
     * 标记单条已读。
     *
     * 任意登录用户都可调用，但只能标记自己的通知（service 层 canOperate 校验）。
     * 不再要求 system:notice:read 权限——这是"用户对自己通知的操作"，不是管理动作。
     */
    #[Put('/notice/{id}/read')]
    public function markRead(Request $request, int $id): Response
    {
        $this->noticeService->markRead($id, $this->userId);
        return $this->success(msg: '已标记为已读');
    }
}
