<?php

namespace app\admin\service\WeChat;

use app\common\WeChat\WeChatFactory;
use app\model\WeChatUser;
use support\Log;

/**
 * 小程序服务
 */
class MiniProgramService
{
    /**
     * 登录凭证校验
     */
    public static function code2Session(string $code): ?array
    {
        return WeChatFactory::safeCall(function () use ($code) {
            $app = WeChatFactory::miniProgram();
            return $app->getAuth()->session($code);
        }, '小程序登录');
    }

    /**
     * 小程序登录：换取 openid 与短期 loginTicket。
     *
     * 仅向客户端返回 openid / unionid / loginTicket，session_key 缓存在服务端，
     * 后续解密类接口通过 loginTicket 取回。
     *
     * @return array{openid:string,unionid?:string,loginTicket:string}|array{errcode:int,errmsg:string}
     */
    public static function miniLogin(string $code): array
    {
        $result = self::code2Session($code);

        if (!is_array($result) || empty($result['openid'])) {
            return [
                'errcode' => is_array($result) ? (int) ($result['errcode'] ?? -1) : -1,
                'errmsg'  => is_array($result) ? (string) ($result['errmsg'] ?? '登录失败') : '登录失败：SDK 无响应',
            ];
        }

        $ticket = bin2hex(random_bytes(16));
        \support\Cache::set(self::sessionKeyCacheKey($ticket), [
            'openid'      => $result['openid'],
            'session_key' => $result['session_key'] ?? '',
            'unionid'     => $result['unionid'] ?? null,
        ], 300); // 5 分钟

        $resp = ['openid' => $result['openid'], 'loginTicket' => $ticket];
        if (!empty($result['unionid'])) {
            $resp['unionid'] = $result['unionid'];
        }
        return $resp;
    }

    /**
     * 通过 loginTicket 取回缓存的 session_key（5 分钟内有效）
     */
    public static function getSessionByTicket(string $ticket): ?array
    {
        if ($ticket === '') {
            return null;
        }
        $cached = \support\Cache::get(self::sessionKeyCacheKey($ticket));
        return is_array($cached) ? $cached : null;
    }

    protected static function sessionKeyCacheKey(string $ticket): string
    {
        return 'wx:mini:sk:' . $ticket;
    }

    /**
     * 通过 code 换取手机号（推荐使用「button.open-type=getPhoneNumber」获得的 code）
     */
    public static function getPhoneNumber(string $code): ?array
    {
        return WeChatFactory::safeCall(function () use ($code) {
            $app = WeChatFactory::miniProgram();
            $resp = $app->getClient()->postJson('wxa/business/getuserphonenumber', ['code' => $code]);
            return self::psr7Json($resp);
        }, '获取用户手机号');
    }

    /**
     * 解密用户敏感数据
     *
     * 推荐使用 loginTicket（参见 miniLogin）代替直传 sessionKey；
     * 旧接口签名保持兼容。
     */
    public static function decryptUserInfo(string $sessionKey, string $encryptedData, string $iv): ?array
    {
        try {
            $app = WeChatFactory::miniProgram();
            $decrypted = $app->getUtils()->decryptSession($sessionKey, $iv, $encryptedData);

            // 保存或更新用户信息
            if (isset($decrypted['openId'])) {
                WeChatUser::createOrUpdateFromWechat([
                    'openid'   => $decrypted['openId'],
                    'unionid'  => $decrypted['unionId']  ?? null,
                    'nickname' => $decrypted['nickName'] ?? null,
                    'gender'   => $decrypted['gender']   ?? 0,
                    'avatar'   => $decrypted['avatarUrl']?? null,
                    'city'     => $decrypted['city']     ?? null,
                    'province' => $decrypted['province'] ?? null,
                    'country'  => $decrypted['country']  ?? null,
                    'language' => $decrypted['language'] ?? null,
                ], 'mini_program');
            }

            return $decrypted;
        } catch (\Throwable $e) {
            Log::error('[WeChat] 解密用户信息失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 通过 loginTicket 解密用户信息（不需要客户端持有 session_key）
     */
    public static function decryptUserInfoByTicket(string $ticket, string $encryptedData, string $iv): ?array
    {
        $session = self::getSessionByTicket($ticket);
        if (!$session || empty($session['session_key'])) {
            return null;
        }
        return self::decryptUserInfo($session['session_key'], $encryptedData, $iv);
    }

    /**
     * 获取小程序码（PSR-7 二进制响应）
     *
     * 不走 safeCall 包装：safeCall 把响应限定为数组，二进制流场景需自行处理。
     */
    public static function getQRCode(string $path, int $width = 430, array $options = []): ?string
    {
        try {
            $app = WeChatFactory::miniProgram();
            $resp = $app->getClient()->postJson('cgi-bin/wxaapp/createwxaqrcode', array_merge([
                'path'  => $path,
                'width' => $width,
            ], $options));

            return self::readBinaryBody($resp);
        } catch (\Throwable $e) {
            Log::error('[WeChat] 获取小程序码失败', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 获取不限制的小程序码（PSR-7 二进制响应）
     */
    public static function getUnlimitedQRCode(string $scene, array $options = []): ?string
    {
        try {
            $app = WeChatFactory::miniProgram();
            $resp = $app->getClient()->postJson('wxa/getwxacodeunlimit', array_merge([
                'scene' => $scene,
            ], $options));

            return self::readBinaryBody($resp);
        } catch (\Throwable $e) {
            Log::error('[WeChat] 获取不限制小程序码失败', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 安全读取 PSR-7 响应的二进制 body。
     *
     * - 微信端在错误时会返回 JSON（如 {"errcode":40029,...}），此时返回 null；
     * - 成功时返回 PNG 二进制流。
     */
    protected static function readBinaryBody(mixed $resp): ?string
    {
        if (!is_object($resp) || !method_exists($resp, 'getBody')) {
            return null;
        }
        $body = (string) $resp->getBody();
        if ($body === '') {
            return null;
        }
        // 错误响应是 JSON
        if ($body[0] === '{' && str_contains($body, 'errcode')) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && (int) ($decoded['errcode'] ?? 0) !== 0) {
                Log::warning('[WeChat] 小程序码接口返回错误', $decoded);
                return null;
            }
        }
        return $body;
    }

    /**
     * 把 PSR-7 响应解析为关联数组。
     *
     * EasyWeChat v6 的 Client 默认以 response_type='array' 返回 array，
     * 但底层 Symfony HttpClient 在某些异常路径下仍可能交还 ResponseInterface。
     * 这里兼容两种情况。
     */
    protected static function psr7Json(mixed $resp): ?array
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
     * 发送订阅消息
     */
    public static function sendSubscribeMessage(
        string $openid,
        string $templateId,
        array $data,
        ?string $page = null,
        ?string $state = null
    ): ?array {
        return WeChatFactory::safeCall(function () use ($openid, $templateId, $data, $page, $state) {
            $app = WeChatFactory::miniProgram();
            $message = [
                'touser'      => $openid,
                'template_id' => $templateId,
                'data'        => $data,
            ];

            if ($page) {
                $message['page'] = $page;
            }
            if ($state) {
                $message['miniprogram_state'] = $state;
            }

            $resp = $app->getClient()->postJson('cgi-bin/message/subscribe/send', $message);
            return self::psr7Json($resp);
        }, '发送订阅消息');
    }

    /**
     * 检测文本内容是否安全（msg_sec_check v2）
     */
    public static function checkContentSecurity(string $content, ?string $openid = null, int $scene = 1): ?array
    {
        return WeChatFactory::safeCall(function () use ($content, $openid, $scene) {
            $app = WeChatFactory::miniProgram();
            $params = ['content' => $content];
            if ($openid !== null && $openid !== '') {
                $params['version'] = 2;
                $params['openid']  = $openid;
                $params['scene']   = $scene;
            }
            $resp = $app->getClient()->postJson('wxa/msg_sec_check', $params);
            return self::psr7Json($resp);
        }, '内容安全检测');
    }

    /**
     * 检测图片是否安全（img_sec_check，参数为二进制流路径）
     */
    public static function checkImageSecurity(string $mediaPath): ?array
    {
        return WeChatFactory::safeCall(function () use ($mediaPath) {
            $app = WeChatFactory::miniProgram();
            $resp = $app->getClient()->request('POST', 'wxa/img_sec_check', [
                'body' => fopen($mediaPath, 'r'),
            ]);
            return self::psr7Json($resp);
        }, '图片安全检测');
    }
}
