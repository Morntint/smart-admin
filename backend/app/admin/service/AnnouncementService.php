<?php

namespace app\admin\service;

use app\admin\service\PermissionService;
use app\common\exception\BusinessException;
use app\common\support\HtmlSanitizer;
use app\model\SysAnnouncement;
use app\model\SysUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use support\Redis;
use support\Request;
use Throwable;

/**
 * 系统公告业务服务
 *
 * 业务规则：
 *  - 状态：草稿(0) / 已发布(1) / 已下线(2)
 *  - 已发布公告前台仅在「生效时间 ≤ now < 失效时间」区间内展示
 *  - 置顶(is_top=1)在前台按 sort ASC, is_top DESC, published_at DESC 排
 *  - 同一公告发布人=当前用户；下线操作由发布人或超管执行
 *  - 删除公告为软删除，保留数据用于审计
 */
class AnnouncementService extends BaseService
{
    protected string $modelClass = SysAnnouncement::class;

    /**
     * 公告分页列表（管理端）。
     *
     * @return array{list:Collection,total:int,page:int,limit:int}
     */
    public function pageList(Request $request): array
    {
        $query = $this->newQuery();

        $this->applyFilters($query, filters: [
            'category' => $request->get('category', ''),
            'level'    => $request->get('level', ''),
            'status'   => $request->get('status', ''),
            'is_top'   => $request->get('is_top', ''),
        ]);

        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['title', 'remark']
        );

        $this->applyDateRange(
            $query,
            'created_at',
            (string) $request->get('start_date', ''),
            (string) $request->get('end_date', '')
        );

        $query->with(['publisher:id,username,nickname'])
              ->orderBy('is_top', 'desc')
              ->orderBy('sort', 'asc')
              ->orderBy('id', 'desc');

        return $this->paginate($query, $request, defaultLimit: 20);
    }

    /**
     * 前台用户可见的有效公告列表（用于站内公告弹窗/列表）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function activeList(Request $request): array
    {
        $now = $this->now();
        $query = $this->newQuery()
            ->where('status', SysAnnouncement::STATUS_PUBLISHED)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('effective_at')->orWhere('effective_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('expire_at')->orWhere('expire_at', '>', $now);
            });

        $category = trim((string) $request->get('category', ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        $limit = min(50, max(1, (int) $request->get('limit', 20)));

        return $query->with(['publisher:id,username,nickname'])
            ->orderBy('is_top', 'desc')
            ->orderBy('sort', 'asc')
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 公告详情（管理端）。
     */
    public function detail(int $id): SysAnnouncement
    {
        /** @var SysAnnouncement $ann */
        $ann = $this->findOrFail($id, ['publisher'], '公告不存在');
        return $ann;
    }

    /**
     * 前台公告详情（仅返回已发布且在有效期内的）。
     *
     * 同一个用户对同一个公告的访问 5 分钟内不重复 +1 view_count（BE-A1 防刷）。
     */
    public function publicDetail(int $id, int $viewerId = 0): SysAnnouncement
    {
        /** @var ?SysAnnouncement $ann */
        $ann = $this->newQuery()->find($id);
        if (!$ann) {
            throw BusinessException::notFound('公告不存在');
        }
        if ((int) $ann->status !== SysAnnouncement::STATUS_PUBLISHED) {
            throw BusinessException::notFound('公告未发布或已下线');
        }
        $now = $this->now();
        if ($ann->effective_at && $ann->effective_at > $now) {
            throw BusinessException::notFound('公告尚未生效');
        }
        if ($ann->expire_at && $ann->expire_at <= $now) {
            throw BusinessException::notFound('公告已过期');
        }

        // view_count 去重：5 分钟内同一用户重复访问只算 1 次
        if ($this->shouldIncrementView($id, $viewerId)) {
            $ann->increment('view_count');
        }

        $ann->load(['publisher:id,username,nickname']);
        return $ann;
    }

    /**
     * 5 分钟窗口内的去重判定：基于 Redis SET NX。
     *
     * 未登录访问者（viewerId<=0）按 IP fallback；Redis 故障 fail-open 直接算新一次浏览，
     * 不阻断正常计数。
     */
    private function shouldIncrementView(int $annId, int $viewerId): bool
    {
        $key = 'ann_view:' . $annId . ':' . ($viewerId > 0 ? "u{$viewerId}" : 'anon');
        try {
            $ok = Redis::set($key, '1', 'EX', 300, 'NX');
            return (bool) $ok;
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * 创建公告（默认草稿状态）。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $operatorId): SysAnnouncement
    {
        $this->assertTimeRange($data);

        return SysAnnouncement::create([
            'title'        => trim((string) ($data['title'] ?? '')),
            'content'      => HtmlSanitizer::sanitize((string) ($data['content'] ?? '')),
            'category'     => trim((string) ($data['category'] ?? SysAnnouncement::CATEGORY_NOTICE)),
            'level'        => trim((string) ($data['level'] ?? SysAnnouncement::LEVEL_INFO)),
            'is_top'       => (int) ($data['is_top'] ?? 0),
            'is_popup'     => (int) ($data['is_popup'] ?? 0),
            'status'       => (int) ($data['status'] ?? SysAnnouncement::STATUS_DRAFT),
            'publisher_id' => $operatorId > 0 ? $operatorId : null,
            'published_at' => $this->normalizeDateTime($data['published_at'] ?? null),
            'effective_at' => $this->normalizeDateTime($data['effective_at'] ?? null),
            'expire_at'    => $this->normalizeDateTime($data['expire_at'] ?? null),
            'sort'         => (int) ($data['sort'] ?? 0),
            'remark'       => trim((string) ($data['remark'] ?? '')) ?: null,
            'created_by'   => $operatorId,
            'created_at'   => $this->now(),
        ]);
    }

    /**
     * 更新公告。
     *
     * 校验：仅发布人 / 创建者 / 超管可改；其余角色即便拥有 system:announcement:edit
     * 权限也不能动他人公告（对象级权限）。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysAnnouncement
    {
        /** @var SysAnnouncement $ann */
        $ann = $this->findOrFail($id, [], '公告不存在');
        $this->assertCanOperate($ann, $operatorId);

        $this->assertTimeRange($data);

        $ann->fill([
            'title'        => trim((string) ($data['title'] ?? '')),
            'content'      => HtmlSanitizer::sanitize((string) ($data['content'] ?? '')),
            'category'     => trim((string) ($data['category'] ?? SysAnnouncement::CATEGORY_NOTICE)),
            'level'        => trim((string) ($data['level'] ?? SysAnnouncement::LEVEL_INFO)),
            'is_top'       => (int) ($data['is_top'] ?? 0),
            'is_popup'     => (int) ($data['is_popup'] ?? 0),
            'status'       => (int) ($data['status'] ?? $ann->status),
            'published_at' => $this->normalizeDateTime($data['published_at'] ?? $ann->published_at),
            'effective_at' => $this->normalizeDateTime($data['effective_at'] ?? $ann->effective_at),
            'expire_at'    => $this->normalizeDateTime($data['expire_at'] ?? $ann->expire_at),
            'sort'         => (int) ($data['sort'] ?? 0),
            'remark'       => trim((string) ($data['remark'] ?? '')) ?: null,
            'updated_by'   => $operatorId,
            'updated_at'   => $this->now(),
        ])->save();

        return $ann;
    }

    /**
     * 删除公告（软删除）。
     */
    public function delete(int $id, int $operatorId): void
    {
        /** @var SysAnnouncement $ann */
        $ann = $this->findOrFail($id, [], '公告不存在');
        $this->assertCanOperate($ann, $operatorId);
        $ann->delete();
    }

    /**
     * 批量删除。非超管仅能删自己发布/创建的公告。
     *
     * @param int[] $ids
     */
    public function batchDelete(array $ids, int $operatorId): int
    {
        if ($ids === []) {
            throw BusinessException::badRequest('请选择要删除的公告');
        }
        if (PermissionService::getInstance()->isSuperAdmin($operatorId)) {
            return SysAnnouncement::whereIn('id', $ids)->delete();
        }
        return SysAnnouncement::whereIn('id', $ids)
            ->where(function ($q) use ($operatorId) {
                $q->where('publisher_id', $operatorId)->orWhere('created_by', $operatorId);
            })
            ->delete();
    }

    /**
     * 发布公告（草稿→已发布）。
     */
    public function publish(int $id, int $operatorId): SysAnnouncement
    {
        /** @var SysAnnouncement $ann */
        $ann = $this->findOrFail($id, [], '公告不存在');
        $this->assertCanOperate($ann, $operatorId);
        if ((int) $ann->status === SysAnnouncement::STATUS_PUBLISHED) {
            return $ann;
        }
        $ann->status       = SysAnnouncement::STATUS_PUBLISHED;
        $ann->publisher_id = $ann->publisher_id ?: $operatorId;
        $ann->published_at = $ann->published_at ?: $this->now();
        $ann->updated_by   = $operatorId;
        $ann->updated_at   = $this->now();
        $ann->save();
        return $ann;
    }

    /**
     * 下线公告（已发布→已下线）。
     */
    public function offline(int $id, int $operatorId): SysAnnouncement
    {
        /** @var SysAnnouncement $ann */
        $ann = $this->findOrFail($id, [], '公告不存在');
        $this->assertCanOperate($ann, $operatorId);
        $ann->status     = SysAnnouncement::STATUS_OFFLINE;
        $ann->updated_by = $operatorId;
        $ann->updated_at = $this->now();
        $ann->save();
        return $ann;
    }

    /**
     * 切换置顶状态。
     */
    public function toggleTop(int $id, int $operatorId): SysAnnouncement
    {
        /** @var SysAnnouncement $ann */
        $ann = $this->findOrFail($id, [], '公告不存在');
        $this->assertCanOperate($ann, $operatorId);
        $ann->is_top     = (int) $ann->is_top === 1 ? 0 : 1;
        $ann->updated_by = $operatorId;
        $ann->updated_at = $this->now();
        $ann->save();
        return $ann;
    }

    /**
     * 切换弹窗强提示状态。
     */
    public function togglePopup(int $id, int $operatorId): SysAnnouncement
    {
        /** @var SysAnnouncement $ann */
        $ann = $this->findOrFail($id, [], '公告不存在');
        $this->assertCanOperate($ann, $operatorId);
        $ann->is_popup   = (int) $ann->is_popup === 1 ? 0 : 1;
        $ann->updated_by = $operatorId;
        $ann->updated_at = $this->now();
        $ann->save();
        return $ann;
    }

    /**
     * 对象级权限：发布人 / 创建者 / 超管可操作；其余抛 403。
     */
    private function assertCanOperate(SysAnnouncement $ann, int $operatorId): void
    {
        if (PermissionService::getInstance()->isSuperAdmin($operatorId)) {
            return;
        }
        $ownerIds = [(int) ($ann->publisher_id ?? 0), (int) ($ann->created_by ?? 0)];
        if (!in_array($operatorId, $ownerIds, true) || $operatorId <= 0) {
            throw BusinessException::forbidden('无权操作他人公告');
        }
    }

    /**
     * 清理已下线 N 天前的公告（物理删除）。
     */
    public function clearOffline(int $days = 30): int
    {
        $deadline = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return SysAnnouncement::where('status', SysAnnouncement::STATUS_OFFLINE)
            ->where('updated_at', '<', $deadline)
            ->delete();
    }

    // -------------------------------------------------------------------------
    // 私有方法
    // -------------------------------------------------------------------------

    /**
     * 校验生效/失效时间区间合法。
     *
     * @param array<string,mixed> $data
     */
    private function assertTimeRange(array $data): void
    {
        $effective = $data['effective_at'] ?? null;
        $expire    = $data['expire_at']    ?? null;
        if ($effective && $expire) {
            $effTs = is_numeric($effective) ? (int) $effective : strtotime((string) $effective);
            $expTs = is_numeric($expire) ? (int) $expire : strtotime((string) $expire);
            if ($effTs !== false && $expTs !== false && $effTs !== 0 && $expTs !== 0 && $effTs >= $expTs) {
                throw BusinessException::badRequest('失效时间必须晚于生效时间');
            }
        }
    }

    /**
     * 规范化日期时间。
     *
     * @param mixed $value
     * @return string|null
     */
    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
