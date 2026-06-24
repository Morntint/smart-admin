<?php

namespace app\admin\controller;

use app\admin\service\UserService;
use app\admin\validation\UserValidator;
use app\common\attribute\Idempotent;
use app\common\attribute\RequiresPermission;
use OpenApi\Attributes as OA;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Patch;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Request;
use support\Response;
use support\validation\annotation\Validate;

/**
 * 用户管理（后台）
 *
 * 路由前缀：/admin/user
 */
#[DisableDefaultRoute]
#[RouteGroup('/admin')]
class UserController extends BaseController
{
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = UserService::getInstance();
    }

    /**
     * 用户分页列表
     */
    #[OA\Get(
        path: '/admin/user',
        summary: '用户分页列表',
        tags: ['用户管理'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'keyword', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['0', '1'])),
            new OA\Parameter(name: 'dept_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '成功',
                content: new OA\JsonContent(ref: '#/components/schemas/Pagination')
            ),
        ]
    )]
    #[Get('/user')]
    #[Validate(rules: [
        'page'    => 'integer|min:1',
        'limit'   => 'integer|min:1|max:100',
        'keyword' => 'string|max:100',
        'status'  => 'string|in:0,1',
        'dept_id' => 'integer|min:0',
    ])]
    public function index(Request $request): Response
    {
        return $this->pageResponse($this->userService->pageList($request));
    }

    /**
     * 导出用户（轻量字段）
     */
    #[Get('/user/export')]
    public function export(Request $request): Response
    {
        return $this->success($this->userService->exportList($request));
    }

    /**
     * 获取当前登录用户信息
     * 注意：必须放在 /user/{id} 路由之前，否则会被动态路由匹配覆盖
     */
    #[Get('/user/info')]
    public function info(Request $request): Response
    {
        $user = $this->userService->detail($this->userId);
        return $this->success([
            'userId' => $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->roles ? $user->roles->pluck('code')->all() : [],
            'buttons' => \app\admin\service\PermissionService::getInstance()->getPermissions($this->userId),
        ]);
    }

    /**
     * 用户详情
     */
    #[Get('/user/{id}')]
    public function show(Request $request, int $id): Response
    {
        return $this->success($this->userService->detail($id));
    }

    /**
     * 创建用户
     */
    #[OA\Post(
        path: '/admin/user',
        summary: '创建用户',
        tags: ['用户管理'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'zhangsan'),
                    new OA\Property(property: 'password', type: 'string', example: '123456'),
                    new OA\Property(property: 'nickname', type: 'string', example: '张三'),
                    new OA\Property(property: 'email', type: 'string', example: 'zhangsan@example.com'),
                    new OA\Property(property: 'mobile', type: 'string', example: '13800138000'),
                    new OA\Property(property: 'dept_id', type: 'integer', example: 1),
                    new OA\Property(property: 'status', type: 'integer', enum: [0, 1], example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '创建成功',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiSuccess')
            ),
            new OA\Response(
                response: 409,
                description: '用户名/手机号已存在',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiError')
            ),
        ]
    )]
    #[Post('/user')]
    #[RequiresPermission('system:user:add')]
    #[Idempotent(window: 5)]
    #[Validate(validator: UserValidator::class, scene: 'create')]
    public function store(Request $request): Response
    {
        $user = $this->userService->create($request->post(), $this->userId);
        return $this->success(['id' => $user->id], '创建成功');
    }

    /**
     * 更新用户
     */
    #[Put('/user/{id}')]
    #[RequiresPermission('system:user:edit')]
    #[Validate(validator: UserValidator::class, scene: 'update')]
    public function update(Request $request, int $id): Response
    {
        $this->userService->update($id, $request->post(), $this->userId);
        return $this->success(msg: '更新成功');
    }

    /**
     * 删除用户
     */
    #[Delete('/user/{id}')]
    #[RequiresPermission('system:user:del')]
    public function destroy(Request $request, int $id): Response
    {
        $this->userService->delete($id, $this->userId);
        return $this->success(msg: '删除成功');
    }

    /**
     * 重置密码（管理员操作）
     */
    #[Put('/user/{id}/reset-password')]
    #[RequiresPermission('system:user:resetPwd')]
    #[Validate(validator: UserValidator::class, scene: 'resetPassword')]
    public function resetPassword(Request $request, int $id): Response
    {
        $this->userService->resetPassword(
            $id,
            (string) $request->post('password', ''),
            $this->userId
        );
        return $this->success(msg: '密码重置成功');
    }

    /**
     * 修改自己的密码
     */
    #[Post('/user/change-password')]
    #[Validate(validator: UserValidator::class, scene: 'changePassword')]
    public function changePassword(Request $request): Response
    {
        $this->userService->changePassword(
            $this->userId,
            (string) $request->post('old_password', ''),
            (string) $request->post('new_password', '')
        );
        return $this->success(msg: '密码修改成功');
    }

    /**
     * 修改个人资料
     */
    #[Post('/user/profile')]
    #[Validate(validator: UserValidator::class, scene: 'profile')]
    public function profile(Request $request): Response
    {
        $this->userService->updateProfile($this->userId, $request->post());
        return $this->success(msg: '修改成功');
    }

    /**
     * 切换用户状态
     */
    #[Patch('/user/{id}/status')]
    #[RequiresPermission('system:user:edit')]
    public function toggleStatus(Request $request, int $id): Response
    {
        $user = $this->userService->toggleStatus($id, $this->userId);
        return $this->success(['status' => $user->status]);
    }
}
