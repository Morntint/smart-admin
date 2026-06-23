<?php

namespace app\admin\service;

use app\model\SysRole;
use app\model\SysUser;

/**
 * 数据范围服务
 *
 * 计算用户的「有效数据范围」：综合用户所有正常状态角色的 data_scope，
 * 按「取并集（最宽）」规则归并为三态结果，供 BaseService::applyDataScope 过滤查询。
 *
 * 取并集规则：
 *  - 任一角色为「全部数据」→ all（短路）
 *  - 否则合并各「部门型」范围（本部门 / 本部门及下级 / 自定义）的部门 ID 集合 → dept
 *  - 仅「仅本人」且无任何部门范围 → self
 *
 * 缓存：user_data_scope_{uid}（5min）；角色 data_scope 变更时由
 * PermissionService::clearCache 连带失效。
 *
 * 注意：本服务不处理超级管理员（保持纯粹），超管跳过由调用方
 *      （BaseService::applyDataScope）判断。
 */
class DataScopeService
{
    /** 缓存 TTL（秒） */
    private const CACHE_TTL = 300;

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 获取用户有效数据范围（带缓存）。
     *
     * @return array{scope:string,deptIds:int[]} scope ∈ all|dept|self
     */
    public function getEffectiveScope(int $userId): array
    {
        $cached = cache($this->key($userId));
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->computeScope($userId);
        cache([$this->key($userId) => $result], self::CACHE_TTL);
        return $result;
    }

    /**
     * 清除用户数据范围缓存。
     */
    public function clearCache(int $userId): void
    {
        cache()->delete($this->key($userId));
    }

    /**
     * 计算用户有效数据范围（不走缓存）。
     *
     * @return array{scope:string,deptIds:int[]}
     */
    private function computeScope(int $userId): array
    {
        $user = SysUser::find($userId);
        if (!$user) {
            return ['scope' => 'self', 'deptIds' => []];
        }

        // 仅取正常状态角色
        $roles = $user->roles()
            ->where('sys_role.status', SysRole::STATUS_NORMAL)
            ->get(['sys_role.id', 'data_scope', 'data_scope_depts']);

        if ($roles->isEmpty()) {
            // 无任何角色：最严格，仅本人
            return ['scope' => 'self', 'deptIds' => []];
        }

        $deptIds    = [];
        $hasDept    = false;
        $hasSelf    = false;
        $userDeptId = (int) ($user->dept_id ?? 0);

        foreach ($roles as $role) {
            switch ((int) $role->data_scope) {
                case SysRole::DATA_SCOPE_ALL:
                    // 最宽即全部，短路
                    return ['scope' => 'all', 'deptIds' => []];

                case SysRole::DATA_SCOPE_DEPT:
                    if ($userDeptId > 0) {
                        $deptIds[] = $userDeptId;
                        $hasDept   = true;
                    }
                    break;

                case SysRole::DATA_SCOPE_DEPT_AND_CHILD:
                    if ($userDeptId > 0) {
                        $deptIds[] = $userDeptId;
                        if ($user->department) {
                            $deptIds = array_merge($deptIds, $user->department->getDescendantIds());
                        }
                        $hasDept = true;
                    }
                    break;

                case SysRole::DATA_SCOPE_SELF:
                    $hasSelf = true;
                    break;

                case SysRole::DATA_SCOPE_CUSTOM:
                    $custom = $role->data_scope_depts
                        ? array_filter(array_map('intval', explode(',', $role->data_scope_depts)))
                        : [];
                    if ($custom !== []) {
                        $deptIds = array_merge($deptIds, $custom);
                        $hasDept = true;
                    }
                    break;
            }
        }

        // 部门型优先于 self（取并集，部门范围更宽）
        if ($hasDept) {
            return ['scope' => 'dept', 'deptIds' => array_values(array_unique($deptIds))];
        }
        if ($hasSelf) {
            return ['scope' => 'self', 'deptIds' => []];
        }

        // 兜底：角色均为部门型但用户无 dept_id → 看不到任何部门数据
        return ['scope' => 'dept', 'deptIds' => []];
    }

    private function key(int $userId): string
    {
        return "user_data_scope_{$userId}";
    }
}
