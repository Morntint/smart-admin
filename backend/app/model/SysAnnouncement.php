<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统公告模型
 *
 * 表：sys_announcement
 *
 * 业务约束：
 *  - 公告面向全员(登录用户)可见，由后台管理员维护
 *  - 状态流转：草稿(0) → 已发布(1) → 已下线(2)
 *  - 草稿状态下不进入前台公告列表
 *  - effective_at / expire_at 控制有效期；前台仅在有效期内展示
 *
 * @property int                  $id
 * @property string               $title
 * @property string               $content
 * @property string               $category
 * @property string               $level
 * @property int                  $is_top
 * @property int                  $is_popup
 * @property int                  $status
 * @property int|null             $publisher_id
 * @property string|null          $published_at
 * @property string|null          $effective_at
 * @property string|null          $expire_at
 * @property int                  $view_count
 * @property int                  $sort
 * @property string|null          $remark
 * @property-read \app\model\SysUser|null $publisher
 */
class SysAnnouncement extends BaseModel
{
    use SoftDeletes;

    protected $table = 'sys_announcement';

    /** 状态：草稿 */
    public const STATUS_DRAFT    = 0;
    /** 状态：已发布 */
    public const STATUS_PUBLISHED = 1;
    /** 状态：已下线 */
    public const STATUS_OFFLINE  = 2;

    /** 分类 */
    public const CATEGORY_NOTICE     = 'notice';     // 通知
    public const CATEGORY_ANNOUNCEMENT = 'announcement'; // 公告
    public const CATEGORY_ACTIVITY   = 'activity';   // 活动
    public const CATEGORY_MAINTENANCE = 'maintenance'; // 维护

    /** 级别 */
    public const LEVEL_INFO     = 'info';     // 普通
    public const LEVEL_IMPORTANT = 'important'; // 重要
    public const LEVEL_URGENT   = 'urgent';   // 紧急

    /** 状态映射 */
    public static array $statusMap = [
        self::STATUS_DRAFT     => '草稿',
        self::STATUS_PUBLISHED => '已发布',
        self::STATUS_OFFLINE   => '已下线',
    ];

    /** 分类映射 */
    public static array $categoryMap = [
        self::CATEGORY_NOTICE      => '通知',
        self::CATEGORY_ANNOUNCEMENT => '公告',
        self::CATEGORY_ACTIVITY    => '活动',
        self::CATEGORY_MAINTENANCE => '维护',
    ];

    /** 级别映射 */
    public static array $levelMap = [
        self::LEVEL_INFO     => '普通',
        self::LEVEL_IMPORTANT => '重要',
        self::LEVEL_URGENT   => '紧急',
    ];

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'title',
        'content',
        'category',
        'level',
        'is_top',
        'is_popup',
        'status',
        'publisher_id',
        'published_at',
        'effective_at',
        'expire_at',
        'view_count',
        'sort',
        'remark',
        'created_by',
        'updated_by',
    ];

    public function getStatusTextAttribute(): string
    {
        return self::$statusMap[$this->status] ?? '未知';
    }

    public function getCategoryTextAttribute(): string
    {
        return self::$categoryMap[$this->category] ?? '未知';
    }

    public function getLevelTextAttribute(): string
    {
        return self::$levelMap[$this->level] ?? '未知';
    }

    /**
     * 发布人。
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(SysUser::class, 'publisher_id');
    }
}
