<?php

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 微信用户模型
 */
class WeChatUser extends BaseModel
{
    use SoftDeletes;

    protected $table = 'wechat_user';

    protected $fillable = [
        'user_id',
        'openid',
        'unionid',
        'app_type',
        'nickname',
        'gender',
        'avatar',
        'city',
        'province',
        'country',
        'language',
        'subscribe',
        'subscribe_time',
        'subscribe_scene',
        'qr_scene',
        'qr_scan_time',
        'latitude',
        'longitude',
        'location_precision',
        'location_time',
        'remark',
        'group_id',
        'tagid_list',
        'extra',
    ];

    protected $casts = [
        'tagid_list' => 'json',
        'extra' => 'json',
        'subscribe_time' => 'datetime',
        'qr_scan_time' => 'datetime',
        'location_time' => 'datetime',
    ];

    /**
     * 关联系统用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(SysUser::class, 'user_id');
    }

    /**
     * 根据 OpenID 获取用户
     */
    public static function findByOpenid(string $openid, string $appType = 'official_account'): ?self
    {
        return self::where('openid', $openid)
            ->where('app_type', $appType)
            ->first();
    }

    /**
     * 根据 UnionID 获取用户
     */
    public static function findByUnionid(string $unionid): ?self
    {
        return self::where('unionid', $unionid)->first();
    }

    /**
     * 更新用户关注状态（必须指定 app_type，避免同 openid 跨应用串数据）
     */
    public static function updateSubscribeStatus(string $openid, int $subscribe, string $appType = 'official_account'): void
    {
        self::where('app_type', $appType)
            ->where('openid', $openid)
            ->update([
                'subscribe' => $subscribe,
                'subscribe_time' => $subscribe ? now() : null,
            ]);
    }

    /**
     * 从微信用户信息创建或更新记录
     *
     * 注意：subscribe_time 仅在微信明确返回时才更新，避免每次 oauth 刷新都改写真实关注时间。
     */
    public static function createOrUpdateFromWechat(array $userInfo, string $appType = 'official_account'): self
    {
        $openid = $userInfo['openid'] ?? $userInfo['openId'] ?? '';

        $data = [
            'openid' => $openid,
            'unionid' => $userInfo['unionid'] ?? $userInfo['unionId'] ?? null,
            'app_type' => $appType,
            'nickname' => $userInfo['nickname'] ?? $userInfo['nickName'] ?? null,
            'gender' => $userInfo['sex'] ?? $userInfo['gender'] ?? 0,
            'avatar' => $userInfo['headimgurl'] ?? $userInfo['avatarUrl'] ?? null,
            'city' => $userInfo['city'] ?? null,
            'province' => $userInfo['province'] ?? null,
            'country' => $userInfo['country'] ?? null,
            'language' => $userInfo['language'] ?? null,
            'subscribe' => $userInfo['subscribe'] ?? 1,
            'subscribe_scene' => $userInfo['subscribe_scene'] ?? null,
            'remark' => $userInfo['remark'] ?? null,
            'group_id' => $userInfo['groupid'] ?? $userInfo['groupId'] ?? null,
            'tagid_list' => $userInfo['tagid_list'] ?? null,
        ];

        // 仅当微信明确提供 subscribe_time 时才写入，避免覆盖已有真实关注时间
        if (isset($userInfo['subscribe_time']) && $userInfo['subscribe_time']) {
            $data['subscribe_time'] = date('Y-m-d H:i:s', (int) $userInfo['subscribe_time']);
        }

        // 过滤空值
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        return self::updateOrCreate(
            ['openid' => $openid, 'app_type' => $appType],
            $data
        );
    }

    /**
     * 绑定系统用户
     */
    public function bindUser(int $userId): bool
    {
        return $this->update(['user_id' => $userId]);
    }

    /**
     * 解绑系统用户
     */
    public function unbindUser(): bool
    {
        return $this->update(['user_id' => null]);
    }
}
