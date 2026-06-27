<?php

namespace app\common\WeChat;

use EasyWeChat\Kernel\Message;
use EasyWeChat\MiniApp\Application as MiniApp;
use EasyWeChat\OfficialAccount\Application as OfficialAccount;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ResponseInterface;
use support\Log;
use app\model\WeChatMessage;
use app\model\WeChatUser;

/**
 * 微信事件处理器
 *
 * 处理公众号 / 小程序的事件与消息推送。
 *
 * EasyWeChat v6 的 Server 把回调消息以 `Message` 对象交给中间件，
 * 通过属性访问字段（`$message->MsgType` / `$message->Event` / `$message->FromUserName`）。
 *
 * Server 的中间件链：
 *   $server->addEventListener($eventName, $callable)   只匹配特定事件
 *   $server->addMessageListener($msgType, $callable)   只匹配特定消息类型
 *   $server->with($middleware)                          通用中间件
 *
 * 处理器返回值：null/string 都会被 Server 包装成对应回复 XML；
 * 也可以返回完整的 ResponseInterface 进行细粒度控制。
 */
class WeChatEventHandler
{
    /**
     * 公众号回调入口
     */
    public static function handleOfficialServer(OfficialAccount $app): \Symfony\Component\HttpFoundation\Response
    {
        $server = $app->getServer();

        // 通用中间件：记录入站消息（幂等）+ 全局兜底
        $server->with(function (Message $message, \Closure $next) {
            self::logIncoming($message, 'official_account');
            try {
                return $next($message);
            } catch (\Throwable $e) {
                Log::error('[WeChat] 公众号消息处理失败', [
                    'message' => $e->getMessage(),
                    'msg_id'  => $message->MsgId ?? null,
                ]);
                return new Psr7Response(200, [], 'success');
            }
        });

        // 各类事件
        $server->addEventListener('subscribe', fn (Message $message, \Closure $next) =>
            self::onSubscribe($message, $next));
        $server->addEventListener('unsubscribe', fn (Message $message, \Closure $next) =>
            self::onUnsubscribe($message, $next));
        $server->addEventListener('SCAN', fn (Message $message, \Closure $next) =>
            self::onScan($message, $next));
        $server->addEventListener('LOCATION', fn (Message $message, \Closure $next) =>
            self::onLocation($message, $next));
        $server->addEventListener('CLICK', fn (Message $message, \Closure $next) =>
            self::onMenuClick($message, $next));
        $server->addEventListener('VIEW', fn (Message $message, \Closure $next) =>
            self::onMenuView($message, $next));
        $server->addEventListener('TEMPLATESENDJOBFINISH', fn (Message $message, \Closure $next) =>
            self::onTemplateSendFinish($message, $next));

        // 普通消息
        $server->addMessageListener('text', fn (Message $message, \Closure $next) =>
            self::onTextMessage($message, $next));

        return self::adaptResponse($server->serve());
    }

    /**
     * 小程序回调入口
     *
     * 小程序的事件主要为客服消息、订阅消息回执等，结构与公众号一致。
     */
    public static function handleMiniServer(MiniApp $app): \Symfony\Component\HttpFoundation\Response
    {
        $server = $app->getServer();

        $server->with(function (Message $message, \Closure $next) {
            self::logIncoming($message, 'mini_program');
            try {
                return $next($message);
            } catch (\Throwable $e) {
                Log::error('[WeChat] 小程序消息处理失败', [
                    'message' => $e->getMessage(),
                    'msg_id'  => $message->MsgId ?? null,
                ]);
                return new Psr7Response(200, [], 'success');
            }
        });

        return self::adaptResponse($server->serve());
    }

    /**
     * 旧入口的向后兼容别名，等同于 handleOfficialServer。
     */
    public static function handleOfficialAccount(OfficialAccount $app): \Symfony\Component\HttpFoundation\Response
    {
        return self::handleOfficialServer($app);
    }

    /**
     * 把 PSR-7 响应转换为 Symfony 响应（webman 直接返回的格式）。
     */
    protected static function adaptResponse(ResponseInterface $response): \Symfony\Component\HttpFoundation\Response
    {
        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        return new \Symfony\Component\HttpFoundation\Response($body, $status, $headers);
    }

    /**
     * 记录入站消息（按 app_type + msg_id 幂等，event 类消息没有 msg_id 时仅写日志）
     */
    protected static function logIncoming(Message $message, string $appType): void
    {
        $msgId = $message->MsgId ?? null;

        try {
            if ($msgId !== null) {
                WeChatMessage::firstOrCreate(
                    ['app_type' => $appType, 'msg_id' => (string) $msgId],
                    [
                        'msg_type'     => (string) ($message->MsgType ?? 'unknown'),
                        'event'        => $message->Event ?? null,
                        'from_user'    => (string) ($message->FromUserName ?? ''),
                        'to_user'      => (string) ($message->ToUserName ?? ''),
                        'content'      => json_encode($message->toArray(), JSON_UNESCAPED_UNICODE),
                        'receive_time' => date('Y-m-d H:i:s', (int) ($message->CreateTime ?? time())),
                    ]
                );
            } else {
                // 事件类消息无 MsgId，按 from+event+createTime 去重
                WeChatMessage::create([
                    'app_type'     => $appType,
                    'msg_type'     => (string) ($message->MsgType ?? 'event'),
                    'event'        => $message->Event ?? null,
                    'from_user'    => (string) ($message->FromUserName ?? ''),
                    'to_user'      => (string) ($message->ToUserName ?? ''),
                    'content'      => json_encode($message->toArray(), JSON_UNESCAPED_UNICODE),
                    'receive_time' => date('Y-m-d H:i:s', (int) ($message->CreateTime ?? time())),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[WeChat] 记录消息失败', ['error' => $e->getMessage()]);
        }
    }

    /* -------------------- 事件处理 -------------------- */

    protected static function onSubscribe(Message $message, \Closure $next): mixed
    {
        $openId = (string) ($message->FromUserName ?? '');
        $eventKey = (string) ($message->EventKey ?? '');
        $appType = 'official_account';

        Log::info('[WeChat] 用户关注', ['openid' => $openId, 'event_key' => $eventKey]);

        WeChatUser::where('app_type', $appType)
            ->where('openid', $openId)
            ->update([
                'subscribe'      => 1,
                'subscribe_time' => date('Y-m-d H:i:s'),
            ]);

        // 扫码关注
        if (str_starts_with($eventKey, 'qrscene_')) {
            WeChatUser::where('app_type', $appType)
                ->where('openid', $openId)
                ->update([
                    'subscribe_scene' => substr($eventKey, 8),
                    'qr_scan_time'    => date('Y-m-d H:i:s'),
                ]);
        }

        return $next($message);
    }

    protected static function onUnsubscribe(Message $message, \Closure $next): mixed
    {
        $openId = (string) ($message->FromUserName ?? '');

        Log::info('[WeChat] 用户取消关注', ['openid' => $openId]);

        WeChatUser::where('app_type', 'official_account')
            ->where('openid', $openId)
            ->update([
                'subscribe'      => 0,
                'subscribe_time' => null,
            ]);

        return $next($message);
    }

    protected static function onScan(Message $message, \Closure $next): mixed
    {
        Log::info('[WeChat] 已关注用户扫码', [
            'openid'    => $message->FromUserName ?? '',
            'event_key' => $message->EventKey ?? '',
        ]);

        return $next($message);
    }

    protected static function onLocation(Message $message, \Closure $next): mixed
    {
        $openId = (string) ($message->FromUserName ?? '');

        WeChatUser::where('app_type', 'official_account')
            ->where('openid', $openId)
            ->update([
                'latitude'           => $message->Latitude ?? null,
                'longitude'          => $message->Longitude ?? null,
                'location_precision' => $message->Precision ?? null,
                'location_time'      => date('Y-m-d H:i:s'),
            ]);

        return $next($message);
    }

    protected static function onMenuClick(Message $message, \Closure $next): mixed
    {
        Log::info('[WeChat] 菜单点击', [
            'openid'    => $message->FromUserName ?? '',
            'event_key' => $message->EventKey ?? '',
        ]);

        return $next($message);
    }

    protected static function onMenuView(Message $message, \Closure $next): mixed
    {
        Log::info('[WeChat] 菜单跳转', [
            'openid' => $message->FromUserName ?? '',
            'url'    => $message->EventKey ?? '',
        ]);

        return $next($message);
    }

    /**
     * 模板消息发送结果回执：更新对应 send_status。
     * 注意 WeChat 回传字段为 MsgID（大写 ID），与入站消息的 MsgId 不同。
     */
    protected static function onTemplateSendFinish(Message $message, \Closure $next): mixed
    {
        $msgId = $message->MsgID ?? null;
        $status = (string) ($message->Status ?? '');

        if ($msgId !== null) {
            WeChatMessage::where('app_type', 'official_account')
                ->where('msg_id', (string) $msgId)
                ->update([
                    'send_status' => $status === 'success' ? 1 : 2,
                    'send_time'   => date('Y-m-d H:i:s'),
                    'send_result' => $status,
                ]);
        }

        return $next($message);
    }

    /* -------------------- 消息处理 -------------------- */

    /**
     * 文本消息示例处理：包含关键词「客服」时回复一段文本。
     */
    protected static function onTextMessage(Message $message, \Closure $next): mixed
    {
        $content = (string) ($message->Content ?? '');

        if (mb_strpos($content, '客服') !== false) {
            // 返回字符串，Server 会自动包装成 text 回复 XML
            return '您好！请问有什么可以帮您？';
        }

        return $next($message);
    }
}
