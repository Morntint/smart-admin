<?php

namespace app\admin\service;

use app\common\exception\BusinessException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use support\Request;
use Throwable;

/**
 * 通用 Service 基类
 *
 * 提供控制器经常复用的查询/分页/写操作封装，子类只需在属性中
 * 指定 `$modelClass` 即可。
 *
 * 设计约定：
 *  - Service 通过 getInstance() 获取单例（webman 无构造注入容器）
 *  - 业务异常一律抛 BusinessException，由全局 Handler 统一处理
 *  - 通用过滤、分页、findOrFail、unique 校验、事务封装在此完成
 *  - 不要在 Service 中直接调用 request() / response()，应通过参数传入
 */
abstract class BaseService
{
    /**
     * 关联模型类（子类必须设置）。
     *
     * @var class-string<Model>
     */
    protected string $modelClass;

    /**
     * 单例缓存（按子类区分）。
     *
     * @var array<class-string,static>
     */
    protected static array $instances = [];

    /**
     * 获取 Service 单例。
     *
     * Webman 的容器不支持构造函数自动类型注入，
     * 与 PermissionService / JwtService 保持一致使用单例工厂方法。
     */
    public static function getInstance(): static
    {
        $cls = static::class;
        return self::$instances[$cls] ??= new $cls();
    }

    /**
     * 通用条件过滤
     *
     * - $filters：等值/IN 过滤，值为 ''、null 时跳过
     * - $like：模糊匹配，过滤 % _ 防注入
     *
     * @param Builder              $query
     * @param array<string,mixed>  $filters
     * @param array<string,string> $like
     */
    protected function applyFilters(Builder $query, array $filters = [], array $like = []): Builder
    {
        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            is_array($value)
                ? $query->whereIn($field, $value)
                : $query->where($field, $value);
        }

        foreach ($like as $field => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $query->where($field, 'like', safe_like_pattern($value));
        }

        return $query;
    }

    /**
     * 通用日期区间过滤（包含起止日期，按整天计算）。
     */
    protected function applyDateRange(Builder $query, string $field, string $startDate, string $endDate): void
    {
        if ($startDate !== '') {
            $query->where($field, '>=', $startDate . ' 00:00:00');
        }
        if ($endDate !== '') {
            $query->where($field, '<=', $endDate . ' 23:59:59');
        }
    }

    /**
     * 多字段关键字搜索（OR 连接）。
     *
     * @param string[] $fields 参与搜索的字段
     */
    protected function applyKeyword(Builder $query, string $keyword, array $fields): Builder
    {
        $keyword = trim($keyword);
        if ($keyword === '' || $fields === []) {
            return $query;
        }
        $pattern = safe_like_pattern($keyword);

        return $query->where(function (Builder $q) use ($fields, $pattern) {
            foreach ($fields as $field) {
                $q->orWhere($field, 'like', $pattern);
            }
        });
    }

    /**
     * 应用数据范围过滤（RBAC 数据权限）。
     *
     * 根据当前用户所有角色归并出的有效数据范围，对查询追加部门/本人条件。
     * 超级管理员跳过过滤。userId 由调用方从 $request->admin_user_id 传入
     * （遵守「不在 Service 内调 request()」约定）。
     *
     * @param Builder $query
     * @param int     $userId  当前登录用户 ID
     * @param array{dept_field?:string,user_field?:string} $options
     *        - dept_field：该表用于「部门」过滤的列（如 'dept_id'）
     *        - user_field：该表用于「本人」过滤的列（sys_user 用 'id'，业务表用 'created_by'）
     */
    protected function applyDataScope(Builder $query, int $userId, array $options = []): Builder
    {
        // 超管跳过（与权限中间件一致）
        if (PermissionService::getInstance()->isSuperAdmin($userId)) {
            return $query;
        }

        $deptField = $options['dept_field'] ?? null;
        $userField = $options['user_field'] ?? null;

        $scope = DataScopeService::getInstance()->getEffectiveScope($userId);

        switch ($scope['scope']) {
            case 'all':
                return $query;

            case 'dept':
                // 无部门字段的表无法按部门过滤，不限制
                if ($deptField === null) {
                    return $query;
                }
                // 可见部门集合为空 → 返回空结果集，避免漏过滤变成看全部
                if ($scope['deptIds'] === []) {
                    return $query->whereRaw('1 = 0');
                }
                return $query->whereIn($deptField, $scope['deptIds']);

            case 'self':
                // 无法定位「本人」列 → 空集，保守防越权
                if ($userField === null) {
                    return $query->whereRaw('1 = 0');
                }
                return $query->where($userField, $userId);
        }

        return $query;
    }

    /**
     * 通用分页方法
     *
     * @param Builder $query
     * @param Request $request
     * @param int     $defaultLimit
     * @param int     $maxLimit
     * @return array{list:Collection,total:int,page:int,limit:int}
     */
    protected function paginate(Builder $query, Request $request, int $defaultLimit = 15, int $maxLimit = 100): array
    {
        $page  = max(1, (int) $request->get('page', 1));
        $limit = min($maxLimit, max(1, (int) $request->get('limit', $defaultLimit)));

        $total = (clone $query)->count();
        $list  = $query->orderBy('id', 'desc')
                       ->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->get();

        return compact('list', 'total', 'page', 'limit');
    }

    /**
     * 根据主键获取单条记录，找不到时抛业务异常。
     *
     * @param array<int|string,mixed>|string $with 预加载关联
     * @throws BusinessException
     */
    protected function findOrFail(int $id, array|string $with = [], string $message = '数据不存在'): Model
    {
        $query = $this->newQuery();
        if ($with !== [] && $with !== '') {
            $query->with($with);
        }
        $model = $query->find($id);
        if (!$model) {
            throw BusinessException::notFound($message);
        }
        return $model;
    }

    /**
     * 判断字段值是否已被占用（用于 unique 检查）。
     *
     * @param int|null $exceptId 排除的 ID（更新场景）
     */
    protected function isValueExists(string $field, mixed $value, ?int $exceptId = null): bool
    {
        $query = $this->newQuery()->where($field, $value);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        return $query->exists();
    }

    /**
     * 唯一性断言：值已被占用时抛业务异常（409）。
     */
    protected function assertUnique(string $field, mixed $value, ?int $exceptId = null, string $message = '该值已存在'): void
    {
        if ($this->isValueExists($field, $value, $exceptId)) {
            throw BusinessException::conflict($message);
        }
    }

    /**
     * 获取新的查询构造器（每次调用都返回新实例，避免污染）。
     */
    protected function newQuery(): Builder
    {
        $modelClass = $this->modelClass;
        /** @var Model $instance */
        $instance = new $modelClass();
        return $instance->newQuery();
    }

    /**
     * 数据库事务封装。
     *
     * @template T
     * @param callable():T $callback
     * @return T
     * @throws Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        return $this->newQuery()->getModel()->getConnection()->transaction($callback);
    }

    /**
     * 获取当前时间字符串（Y-m-d H:i:s）。
     */
    protected function now(): string
    {
        return now_datetime();
    }
}
