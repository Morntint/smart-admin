<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 系统操作日志模型
 *
 * 表：sys_operation_log
 *
 * 由 OperationLog 中间件自动写入，记录所有 POST/PUT/PATCH/DELETE 写操作。
 */
class SysOperationLog extends BaseModel
{
    // ------- 请求方法 -------
    public const METHOD_GET    = 'GET';
    public const METHOD_POST   = 'POST';
    public const METHOD_PUT    = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PATCH  = 'PATCH';

    // ------- 状态 -------
    public const STATUS_ABNORMAL = 0;
    public const STATUS_NORMAL   = 1;

    /** 请求方法 → 前端 Tag 颜色映射 */
    private const METHOD_COLOR_MAP = [
        'GET'    => 'success',
        'POST'   => 'primary',
        'PUT'    => 'warning',
        'DELETE' => 'danger',
        'PATCH'  => 'info',
    ];

    protected $table = 'sys_operation_log';

    /** 关闭 Eloquent 自动维护 updated_at；操作日志只插入、不更新 */
    public $timestamps = false;

    /**
     * 允许批量赋值的字段。
     *
     * @var string[]
     */
    protected $fillable = [
        'module',
        'action',
        'method',
        'url',
        'ip',
        'user_agent',
        'user_id',
        'username',
        'param',
        'result',
        'status',
        'error_msg',
        'duration',
        'created_at',
    ];

    // -------------------------------------------------------------------------
    // 派生属性
    // -------------------------------------------------------------------------

    public function getStatusTextAttribute(): string
    {
        return (int) $this->status === self::STATUS_NORMAL ? '正常' : '异常';
    }

    public function getMethodColorAttribute(): string
    {
        return self::METHOD_COLOR_MAP[$this->method] ?? 'default';
    }

    // -------------------------------------------------------------------------
    // 关联关系
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(SysUser::class, 'user_id');
    }

    // -------------------------------------------------------------------------
    // 业务方法
    // -------------------------------------------------------------------------

    /**
     * 解码请求参数为数组（前端展示详情时使用）。
     *
     * @return array<string,mixed>
     */
    public function getFormattedParam(): array
    {
        if (empty($this->param)) {
            return [];
        }
        $param = json_decode((string) $this->param, true);
        return is_array($param) ? $param : [];
    }

    /**
     * 组装一行操作日志数据（在请求上下文内调用）。
     *
     * 用于入队：把依赖 request() 的字段（ip / user_agent / created_at）在此处取好，
     * 消费进程脱离请求上下文也能直接批量写库。
     *
     * @param array<string,mixed>|null $param  请求参数
     * @param array<string,mixed>|null $result 响应内容
     * @return array<string,mixed>
     */
    public static function buildRow(
        string  $method,
        string  $url,
        ?int    $userId   = null,
        ?string $username = null,
        ?string $module   = null,
        ?array  $param    = null,
        ?array  $result   = null,
        bool    $status   = true,
        ?string $errorMsg = null,
        ?int    $duration = null
    ): array {
        return [
            'method'     => $method,
            'url'        => $url,
            'module'     => $module,
            'user_id'    => $userId,
            'username'   => $username,
            'param'      => $param  ? json_encode($param,  JSON_UNESCAPED_UNICODE) : null,
            'result'     => $result ? json_encode($result, JSON_UNESCAPED_UNICODE) : null,
            'ip'         => request()?->getRealIp(),
            'user_agent' => request()?->header('user-agent'),
            'status'     => $status ? self::STATUS_NORMAL : self::STATUS_ABNORMAL,
            'error_msg'  => $errorMsg,
            'duration'   => $duration,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 记录操作日志（统一入口，同步写库）。
     *
     * @param array<string,mixed>|null $param  请求参数
     * @param array<string,mixed>|null $result 响应内容
     */
    public static function record(
        string  $method,
        string  $url,
        ?int    $userId   = null,
        ?string $username = null,
        ?string $module   = null,
        ?array  $param    = null,
        ?array  $result   = null,
        bool    $status   = true,
        ?string $errorMsg = null,
        ?int    $duration = null
    ): self {
        return self::create(self::buildRow(
            $method, $url, $userId, $username, $module,
            $param, $result, $status, $errorMsg, $duration
        ));
    }
}
