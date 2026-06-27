<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统通知模型
 *
 * 表：sys_notice
 *
 * 业务约束：
 *  - 通知为「用户维度」的站内信，每条记录归属于具体 user_id
 *  - 支持按业务类型(biz_type) + 业务ID(biz_id) 关联外部业务
 *  - 软删除：用户主动删除/批量清理走 deleted_at
 *
 * @property int                  $id
 * @property int                  $user_id
 * @property int                  $type
 * @property string               $level
 * @property string               $title
 * @property string|null          $content
 * @property string|null          $biz_type
 * @property string|null          $biz_id
 * @property string|null          $link
 * @property int                  $is_read
 * @property string|null          $read_time
 * @property int|null             $sender_id
 * @property string|null          $expire_time
 * @property-read \app\model\SysUser|null $user
 * @property-read \app\model\SysUser|null $sender
 */
class SysNotice extends BaseModel
{
    use SoftDeletes;

    protected $table = 'sys_notice';

    /** 通知类型 */
    public const TYPE_SYSTEM  = 1; // 系统通知
    public const TYPE_TODO    = 2; // 待办
    public const TYPE_WARNING = 3; // 预警
    public const TYPE_PERSONAL = 4; // 个人消息

    /** 级别 */
    public const LEVEL_INFO    = 'info';
    public const LEVEL_SUCCESS = 'success';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_DANGER  = 'danger';

    /** 通知类型映射 */
    public static array $typeMap = [
        self::TYPE_SYSTEM  => '系统通知',
        self::TYPE_TODO    => '待办',
        self::TYPE_WARNING => '预警',
        self::TYPE_PERSONAL => '个人消息',
    ];

    /** 级别映射 */
    public static array $levelMap = [
        self::LEVEL_INFO    => '普通',
        self::LEVEL_SUCCESS => '成功',
        self::LEVEL_WARNING => '警告',
        self::LEVEL_DANGER  => '严重',
    ];

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'level',
        'title',
        'content',
        'biz_type',
        'biz_id',
        'link',
        'is_read',
        'read_time',
        'sender_id',
        'expire_time',
    ];

    /**
     * 类型派生属性。
     */
    public function getTypeTextAttribute(): string
    {
        return self::$typeMap[$this->type] ?? '未知';
    }

    /**
     * 级别派生属性。
     */
    public function getLevelTextAttribute(): string
    {
        return self::$levelMap[$this->level] ?? '未知';
    }

    /**
     * 接收人。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(SysUser::class, 'user_id');
    }

    /**
     * 发送人（系统通知为 NULL）。
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(SysUser::class, 'sender_id');
    }
}
