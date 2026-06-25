<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 文件管理模型
 *
 * 表：sys_file
 *
 * 业务约束：
 *  - 物理文件统一存放在 public/uploads/Y/m/d/ 下
 *  - 删除时需先删物理文件再删记录（FileService 已封装）
 *
 * @property string               $name
 * @property string               $original_name
 * @property string               $file_path
 * @property string|null          $file_url
 * @property int                  $file_size
 * @property string|null          $file_ext
 * @property string|null          $file_type
 * @property string               $storage_type
 * @property string|null          $upload_ip
 * @property int|null             $upload_user_id
 * @property int                  $download_count
 * @property int                  $status
 * @property string|null          $remark
 * @property-read string          $full_url
 * @property-read string          $formatted_size
 * @property-read string          $file_icon
 * @property-read \app\model\SysUser|null $user
 * @property int|null             $count        聚合查询别名
 * @property int|null             $total_size   聚合查询别名
 */
class SysFile extends BaseModel
{
    /** 存储类型：本地 */
    public const STORAGE_LOCAL = 'local';
    /** 存储类型：阿里云 OSS */
    public const STORAGE_OSS   = 'oss';
    /** 存储类型：腾讯云 COS */
    public const STORAGE_COS   = 'cos';
    /** 存储类型：AWS S3 */
    public const STORAGE_S3    = 's3';

    /** 状态：禁用 */
    public const STATUS_DISABLED = 0;
    /** 状态：正常 */
    public const STATUS_NORMAL   = 1;

    /** 图片扩展名 */
    private const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];

    /** 文件类型 → 图标映射 */
    private const ICON_MAP = [
        'jpg'  => 'image', 'jpeg' => 'image', 'png'  => 'image',
        'gif'  => 'image', 'bmp'  => 'image', 'webp' => 'image',
        'svg'  => 'image',
        'pdf'  => 'pdf',
        'doc'  => 'word',  'docx' => 'word',
        'xls'  => 'excel', 'xlsx' => 'excel',
        'ppt'  => 'ppt',   'pptx' => 'ppt',
        'txt'  => 'txt',
        'zip'  => 'zip',   'rar'  => 'zip',  '7z'  => 'zip',
        'mp3'  => 'audio', 'wav'  => 'audio',
        'mp4'  => 'video', 'avi'  => 'video','mov' => 'video',
    ];

    protected $table = 'sys_file';

    /**
     * 允许批量赋值的字段
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'original_name',
        'file_path',
        'file_url',
        'file_size',
        'file_ext',
        'file_type',
        'storage_type',
        'upload_ip',
        'upload_user_id',
        'download_count',
        'status',
        'remark',
        'created_at',
    ];

    // -------------------------------------------------------------------------
    // 序列化
    // -------------------------------------------------------------------------

    /**
     * 序列化为前端契约字段（与 FileListItem 对齐）。
     *
     * 列表与详情统一在此映射，避免数据库列名（file_path 等）直接外泄。
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'original_name'  => $this->original_name,
            'path'           => $this->file_path,
            'url'            => $this->full_url,
            'size'           => (int) $this->file_size,
            'mime_type'      => $this->file_type,
            'extension'      => $this->file_ext,
            'storage'        => $this->storage_type,
            'download_count' => (int) $this->download_count,
            'status'         => (int) $this->status,
            'create_by'      => $this->upload_user_id,
            'create_time'    => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    // -------------------------------------------------------------------------
    // 派生属性
    // -------------------------------------------------------------------------

    /**
     * 完整访问 URL（优先使用 file_url，回退到 /uploads/<path>）。
     */
    public function getFullUrlAttribute(): string
    {
        if ($this->file_url) {
            return (string) $this->file_url;
        }
        return '/uploads/' . ltrim((string) $this->file_path, '/');
    }

    /**
     * 格式化文件大小（B / KB / MB / GB）。
     */
    public function getFormattedSizeAttribute(): string
    {
        $size = (int) $this->file_size;
        return match (true) {
            $size < 1024                  => $size . ' B',
            $size < 1024 * 1024           => round($size / 1024, 2) . ' KB',
            $size < 1024 * 1024 * 1024    => round($size / 1024 / 1024, 2) . ' MB',
            default                        => round($size / 1024 / 1024 / 1024, 2) . ' GB',
        };
    }

    /**
     * 文件图标标识（前端按此值显示对应图标）。
     */
    public function getFileIconAttribute(): string
    {
        return self::ICON_MAP[strtolower((string) $this->file_ext)] ?? 'file';
    }

    // -------------------------------------------------------------------------
    // 关联关系
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(SysUser::class, 'upload_user_id');
    }

    // -------------------------------------------------------------------------
    // 业务方法
    // -------------------------------------------------------------------------

    public function isImage(): bool
    {
        return in_array(strtolower((string) $this->file_ext), self::IMAGE_EXTS, true);
    }

    public function incrementDownload(): void
    {
        $this->increment('download_count');
    }
}
