<?php

namespace app\admin\service\WeChat;

use app\common\WeChat\WeChatFactory;
use app\model\WeChatUser;
use support\Log;

/**
 * 微信用户服务
 *
 * EasyWeChat v6 不再暴露 $app->getUser() 等业务客户端，
 * 所有 OpenAPI 通过 $app->getClient()->postJson / ->get 调用。
 */
class WeChatUserService
{
    /**
     * 将响应规约为关联数组
     */
    protected static function asArray(mixed $resp): ?array
    {
        if (is_array($resp)) {
            return $resp;
        }
        if (is_object($resp) && method_exists($resp, 'getBody')) {
            $body = (string) $resp->getBody();
            if ($body === '') {
                return null;
            }
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * 微信 OpenAPI 是否真正成功（errcode 缺失或为 0）
     */
    protected static function isOk(?array $result): bool
    {
        return is_array($result) && ((int) ($result['errcode'] ?? 0) === 0);
    }

    /**
     * 获取用户信息（单个）
     */
    public static function getUserInfo(string $openid, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($openid) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->get('cgi-bin/user/info', ['query' => [
                'openid' => $openid,
                'lang'   => 'zh_CN',
            ]]);
            return self::asArray($resp);
        }, '获取用户信息');
    }

    /**
     * 批量获取用户基本信息（推荐用于大量同步，每次最多 100 个 openid）
     *
     * @param string[] $openidList
     */
    public static function batchGetUserInfo(array $openidList, string $appType = 'official_account'): ?array
    {
        if ($openidList === []) {
            return ['user_info_list' => []];
        }

        return WeChatFactory::safeCall(function () use ($openidList) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/user/info/batchget', [
                'user_list' => array_map(fn ($openid) => [
                    'openid' => $openid,
                    'lang'   => 'zh_CN',
                ], $openidList),
            ]);
            return self::asArray($resp);
        }, '批量获取用户信息');
    }

    /**
     * 同步单个用户到本地数据库
     */
    public static function syncUser(string $openid, string $appType = 'official_account'): ?WeChatUser
    {
        $userInfo = self::getUserInfo($openid, $appType);
        if (!is_array($userInfo) || empty($userInfo['openid'])) {
            return null;
        }
        return WeChatUser::createOrUpdateFromWechat($userInfo, $appType);
    }

    /**
     * 批量同步用户信息：优先使用 batchget（一次最多 100），降低 API 配额消耗。
     *
     * @param string[] $openidList
     */
    public static function batchSyncUsers(array $openidList, string $appType = 'official_account'): int
    {
        if ($openidList === []) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($openidList, 100) as $chunk) {
            $result = self::batchGetUserInfo($chunk, $appType);
            if (!is_array($result) || empty($result['user_info_list'])) {
                continue;
            }
            (new WeChatUser())->getConnection()->transaction(function () use ($result, $appType, &$count) {
                foreach ($result['user_info_list'] as $userInfo) {
                    if (empty($userInfo['openid'])) {
                        continue;
                    }
                    WeChatUser::createOrUpdateFromWechat($userInfo, $appType);
                    $count++;
                }
            });
        }
        return $count;
    }

    /**
     * 获取关注者 openid 列表
     */
    public static function getUserList(?string $nextOpenid = null, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($nextOpenid) {
            $app = WeChatFactory::officialAccount();
            $query = [];
            if ($nextOpenid) {
                $query['next_openid'] = $nextOpenid;
            }
            $resp = $app->getClient()->get('cgi-bin/user/get', ['query' => $query]);
            return self::asArray($resp);
        }, '获取关注者列表');
    }

    /**
     * 全量同步用户列表
     *
     * 推动条件用 `next_openid` 而非 `count`：count 在某些极端情况下可能与 next_openid
     * 不一致；以分页指针作为唯一判断标准更安全。
     */
    public static function syncAllUsers(string $appType = 'official_account'): int
    {
        $total = 0;
        $nextOpenid = null;

        do {
            $result = self::getUserList($nextOpenid, $appType);
            if (!is_array($result)) {
                break;
            }

            $openidList = $result['data']['openid'] ?? [];
            if (!empty($openidList) && is_array($openidList)) {
                $total += self::batchSyncUsers($openidList, $appType);
            }

            $nextOpenid = $result['next_openid'] ?? null;
        } while (!empty($nextOpenid));

        Log::info('[WeChat] 全量同步用户完成', ['app_type' => $appType, 'count' => $total]);

        return $total;
    }

    /**
     * 更新用户备注（仅在微信端调用成功时同步到本地）
     */
    public static function updateRemark(string $openid, string $remark, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($openid, $remark) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/user/info/updateremark', [
                'openid' => $openid,
                'remark' => $remark,
            ]);
            return self::asArray($resp);
        }, '更新用户备注');

        if (self::isOk($result)) {
            WeChatUser::where('app_type', $appType)
                ->where('openid', $openid)
                ->update(['remark' => $remark]);
            return true;
        }

        return false;
    }

    /**
     * 获取用户标签列表
     */
    public static function getTags(string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->get('cgi-bin/tags/get');
            return self::asArray($resp);
        }, '获取用户标签列表');
    }

    /**
     * 为用户打标签
     */
    public static function tagUser(array $openidList, int $tagId, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($openidList, $tagId) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/tags/members/batchtagging', [
                'openid_list' => $openidList,
                'tagid'       => $tagId,
            ]);
            return self::asArray($resp);
        }, '为用户打标签');

        return self::isOk($result);
    }

    /**
     * 为用户取消标签
     */
    public static function untagUser(array $openidList, int $tagId, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($openidList, $tagId) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/tags/members/batchuntagging', [
                'openid_list' => $openidList,
                'tagid'       => $tagId,
            ]);
            return self::asArray($resp);
        }, '为用户取消标签');

        return self::isOk($result);
    }

    /**
     * 获取用户身上的标签
     */
    public static function getUserTags(string $openid, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($openid) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/tags/getidlist', [
                'openid' => $openid,
            ]);
            return self::asArray($resp);
        }, '获取用户标签');
    }

    /**
     * 获取黑名单
     */
    public static function getBlacklist(?string $beginOpenid = null, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($beginOpenid) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/tags/members/getblacklist', [
                'begin_openid' => $beginOpenid ?? '',
            ]);
            return self::asArray($resp);
        }, '获取黑名单');
    }

    /**
     * 拉黑用户
     */
    public static function blockUsers(array $openidList, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($openidList) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/tags/members/batchblacklist', [
                'openid_list' => $openidList,
            ]);
            return self::asArray($resp);
        }, '拉黑用户');

        return self::isOk($result);
    }

    /**
     * 取消拉黑
     */
    public static function unblockUsers(array $openidList, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($openidList) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/tags/members/batchunblacklist', [
                'openid_list' => $openidList,
            ]);
            return self::asArray($resp);
        }, '取消拉黑用户');

        return self::isOk($result);
    }

    /**
     * 绑定系统用户
     */
    public static function bindSystemUser(string $openid, int $userId, string $appType = 'official_account'): bool
    {
        $wechatUser = WeChatUser::findByOpenid($openid, $appType);
        if (!$wechatUser) {
            return false;
        }
        return $wechatUser->bindUser($userId);
    }

    /**
     * 解绑系统用户
     */
    public static function unbindSystemUser(string $openid, string $appType = 'official_account'): bool
    {
        $wechatUser = WeChatUser::findByOpenid($openid, $appType);
        if (!$wechatUser) {
            return false;
        }
        return $wechatUser->unbindUser();
    }
}
