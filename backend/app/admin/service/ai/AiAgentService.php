<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\common\exception\BusinessException;
use app\model\AiAgent;
use app\model\AiTool;
use support\Request;

/**
 * AI Agent 管理服务
 */
class AiAgentService extends BaseService
{
    protected string $modelClass = AiAgent::class;

    /**
     * 分页列表
     */
    public function pageList(Request $request): array
    {
        $query = AiAgent::query()->with(['model:id,name,model_name', 'tools:id,name,code']);

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['name', 'code', 'description']);
        $this->applyFilters($query, [
            'status'   => $request->get('status'),
            'is_public' => $request->get('is_public'),
        ]);

        return $this->paginate($query, $request);
    }

    /**
     * 获取公开的 Agent 列表（对话工作台用）
     */
    public function publicList(Request $request): array
    {
        $query = AiAgent::query()->with(['model:id,name,model_name', 'tools:id,name,code,tool_type'])
            ->where('status', 1)
            ->where('is_public', 1);

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['name', 'description']);

        return $this->paginate($query, $request);
    }

    /**
     * 详情（含工具列表）
     */
    public function detail(int $id): AiAgent
    {
        /** @var AiAgent $agent */
        $agent = $this->findOrFail($id, ['model', 'tools']);
        return $agent;
    }

    /**
     * 创建
     */
    public function create(array $data, int $userId): AiAgent
    {
        $this->assertUnique('code', $data['code']);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        // tools 是工具 ID 数组：[1, 2, 3] 或带配置的数组
        $toolIds = $data['tools'] ?? [];
        unset($data['tools']);

        return $this->transaction(function () use ($data, $toolIds) {
            /** @var AiAgent $agent */
            $agent = AiAgent::createData($data);

            // 绑定工具（多对多）：支持 [1, 2, 3] 或 [{id: 1}] 两种格式
            if (!empty($toolIds)) {
                $ids = $this->normalizeToolIds($toolIds);
                if ($ids !== []) {
                    $agent->tools()->sync($ids);
                }
            }

            return $agent;
        });
    }

    /**
     * 更新
     */
    public function update(int $id, array $data, int $userId): bool
    {
        /** @var AiAgent $agent */
        $agent = $this->findOrFail($id);
        $this->assertUnique('code', $data['code'], $id);
        $data['updated_by'] = $userId;

        $toolIds = $data['tools'] ?? null;
        unset($data['tools']);

        return $this->transaction(function () use ($agent, $data, $toolIds) {
            $agent->updateData($data);

            // 重新绑定工具（如果传了 tools 才更新）：支持 [1, 2, 3] 或 [{id: 1}] 两种格式
            if ($toolIds !== null) {
                $ids = $this->normalizeToolIds($toolIds);
                $agent->tools()->sync($ids);
            }

            return true;
        });
    }

    /**
     * 删除（解除工具绑定）
     */
    public function delete(int $id): void
    {
        /** @var AiAgent $agent */
        $agent = $this->findOrFail($id);
        $this->transaction(function () use ($agent) {
            // 解除所有工具关联
            $agent->tools()->detach();
            $agent->delete();
        });
    }

    /**
     * 获取 Agent 完整配置（用于对话初始化）
     */
    public function getAgentConfig(int $id): array
    {
        /** @var AiAgent $agent */
        $agent = $this->findOrFail($id, ['model', 'tools']);
        if ($agent->status !== 1) {
            throw new BusinessException('Agent 已禁用');
        }
        if (!$agent->model || $agent->model->status !== 1) {
            throw new BusinessException('关联的 AI 模型不可用');
        }
        return $agent->toArray();
    }

    /**
     * 获取 Agent 可用的工具列表（供用户选择）
     *
     * @param int $agentId
     * @return array<int,array{id:int,name:string,code:string,description:string,icon:string|null}>
     */
    public function getAgentTools(int $agentId): array
    {
        /** @var AiAgent $agent */
        $agent = $this->findOrFail($agentId, ['tools']);
        if ($agent->status !== 1) {
            throw new BusinessException('Agent 已禁用');
        }

        return $agent->tools->map(function ($tool) {
            /** @var AiTool $tool */
            return [
                'id'          => $tool->id,
                'name'        => $tool->name,
                'code'        => $tool->code,
                'description' => $tool->description,
                'icon'        => $tool->icon ?? null,
            ];
        })->toArray();
    }

    /**
     * 获取 Agent 绑定的工具 ID 列表（用于会话/请求里所选工具的合法性校验）。
     *
     * @return int[]
     */
    public function getAgentToolIds(int $agentId): array
    {
        /** @var AiAgent $agent */
        $agent = $this->findOrFail($agentId, ['tools:id']);
        return $agent->tools->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * 把外部传入的 tool 列表规整成纯 int ID 数组：
     *  - 支持 [1, 2, 3] / [{id: 1}, {id: 2}] / [[1], [2]] 三种格式
     *  - 去重；丢弃 0、负数、非数字
     *  - 与 DB 对账丢弃不存在的 ID（避免外键脏数据）
     *
     * @param mixed[] $rawItems
     * @return int[]
     */
    private function normalizeToolIds(array $rawItems): array
    {
        $ids = [];
        foreach ($rawItems as $item) {
            if (is_array($item)) {
                $val = $item['id'] ?? $item[0] ?? null;
            } else {
                $val = $item;
            }
            $id = (int) $val;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        // 与 DB 对账：只保留存在且状态正常的 tool
        return AiTool::whereIn('id', $ids)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
