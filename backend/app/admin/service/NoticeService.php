<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\common\support\HtmlSanitizer;
use app\model\SysNotice;
use app\model\SysUser;
use Illuminate\Support\Collection;
use support\Request;

/**
 * 系统通知业务服务
 *
 * 业务规则：
 *  - 通知归属于具体用户(user_id)，不允许跨用户操作
 *  - 列表查询应用数据范围(超管看全部，普通用户仅看自己发出的/与自己相关)
 *  - 批量发送：一次最多 500 个用户；事务内逐条插入
 *  - 标记已读：单条或批量；过期通知(is_read=0 但已过 expire_time)会自动忽略
 */
class NoticeService extends BaseService
{
    protected string $modelClass = SysNotice::class;

    /** 批量发送最大用户数 */
    private const MAX_BATCH_SIZE = 500;

    /**
     * 通知分页列表（管理端）。
     *
     * @return array{list:Collection,total:int,page:int,limit:int}
     */
    public function pageList(Request $request, int $operatorId): array
    {
        $query = $this->newQuery();

        $this->applyFilters($query, filters: [
            'type'    => $request->get('type', ''),
            'level'   => $request->get('level', ''),
            'is_read' => $request->get('is_read', ''),
            'user_id' => $request->get('user_id', ''),
        ]);

        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['title', 'content']
        );

        $this->applyDateRange(
            $query,
            'created_at',
            (string) $request->get('start_date', ''),
            (string) $request->get('end_date', '')
        );

        $query->with(['user:id,username,nickname', 'sender:id,username,nickname'])
              ->orderBy('id', 'desc');

        return $this->paginate($query, $request, defaultLimit: 20);
    }

    /**
     * 当前登录用户收到的通知分页列表（前端用户中心使用）。
     *
     * 默认过滤已过期通知；显式传 include_expired=1 时不过滤，便于历史查看。
     *
     * @return array{list:Collection,total:int,page:int,limit:int,unread_count:int}
     */
    public function myInbox(Request $request, int $userId): array
    {
        $query = $this->newQuery()->where('user_id', $userId);

        $this->applyFilters($query, filters: [
            'type'    => $request->get('type', ''),
            'level'   => $request->get('level', ''),
            'is_read' => $request->get('is_read', ''),
        ]);

        $this->applyKeyword(
            $query,
            (string) $request->get('keyword', ''),
            ['title']
        );

        // 默认过滤已过期：等价于 expire_time 为 NULL 或晚于当前
        $includeExpired = (string) $request->get('include_expired', '0') === '1';
        if (!$includeExpired) {
            $now = $this->now();
            $query->where(function ($q) use ($now) {
                $q->whereNull('expire_time')->orWhere('expire_time', '>', $now);
            });
        }

        $result = $this->paginate($query, $request, defaultLimit: 20);

        // 列表项附加 is_expired 派生字段，前端用来展示标签
        $now = $this->now();
        /** @var Collection<int,SysNotice> $list */
        $list = $result['list'];
        $list->each(function (SysNotice $n) use ($now) {
            $n->setAttribute(
                'is_expired',
                $n->expire_time !== null && $n->expire_time <= $now ? 1 : 0
            );
        });

        // 统计未读（全局未过期未读数）
        $unreadCount = $this->newQuery()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->where(function ($q) use ($now) {
                $q->whereNull('expire_time')->orWhere('expire_time', '>', $now);
            })
            ->count();

        $result['unread_count'] = (int) $unreadCount;
        return $result;
    }

    /**
     * 当前用户的未读数量（按级别拆分）。
     *
     * @return array{total:int,by_level:array<string,int>}
     */
    public function myUnreadStats(int $userId): array
    {
        $rows = $this->newQuery()
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->where(function ($q) {
                $q->whereNull('expire_time')->orWhere('expire_time', '>', $this->now());
            })
            ->selectRaw('level, COUNT(*) as cnt')
            ->groupBy('level')
            ->pluck('cnt', 'level')
            ->toArray();

        $total = array_sum($rows);
        return [
            'total'    => (int) $total,
            'by_level' => [
                SysNotice::LEVEL_INFO    => (int) ($rows[SysNotice::LEVEL_INFO]    ?? 0),
                SysNotice::LEVEL_SUCCESS => (int) ($rows[SysNotice::LEVEL_SUCCESS] ?? 0),
                SysNotice::LEVEL_WARNING => (int) ($rows[SysNotice::LEVEL_WARNING] ?? 0),
                SysNotice::LEVEL_DANGER  => (int) ($rows[SysNotice::LEVEL_DANGER]  ?? 0),
            ],
        ];
    }

    /**
     * 通知详情。
     */
    public function detail(int $id): SysNotice
    {
        /** @var SysNotice $notice */
        $notice = $this->findOrFail($id, ['user', 'sender'], '通知不存在');
        return $notice;
    }

    /**
     * 单条发送。
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $operatorId): SysNotice
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $this->assertUserExists($userId);

        return SysNotice::create($this->buildPayload($data, $operatorId, $userId));
    }

    /**
     * 批量发送（多个用户）。
     *
     * 性能：旧实现循环 N 次 SysNotice::create()，N=500 时 500 次单条 INSERT；
     * 改为一次性 SysNotice::insert($rows) 批量写入，量级降 N→1 个 round trip。
     * 缺点：批量 insert 不会触发 Eloquent 事件 / 自动时间戳，所以手工填 created_at。
     *
     * @param array<string,mixed> $data
     * @return int 成功条数
     */
    public function batchCreate(array $data, int $operatorId): int
    {
        $userIds = (array) ($data['user_ids'] ?? []);
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($v) => $v > 0)));

        if ($userIds === []) {
            throw BusinessException::badRequest('请选择至少一个接收用户');
        }
        if (count($userIds) > self::MAX_BATCH_SIZE) {
            throw BusinessException::badRequest('批量发送一次最多 ' . self::MAX_BATCH_SIZE . ' 个用户');
        }

        // 仅保留真实存在的用户
        $existsIds = SysUser::whereIn('id', $userIds)->pluck('id')->all();
        if ($existsIds === []) {
            throw BusinessException::badRequest('所选用户均不存在');
        }

        // 复用 buildPayload 保证字段与 sanitize 行为一致，去掉与"行间不同"无关的 user_id
        $template = $this->buildPayload($data, $operatorId, 0);
        unset($template['user_id']);

        $rows = array_map(
            fn (int $uid) => array_merge($template, ['user_id' => $uid]),
            $existsIds
        );

        return $this->transaction(function () use ($rows): int {
            // SysNotice::insert 直接走 query builder，单条 SQL 批量写入
            SysNotice::insert($rows);
            return count($rows);
        });
    }

    /**
     * 更新通知（仅允许修改内容/级别等元信息）。
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $operatorId): SysNotice
    {
        /** @var SysNotice $notice */
        $notice = $this->findOrFail($id, [], '通知不存在');

        $notice->fill([
            'type'        => (int) ($data['type'] ?? SysNotice::TYPE_SYSTEM),
            'level'       => trim((string) ($data['level'] ?? SysNotice::LEVEL_INFO)),
            'title'       => trim((string) ($data['title'] ?? '')),
            'content'     => HtmlSanitizer::sanitize((string) ($data['content'] ?? '')),
            'biz_type'    => trim((string) ($data['biz_type'] ?? '')) ?: null,
            'biz_id'      => trim((string) ($data['biz_id'] ?? '')) ?: null,
            'link'        => trim((string) ($data['link'] ?? '')) ?: null,
            'expire_time' => $this->normalizeExpireTime($data['expire_time'] ?? null),
            'updated_at'  => $this->now(),
        ])->save();

        return $notice;
    }

    /**
     * 删除通知。
     *
     * 校验：仅接收人 / 发送人 / 超管可删除（防止管理员误删他人通知）。
     */
    public function delete(int $id, int $operatorId): void
    {
        /** @var SysNotice $notice */
        $notice = $this->findOrFail($id, [], '通知不存在');
        if (!$this->canOperate($notice, $operatorId)) {
            throw BusinessException::forbidden('无权删除他人通知');
        }
        $notice->delete();
    }

    /**
     * 批量删除通知。
     *
     * 非超管仅能批量删除自己的（接收 / 发送）通知；超管允许任意。
     *
     * @param int[] $ids
     */
    public function batchDelete(array $ids, int $operatorId): int
    {
        if ($ids === []) {
            throw BusinessException::badRequest('请选择要删除的通知');
        }
        if (PermissionService::getInstance()->isSuperAdmin($operatorId)) {
            return SysNotice::whereIn('id', $ids)->delete();
        }
        return SysNotice::whereIn('id', $ids)
            ->where(function ($q) use ($operatorId) {
                $q->where('user_id', $operatorId)->orWhere('sender_id', $operatorId);
            })
            ->delete();
    }

    /**
     * 标记单条已读。
     *
     * 校验：仅接收人本人（或超管）可标记；其他人调用直接抛 403。
     */
    public function markRead(int $id, int $operatorId): SysNotice
    {
        /** @var SysNotice $notice */
        $notice = $this->findOrFail($id, [], '通知不存在');

        if (!$this->canOperate($notice, $operatorId)) {
            throw BusinessException::forbidden('无权操作他人通知');
        }

        if ((int) $notice->is_read === 0) {
            $notice->is_read   = 1;
            $notice->read_time = $this->now();
            $notice->save();
        }
        return $notice;
    }

    /**
     * 批量标记已读。
     *
     * 校验：未指定 enforceUserId 时（管理员路径）允许标记任意；
     * 指定 enforceUserId 时（用户中心路径）仅标记属于该用户的，其余静默跳过。
     *
     * @param int[] $ids
     */
    public function batchMarkRead(array $ids, int $operatorId, ?int $enforceUserId = null): int
    {
        if ($ids === []) {
            return 0;
        }

        // 用户中心入口：强制只能操作自己的通知
        if ($enforceUserId !== null) {
            return SysNotice::whereIn('id', $ids)
                ->where('user_id', $enforceUserId)
                ->where('is_read', 0)
                ->update([
                    'is_read'   => 1,
                    'read_time' => $this->now(),
                ]);
        }

        // 管理端入口：超管才允许跨用户标记
        if (!PermissionService::getInstance()->isSuperAdmin($operatorId)) {
            return SysNotice::whereIn('id', $ids)
                ->where('user_id', $operatorId)
                ->where('is_read', 0)
                ->update([
                    'is_read'   => 1,
                    'read_time' => $this->now(),
                ]);
        }

        return SysNotice::whereIn('id', $ids)
            ->where('is_read', 0)
            ->update([
                'is_read'   => 1,
                'read_time' => $this->now(),
            ]);
    }

    /**
     * 当前用户全部标记已读。
     */
    public function markAllRead(int $userId): int
    {
        return SysNotice::where('user_id', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read'   => 1,
                'read_time' => $this->now(),
            ]);
    }

    /**
     * 判断操作员是否有权操作（标记已读 / 删除）该通知：
     *  - 超管直接放行；
     *  - 接收人本人允许；
     *  - 发送人允许（自己发的可以撤）。
     */
    private function canOperate(SysNotice $notice, int $operatorId): bool
    {
        if ($operatorId <= 0) {
            return false;
        }
        if ((int) $notice->user_id === $operatorId) {
            return true;
        }
        if ((int) ($notice->sender_id ?? 0) === $operatorId) {
            return true;
        }
        return PermissionService::getInstance()->isSuperAdmin($operatorId);
    }

    /**
     * 清理过期通知。
     */
    public function clearExpired(int $days = 30): int
    {
        $deadline = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return SysNotice::where('created_at', '<', $deadline)->delete();
    }

    // -------------------------------------------------------------------------
    // 私有方法
    // -------------------------------------------------------------------------

    /**
     * 校验用户存在。
     */
    private function assertUserExists(int $userId): void
    {
        if ($userId <= 0) {
            throw BusinessException::badRequest('接收用户不能为空');
        }
        if (!SysUser::where('id', $userId)->exists()) {
            throw BusinessException::notFound('接收用户不存在');
        }
    }

    /**
     * 构造写入字段。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function buildPayload(array $data, int $operatorId, int $userId): array
    {
        return [
            'user_id'     => $userId,
            'type'        => (int) ($data['type'] ?? SysNotice::TYPE_SYSTEM),
            'level'       => trim((string) ($data['level'] ?? SysNotice::LEVEL_INFO)),
            'title'       => trim((string) ($data['title'] ?? '')),
            'content'     => HtmlSanitizer::sanitize((string) ($data['content'] ?? '')),
            'biz_type'    => trim((string) ($data['biz_type'] ?? '')) ?: null,
            'biz_id'      => trim((string) ($data['biz_id'] ?? '')) ?: null,
            'link'        => trim((string) ($data['link'] ?? '')) ?: null,
            'is_read'     => 0,
            'sender_id'   => $operatorId > 0 ? $operatorId : null,
            'expire_time' => $this->normalizeExpireTime($data['expire_time'] ?? null),
            'created_at'  => $this->now(),
        ];
    }

    /**
     * 规范化过期时间。
     *
     * @param mixed $value
     * @return string|null
     */
    private function normalizeExpireTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
