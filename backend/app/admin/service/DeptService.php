<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\model\SysDepartment;
use app\model\SysUser;
use Illuminate\Support\Collection;
use support\Request;

/**
 * 部门业务服务
 *
 * 业务规则：
 *  - 部门支持无限层级（parent_id 自关联）
 *  - 不能将自身或后代设为父级（防止循环引用）
 *  - 删除前必须无子部门、无关联用户
 *  - 根部门（id=1）不可删除
 */
class DeptService extends BaseService
{
    /** 根部门 ID（不可删除） */
    public const ROOT_DEPT_ID = 1;

    protected string $modelClass = SysDepartment::class;

    /**
     * 部门树形列表。
     *
     * @return array<int,array<string,mixed>>
     */
    public function treeList(Request $request): array
    {
        $query = $this->newQuery();
        $this->applyFilters(
            $query,
            filters: ['status' => $request->get('status', '')],
            like:    ['name'   => $request->get('keyword', '')]
        );
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get()->toTree();
    }

    /**
     * 部门平铺列表。
     */
    public function flatList(): iterable
    {
        return $this->newQuery()->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
    }

    /**
     * 父级部门选项（排除自身与所有后代）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function parentOptions(int $excludeId = 0): array
    {
        $query = $this->newQuery();
        if ($excludeId > 0) {
            $dept = SysDepartment::find($excludeId);
            if ($dept) {
                $query->whereNotIn('id', array_merge([$excludeId], $dept->getDescendantIds()));
            }
        }
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc')
                     ->get(['id', 'parent_id', 'name'])->toArray();
    }

    /**
     * 部门用户树（用于选人组件）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function userTree(): array
    {
        $depts = SysDepartment::where('status', SysDepartment::STATUS_NORMAL)
                              ->orderBy('sort', 'asc')
                              ->get(['id', 'parent_id', 'name'])
                              ->toTree();

        $users = SysUser::where('status', SysUser::STATUS_NORMAL)
                        ->get(['id', 'dept_id', 'username', 'nickname'])
                        ->groupBy('dept_id');

        return $this->attachUsersToTree($depts, $users);
    }

    /**
     * 部门详情（含父级名称和用户数）。
     */
    public function detail(int $id): SysDepartment
    {
        $dept = SysDepartment::leftJoin('sys_department as parent', 'sys_department.parent_id', '=', 'parent.id')
                             ->select('sys_department.*', 'parent.name as parent_name')
                             ->where('sys_department.id', $id)
                             ->first();
        if (!$dept) {
            throw BusinessException::notFound('部门不存在');
        }
        $dept->user_count = SysUser::where('dept_id', $id)->count();
        return $dept;
    }

    /**
     * 创建部门。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $operatorId): SysDepartment
    {
        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0 && !SysDepartment::find($parentId)) {
            throw new BusinessException('父级部门不存在');
        }

        return SysDepartment::create($this->buildDeptPayload($data, $parentId, $operatorId, isUpdate: false));
    }

    /**
     * 更新部门。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysDepartment
    {
        /** @var SysDepartment $dept */
        $dept     = $this->findOrFail($id, [], '部门不存在');
        $parentId = (int) ($data['parent_id'] ?? 0);

        if ($parentId === $id) {
            throw new BusinessException('不能将自己设为父级部门');
        }
        if ($parentId > 0 && in_array($parentId, $dept->getDescendantIds(), true)) {
            throw new BusinessException('不能将自己的子级设为父级部门');
        }

        $dept->fill($this->buildDeptPayload($data, $parentId, $operatorId, isUpdate: true))->save();
        return $dept;
    }

    /**
     * 删除部门。
     */
    public function delete(int $id): void
    {
        if ($id === self::ROOT_DEPT_ID) {
            throw new BusinessException('不能删除根部门');
        }
        $this->findOrFail($id, [], '部门不存在');

        if (SysDepartment::where('parent_id', $id)->exists()) {
            throw new BusinessException('请先删除子部门');
        }
        if (SysUser::where('dept_id', $id)->exists()) {
            throw new BusinessException('该部门下有用户，不能删除');
        }

        SysDepartment::where('id', $id)->delete();
    }

    /**
     * 构建部门字段（创建/更新共用）。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function buildDeptPayload(array $data, int $parentId, int $operatorId, bool $isUpdate): array
    {
        $payload = [
            'parent_id' => $parentId,
            'name'      => trim((string) ($data['name']   ?? '')),
            'leader'    => trim((string) ($data['leader'] ?? '')) ?: null,
            'mobile'    => trim((string) ($data['mobile'] ?? '')) ?: null,
            'email'     => trim((string) ($data['email']  ?? '')) ?: null,
            'sort'      => (int) ($data['sort']   ?? 0),
            'status'    => (int) ($data['status'] ?? SysDepartment::STATUS_NORMAL),
            'remark'    => trim((string) ($data['remark'] ?? '')),
        ];
        if ($isUpdate) {
            $payload['updated_by'] = $operatorId;
            $payload['updated_at'] = $this->now();
        } else {
            $payload['created_by'] = $operatorId;
            $payload['created_at'] = $this->now();
        }
        return $payload;
    }

    /**
     * 将用户附加到部门树形结构上。
     *
     * @param iterable<mixed>                            $depts
     * @param Collection<int|string,Collection<int,SysUser>> $users
     * @return array<int,array<string,mixed>>
     */
    private function attachUsersToTree(iterable $depts, Collection $users): array
    {
        $result = [];
        foreach ($depts as $dept) {
            $item = [
                'id'       => 'dept_' . $dept->id,
                'name'     => $dept->name,
                'type'     => 'dept',
                'children' => [],
            ];

            foreach ($users->get($dept->id, collect()) as $user) {
                $item['children'][] = [
                    'id'   => 'user_' . $user->id,
                    'name' => $user->nickname ?: $user->username,
                    'type' => 'user',
                ];
            }

            if (!empty($dept->children)) {
                $item['children'] = array_merge(
                    $item['children'],
                    $this->attachUsersToTree($dept->children, $users)
                );
            }

            $result[] = $item;
        }
        return $result;
    }
}
