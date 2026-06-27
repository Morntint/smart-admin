<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\common\exception\BusinessException;
use app\model\AiModel;
use support\Request;

/**
 * AI 模型管理服务
 */
class AiModelService extends BaseService
{
    protected string $modelClass = AiModel::class;

    /**
     * 分页列表
     */
    public function pageList(Request $request): array
    {
        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = AiModel::query();

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['name', 'model_name', 'provider']);
        $this->applyFilters($query, [
            'provider' => $request->get('provider'),
            'status'   => $request->get('status'),
        ]);

        // 不返回明文 API Key
        $result = $this->paginate($query, $request);
        $result['list']->transform(function ($item) {
            /** @var AiModel $item */
            $item->api_key = $this->maskApiKey($item->api_key);
            return $item;
        });

        return $result;
    }

    /**
     * 详情（API Key 脱敏）
     */
    public function detail(int $id): AiModel
    {
        /** @var AiModel $model */
        $model = $this->findOrFail($id);
        $model->api_key = $this->maskApiKey($model->api_key);
        return $model;
    }

    /**
     * 创建
     */
    public function create(array $data, int $userId): AiModel
    {
        $this->assertUnique('name', $data['name']);
        // api_key 通过 AiModel::apiKey() accessor 自动加密入库（{@see app\common\support\Crypto}）
        $data['api_key']     = $data['api_key'] ?? '';
        $data['created_by']  = $userId;
        $data['updated_by']  = $userId;
        return AiModel::createData($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data, int $userId): bool
    {
        /** @var AiModel $model */
        $model = $this->findOrFail($id);
        $this->assertUnique('name', $data['name'], $id);
        // API Key 留空表示不更新
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }
        $data['updated_by'] = $userId;
        return $model->updateData($data);
    }

    /**
     * 删除
     */
    public function delete(int $id): void
    {
        /** @var AiModel $model */
        $model = $this->findOrFail($id);
        // 检查是否有 Agent 正在使用
        $agentCount = \app\model\AiAgent::where('model_id', $id)->count();
        if ($agentCount > 0) {
            throw new BusinessException("该模型已被 {$agentCount} 个 Agent 使用，无法删除");
        }
        $model->delete();
    }

    /**
     * 获取启用的模型列表（下拉选项用）
     */
    public function enabledList(): array
    {
        return AiModel::query()->where('status', 1)->orderBy('sort')->get()->toArray();
    }

    /**
     * 获取模型完整配置（包含明文 API Key，仅内部调用）
     */
    public function getModelConfig(int $id): array
    {
        /** @var AiModel $model */
        $model = $this->findOrFail($id);
        return $model->toArray();
    }

    private function maskApiKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return '****';
        }
        return substr($key, 0, 4) . '****' . substr($key, -4);
    }
}
