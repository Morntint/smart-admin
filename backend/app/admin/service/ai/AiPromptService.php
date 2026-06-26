<?php

namespace app\admin\service\ai;

use app\admin\service\BaseService;
use app\model\AiPromptTemplate;
use support\Request;

/**
 * AI 提示词模板管理服务
 */
class AiPromptService extends BaseService
{
    protected string $modelClass = AiPromptTemplate::class;

    /**
     * 分页列表
     */
    public function pageList(Request $request): array
    {
        $query = AiPromptTemplate::query();

        $this->applyKeyword($query, (string) $request->get('keyword', ''), ['name', 'code', 'description']);
        $this->applyFilters($query, [
            'category' => $request->get('category'),
            'status'   => $request->get('status'),
        ]);

        return $this->paginate($query, $request);
    }

    /**
     * 详情
     */
    public function detail(int $id): AiPromptTemplate
    {
        /** @var AiPromptTemplate $template */
        $template = $this->findOrFail($id);
        return $template;
    }

    /**
     * 创建
     */
    public function create(array $data, int $userId): AiPromptTemplate
    {
        $this->assertUnique('code', $data['code']);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        return AiPromptTemplate::createData($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data, int $userId): bool
    {
        /** @var AiPromptTemplate $template */
        $template = $this->findOrFail($id);
        $this->assertUnique('code', $data['code'], $id);
        $data['updated_by'] = $userId;
        return $template->updateData($data);
    }

    /**
     * 删除
     */
    public function delete(int $id): void
    {
        /** @var AiPromptTemplate $template */
        $template = $this->findOrFail($id);
        if ($template->is_system) {
            throw new \app\common\exception\BusinessException('系统内置模板不可删除');
        }
        $template->delete();
    }

    /**
     * 按编码获取模板并渲染
     */
    public function renderByCode(string $code, array $variables = []): string
    {
        /** @var AiPromptTemplate|null $template */
        $template = AiPromptTemplate::where('code', $code)->where('status', 1)->first();
        if (!$template) {
            throw new \app\common\exception\BusinessException('提示词模板不存在或已禁用');
        }
        return $this->render($template->content, $variables);
    }

    /**
     * 渲染变量占位符 {{variable}}
     */
    public function render(string $content, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];
            return $variables[$key] ?? '';
        }, $content);
    }

    /**
     * 按分类获取模板列表（下拉用）
     */
    public function listByCategory(): array
    {
        return AiPromptTemplate::where('status', 1)
            ->orderBy('category')
            ->orderBy('sort')
            ->get()
            ->groupBy('category')
            ->toArray();
    }
}
