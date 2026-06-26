<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\model\AiTool;
use support\Request;

/**
 * AI 工具库管理服务
 *
 * 工具库是全局资源：所有 Agent 共用一套工具定义。
 * Agent 与工具通过关联表 ai_agent_tool_relation 建立多对多绑定。
 */
class AiAgentToolService extends BaseService
{
    protected string $modelClass = AiTool::class;

    /**
     * 分页列表
     */
    public function pageList(Request $request): array
    {
        $query = AiTool::query();

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['name', 'code', 'description']);
        $this->applyFilters($query, [
            'tool_type' => $request->get('tool_type'),
            'status'    => $request->get('status'),
        ]);

        $query->orderBy('sort')->orderBy('id', 'desc');

        return $this->paginate($query, $request);
    }

    /**
     * 获取所有可用工具（Agent 绑定选择用）
     */
    public function availableTools(): array
    {
        return AiTool::query()
            ->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'description', 'tool_type'])
            ->toArray();
    }

    /**
     * 详情
     */
    public function detail(int $id): AiTool
    {
        /** @var AiTool $tool */
        $tool = $this->findOrFail($id);
        return $tool;
    }

    /**
     * 创建
     */
    public function create(array $data, int $userId): AiTool
    {
        $this->assertUnique('code', $data['code']);
        $data = $this->normalizeJsonFields($data);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        return AiTool::createData($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data, int $userId): bool
    {
        /** @var AiTool $tool */
        $tool = $this->findOrFail($id);
        if (isset($data['code']) && $data['code'] !== $tool->code) {
            $this->assertUnique('code', $data['code'], $id);
        }
        $data = $this->normalizeJsonFields($data);
        $data['updated_by'] = $userId;
        return $tool->updateData($data);
    }

    /**
     * 删除（同时解除所有 Agent 的绑定）
     */
    public function delete(int $id): void
    {
        /** @var AiTool $tool */
        $tool = $this->findOrFail($id);
        $this->transaction(function () use ($tool) {
            // 解除所有关联
            $tool->agents()->detach();
            $tool->delete();
        });
    }

    /**
     * 归一化 JSON 字段：parameters_schema / config 接受字符串或数组
     */
    private function normalizeJsonFields(array $data): array
    {
        foreach (['parameters_schema', 'config'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === '' || $value === null) {
                $data[$field] = null;
                continue;
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                // 解析失败时保留原字符串，模型 cast 会处理
                $data[$field] = $decoded === null ? $value : $decoded;
            }
        }
        return $data;
    }
}
