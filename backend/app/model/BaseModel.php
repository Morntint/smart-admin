<?php

namespace app\model;

use app\common\exception\ResourceNotFoundException;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use support\Model;

/**
 * 基础模型类
 *
 * 统一各业务 Model 的公共行为：
 *  - 时间戳字段名（created_at / updated_at）
 *  - 提供常用的静态查询/写入封装，子类可直接复用
 *
 * 设计约定：
 *  - 业务规则不应直接写在 Model 里（避免 ActiveRecord 反模式），优先放 Service
 *  - Model 中只放纯字段访问、范围查询、关联关系、简单的派生属性
 */
abstract class BaseModel extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * 通用分页查询。
     *
     * @param array<string,mixed>           $where 精确条件（值为 ''/null 时跳过）
     * @param array{0:string,1:string}      $order 排序规则 [field, direction]
     * @return array{list:Collection,total:int,page:int,limit:int,total_pages:int}
     */
    public static function getPageList(
        array $where = [],
        int $page = 1,
        int $limit = 15,
        array $order = ['id', 'desc']
    ): array {
        $query = static::query();

        foreach ($where as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            is_array($value)
                ? $query->whereIn($field, $value)
                : $query->where($field, $value);
        }

        $query->orderBy($order[0], $order[1]);
        $total      = (clone $query)->count();
        $totalPages = max(1, (int) ceil($total / max(1, $limit)));
        $page       = max(1, min($page, $totalPages));

        $list = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return compact('total', 'list', 'page', 'limit', 'total_pages');
    }

    /**
     * 根据主键获取单条记录。
     */
    public static function findById(int $id): ?static
    {
        /** @var ?static $model */
        $model = static::find($id);
        return $model;
    }

    /**
     * 根据主键获取单条记录，不存在时抛出业务异常（404）。
     *
     * @throws ResourceNotFoundException
     */
    public static function findOrFailById(int $id, string $message = '数据不存在'): static
    {
        $model = static::findById($id);
        if (!$model) {
            throw new ResourceNotFoundException($message);
        }
        return $model;
    }

    /**
     * 创建数据（统一封装，便于以后注入审计字段）。
     *
     * @param array<string,mixed> $data
     */
    public static function createData(array $data): static
    {
        /** @var static $model */
        $model = static::create($data);
        return $model;
    }

    /**
     * 更新数据（实例方法）。
     *
     * @param array<string,mixed> $data
     */
    public function updateData(array $data): bool
    {
        return (bool) $this->update($data);
    }

    /**
     * 统一日期序列化格式，避免默认 ISO 8601（带 T 和 Z）。
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 范围查询：仅启用状态记录（status = 1）。
     *
     * 子类需有 status 字段才有意义。
     */
    public function scopeOnlyEnabled(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
