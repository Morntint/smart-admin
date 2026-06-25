<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\model\SysUser;
use app\model\SysUserRole;
use support\Request;

/**
 * 用户业务服务
 *
 * 承载用户/角色相关的核心业务规则（唯一性校验、密码哈希、角色绑定、缓存清理），
 * 控制器仅负责参数解析与响应包装。
 */
class UserService extends BaseService
{
    /** 超级管理员 ID（不可删除/不可改状态） */
    public const SUPER_ADMIN_ID = 1;

    /** 默认重置密码 */
    private const DEFAULT_PASSWORD = '123456';

    protected string $modelClass = SysUser::class;

    /**
     * 分页查询用户列表。
     *
     * @return array{list:\Illuminate\Support\Collection,total:int,page:int,limit:int}
     */
    public function pageList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyFilters($query, filters: [
            'status'  => $request->get('status', ''),
            'dept_id' => $request->get('dept_id', ''),
        ]);

        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['username', 'nickname', 'mobile', 'email']
        );

        // 数据范围过滤：sys_user 用 dept_id 做部门过滤、用 id 做「本人」过滤
        $this->applyDataScope($query, (int) $request->admin_user_id, [
            'dept_field' => 'dept_id',
            'user_field' => 'id',
        ]);

        $result = $this->paginate($query, $request);
        /** @var \Illuminate\Database\Eloquent\Collection<int,SysUser> $list */
        $list = $result['list'];
        $list->load(['department', 'roles']);
        $list->each(function (SysUser $user) {
            $user->dept_name  = $user->department?->name;
            $user->role_names = $user->roles->pluck('name');
            $user->makeHidden(['department', 'roles']);
        });
        $result['list'] = $list;

        return $result;
    }

    /**
     * 查询用户详情（含部门、角色 ID 列表）。
     */
    public function detail(int $id): SysUser
    {
        /** @var SysUser $user */
        $user = $this->findOrFail($id, ['department', 'roles'], '用户不存在');
        $user->dept_name  = $user->department?->name;
        $user->role_ids   = $user->roles->pluck('id');
        $user->role_names = $user->roles->pluck('name');
        $user->makeHidden(['department', 'roles']);
        return $user;
    }

    /**
     * 创建用户。
     *
     * @param array<string,mixed> $data 已通过 Validator 校验的数据
     */
    public function create(array $data, int $operatorId): SysUser
    {
        $username = trim((string) ($data['username'] ?? ''));
        $mobile   = trim((string) ($data['mobile']   ?? ''));

        $this->assertUnique('username', $username, null, '用户名已存在');
        if ($mobile !== '') {
            $this->assertUnique('mobile', $mobile, null, '手机号已存在');
        }

        $user = $this->transaction(function () use ($data, $username, $mobile, $operatorId) {
            $user = SysUser::create([
                'username'   => $username,
                'password'   => make_password((string) $data['password']),
                'nickname'   => $data['nickname'] ?? null,
                'email'      => $data['email']    ?? null,
                'mobile'     => $mobile !== '' ? $mobile : null,
                'sex'        => (int) ($data['sex']    ?? 0),
                'status'     => (int) ($data['status'] ?? SysUser::STATUS_NORMAL),
                'dept_id'    => $data['dept_id'] ?? null,
                'remark'     => $data['remark'] ?? null,
                'created_by' => $operatorId,
                'created_at' => $this->now(),
            ]);

            $roleIds = (array) ($data['role_ids'] ?? []);
            if ($roleIds !== []) {
                SysUserRole::addUserRoles($user->id, $roleIds);
            }

            return $user;
        });

        // 用户创建后清理自身权限缓存（首次登录会重建）
        if (!empty($data['role_ids'])) {
            clear_permission_cache($user->id);
        }

        return $user;
    }

    /**
     * 更新用户。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysUser
    {
        /** @var SysUser $user */
        $user   = $this->findOrFail($id, [], '用户不存在');
        $mobile = trim((string) ($data['mobile'] ?? ''));

        if ($mobile !== '') {
            $this->assertUnique('mobile', $mobile, $id, '手机号已存在');
        }

        $this->transaction(function () use ($user, $data, $mobile, $operatorId) {
            $user->fill([
                'nickname'   => $data['nickname'] ?? null,
                'email'      => $data['email']    ?? null,
                'mobile'     => $mobile !== '' ? $mobile : null,
                'sex'        => (int) ($data['sex']    ?? 0),
                'status'     => (int) ($data['status'] ?? SysUser::STATUS_NORMAL),
                'dept_id'    => $data['dept_id'] ?? null,
                'remark'     => $data['remark'] ?? null,
                'updated_by' => $operatorId,
                'updated_at' => $this->now(),
            ])->save();

            if (array_key_exists('role_ids', $data) && is_array($data['role_ids'])) {
                SysUserRole::addUserRoles($user->id, $data['role_ids']);
            }
        });

        clear_permission_cache($id);
        return $user;
    }

    /**
     * 删除用户。
     */
    public function delete(int $id, int $operatorId): void
    {
        if ($id === $operatorId) {
            throw new BusinessException('不能删除当前登录用户');
        }
        if ($id === self::SUPER_ADMIN_ID) {
            throw new BusinessException('不能删除超级管理员');
        }

        $this->findOrFail($id, [], '用户不存在');

        $this->transaction(function () use ($id) {
            SysUser::where('id', $id)->delete();
            SysUserRole::where('user_id', $id)->delete();
        });

        clear_permission_cache($id);
    }

    /**
     * 重置用户密码（管理员操作）。
     */
    public function resetPassword(int $id, string $newPassword, int $operatorId): void
    {
        $newPassword = $newPassword !== '' ? $newPassword : self::DEFAULT_PASSWORD;

        /** @var SysUser $user */
        $user = $this->findOrFail($id, [], '用户不存在');
        $user->password   = make_password($newPassword);
        $user->updated_by = $operatorId;
        $user->updated_at = $this->now();
        $user->save();

        // 重置密码后使该用户已签发的 Token 全部失效，强制重新登录
        $user->bumpTokenVersion();
        clear_permission_cache($id);
    }

    /**
     * 用户自己修改密码。
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): void
    {
        if ($oldPassword === '' || $newPassword === '') {
            throw BusinessException::badRequest('原密码与新密码均不能为空');
        }
        if ($oldPassword === $newPassword) {
            throw BusinessException::badRequest('新密码不能与原密码相同');
        }

        /** @var SysUser $user */
        $user = $this->findOrFail($userId, [], '用户不存在');
        if (!$user->verifyPassword($oldPassword)) {
            throw new BusinessException('原密码错误');
        }
        $user->password   = make_password($newPassword);
        $user->updated_at = $this->now();
        $user->save();

        // 改密后使旧 Token 全部失效（含当前会话），前端应引导重新登录
        $user->bumpTokenVersion();
        clear_permission_cache($userId);
    }

    /**
     * 修改个人资料。
     *
     * @param array<string,mixed> $data
     */
    public function updateProfile(int $userId, array $data): SysUser
    {
        /** @var SysUser $user */
        $user   = $this->findOrFail($userId, [], '用户不存在');
        $email  = trim((string) ($data['email']  ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));

        if ($email !== '') {
            $this->assertUnique('email', $email, $userId, '邮箱已被使用');
        }
        if ($mobile !== '') {
            $this->assertUnique('mobile', $mobile, $userId, '手机号已被使用');
        }

        $user->fill(array_filter([
            'nickname' => $data['nickname'] ?? null,
            'email'    => $email  !== '' ? $email  : null,
            'mobile'   => $mobile !== '' ? $mobile : null,
            'avatar'   => $data['avatar'] ?? null,
        ], fn($v) => $v !== null));
        $user->updated_at = $this->now();
        $user->save();

        return $user;
    }

    /**
     * 切换用户状态。
     */
    public function toggleStatus(int $id, int $operatorId): SysUser
    {
        if ($id === self::SUPER_ADMIN_ID) {
            throw new BusinessException('不能修改超级管理员状态');
        }
        /** @var SysUser $user */
        $user = $this->findOrFail($id, [], '用户不存在');

        $user->status = $user->status === SysUser::STATUS_NORMAL
            ? SysUser::STATUS_DISABLED
            : SysUser::STATUS_NORMAL;
        $user->updated_by = $operatorId;
        $user->updated_at = $this->now();
        $user->save();

        // 禁用时使其已签发 Token 立即失效（与状态校验形成双保险，避免缓存窗口期内仍可访问）
        if ($user->status === SysUser::STATUS_DISABLED) {
            $user->bumpTokenVersion();
        }

        clear_permission_cache($id);
        return $user;
    }

    /**
     * 导出用户（轻量字段映射，供 Excel 导出调用）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['username', 'nickname']
        );

        /** @var \Illuminate\Database\Eloquent\Collection<int,SysUser> $users */
        $users = $query->orderBy('id', 'desc')->get();
        return $users->map(fn(SysUser $u) => [
            'id'          => $u->id,
            'username'    => $u->username,
            'nickname'    => $u->nickname,
            'mobile'      => $u->mobile,
            'email'       => $u->email,
            'status'      => $u->status === SysUser::STATUS_NORMAL ? '正常' : '禁用',
            'login_count' => $u->login_count,
            'login_time'  => $u->login_time,
            'created_at'  => $u->created_at,
        ])->all();
    }
}
