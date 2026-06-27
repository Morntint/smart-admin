<?php

namespace app\admin\service\WeChat;

use app\common\WeChat\WeChatFactory;
use app\model\WeChatMessage;
use app\model\WeChatTemplate;
use support\Log;

/**
 * 微信消息服务
 *
 * 注意：EasyWeChat v6 不再以 `$app->getXxx()` 暴露各业务客户端，
 * 所有微信 OpenAPI 都通过 `$app->getClient()->postJson($path, $payload)` 统一调用。
 * 本类按这套约定改写所有发送/同步逻辑。
 */
class WeChatMessageService
{
    /**
     * 将 PSR-7 响应或字符串响应规约为关联数组
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
     * 判定调用是否成功
     */
    protected static function isOk(?array $result): bool
    {
        return is_array($result) && ((int) ($result['errcode'] ?? 0) === 0);
    }

    /**
     * 发送模板消息
     *
     * - 本地模板表只用于提示性参考，模板未同步时仅记录日志、不阻塞发送；
     * - 无论成功失败都写入一条 wechat_message（status=1 成功 / status=2 失败），
     *   失败原因落入 send_result，便于运营回溯。
     */
    public static function sendTemplateMessage(
        string $openid,
        string $templateId,
        array $data,
        ?string $url = null,
        ?string $appid = null,
        ?string $pagepath = null,
        string $appType = 'official_account'
    ): ?array {
        $template = WeChatTemplate::findByTemplateId($templateId, $appType);
        if (!$template) {
            Log::warning('[WeChat] 模板未同步至本地，仍尝试调用微信发送', [
                'template_id' => $templateId,
                'app_type'    => $appType,
            ]);
        }

        $message = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'data'        => $data,
        ];

        if ($url) {
            $message['url'] = $url;
        }
        if ($appid && $pagepath) {
            $message['miniprogram'] = [
                'appid'    => $appid,
                'pagepath' => $pagepath,
            ];
        }

        $result = WeChatFactory::safeCall(function () use ($message) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/message/template/send', $message);
            return self::asArray($resp);
        }, '发送模板消息');

        $isOk = self::isOk($result);
        try {
            WeChatMessage::create([
                'msg_id'      => is_array($result) ? ($result['msgid'] ?? null) : null,
                'app_type'    => $appType,
                'msg_type'    => 'template',
                'from_user'   => '__system__',
                'to_user'     => $openid,
                'content'     => json_encode($message, JSON_UNESCAPED_UNICODE),
                'send_time'   => date('Y-m-d H:i:s'),
                'send_status' => $isOk ? 1 : 2,
                'send_result' => json_encode($result ?? ['errmsg' => 'sdk no response'], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            Log::error('[WeChat] 模板消息记录写入失败', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * 客服消息 - 文本
     */
    public static function sendTextMessage(string $openid, string $content, string $appType = 'official_account'): ?array
    {
        return self::sendCustomerMessage([
            'touser'  => $openid,
            'msgtype' => 'text',
            'text'    => ['content' => $content],
        ], $appType, ['raw_content' => $content]);
    }

    /**
     * 客服消息 - 图片
     */
    public static function sendImageMessage(string $openid, string $mediaId, string $appType = 'official_account'): ?array
    {
        return self::sendCustomerMessage([
            'touser'  => $openid,
            'msgtype' => 'image',
            'image'   => ['media_id' => $mediaId],
        ], $appType);
    }

    /**
     * 客服消息 - 图文
     */
    public static function sendNewsMessage(string $openid, array $articles, string $appType = 'official_account'): ?array
    {
        return self::sendCustomerMessage([
            'touser'  => $openid,
            'msgtype' => 'news',
            'news'    => ['articles' => $articles],
        ], $appType);
    }

    /**
     * 客服消息 - 图文(永久素材)
     */
    public static function sendMpNewsMessage(string $openid, string $mediaId, string $appType = 'official_account'): ?array
    {
        return self::sendCustomerMessage([
            'touser'  => $openid,
            'msgtype' => 'mpnews',
            'mpnews'  => ['media_id' => $mediaId],
        ], $appType);
    }

    /**
     * 客服消息 - 小程序卡片
     */
    public static function sendMiniProgramPageMessage(
        string $openid,
        string $title,
        string $appid,
        string $pagepath,
        string $thumbMediaId,
        string $appType = 'official_account'
    ): ?array {
        return self::sendCustomerMessage([
            'touser'           => $openid,
            'msgtype'          => 'miniprogrampage',
            'miniprogrampage'  => [
                'title'          => $title,
                'appid'          => $appid,
                'pagepath'       => $pagepath,
                'thumb_media_id' => $thumbMediaId,
            ],
        ], $appType);
    }

    /**
     * 客服输入状态
     */
    public static function setTypingStatus(string $openid, bool $typing = true, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($openid, $typing) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/message/custom/typing', [
                'touser'  => $openid,
                'command' => $typing ? 'Typing' : 'CancelTyping',
            ]);
            return self::asArray($resp);
        }, '设置客服输入状态');
    }

    /**
     * 客服消息统一调用入口
     *
     * @param array<string,mixed> $payload  微信 customer/send 请求体
     * @param array<string,mixed> $logExtra 附加日志字段（仅本地落库使用）
     */
    protected static function sendCustomerMessage(array $payload, string $appType, array $logExtra = []): ?array
    {
        $result = WeChatFactory::safeCall(function () use ($payload) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/message/custom/send', $payload);
            return self::asArray($resp);
        }, '发送客服消息');

        $isOk = self::isOk($result);
        try {
            WeChatMessage::create([
                'app_type'    => $appType,
                'msg_type'    => 'customer_' . ($payload['msgtype'] ?? 'unknown'),
                'from_user'   => '__system__',
                'to_user'     => (string) ($payload['touser'] ?? ''),
                'content'     => $logExtra['raw_content'] ?? json_encode($payload, JSON_UNESCAPED_UNICODE),
                'send_time'   => date('Y-m-d H:i:s'),
                'send_status' => $isOk ? 1 : 2,
                'send_result' => json_encode($result ?? ['errmsg' => 'sdk no response'], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            Log::error('[WeChat] 客服消息记录写入失败', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * 获取模板列表
     */
    public static function getTemplateList(string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->get('cgi-bin/template/get_all_private_template');
            return self::asArray($resp);
        }, '获取模板列表');
    }

    /**
     * 同步模板列表到本地
     *
     * 微信 OpenAPI 返回的 primary_industry / deputy_industry 为对象（{first_class, second_class}）
     * 这里展平为字符串 "一级 / 二级"，原始结构写入 params.industry。
     */
    public static function syncTemplates(string $appType = 'official_account'): int
    {
        $result = self::getTemplateList($appType);
        if (!is_array($result) || empty($result['template_list']) || !is_array($result['template_list'])) {
            return 0;
        }

        $count = (new WeChatTemplate())->getConnection()->transaction(function () use ($result, $appType) {
            $local = 0;
            foreach ($result['template_list'] as $template) {
                $primary = self::flattenIndustry($template['primary_industry'] ?? null);
                $deputy  = self::flattenIndustry($template['deputy_industry']  ?? null);

                WeChatTemplate::updateOrCreate(
                    ['template_id' => $template['template_id'], 'app_type' => $appType],
                    [
                        'title'            => (string) ($template['title'] ?? ''),
                        'primary_industry' => $primary,
                        'deputy_industry'  => $deputy,
                        'content'          => (string) ($template['content'] ?? ''),
                        'example'          => (string) ($template['example'] ?? ''),
                        'params'           => [
                            'primary_industry_raw' => $template['primary_industry'] ?? null,
                            'deputy_industry_raw'  => $template['deputy_industry']  ?? null,
                        ],
                        'status'           => 1,
                    ]
                );
                $local++;
            }
            return $local;
        });

        Log::info('[WeChat] 同步模板完成', ['app_type' => $appType, 'count' => $count]);

        return $count;
    }

    /**
     * 把微信 industry 对象拍平为字符串
     */
    protected static function flattenIndustry(mixed $industry): ?string
    {
        if ($industry === null || $industry === '') {
            return null;
        }
        if (is_string($industry)) {
            return $industry;
        }
        if (is_array($industry)) {
            $first  = $industry['first_class']  ?? '';
            $second = $industry['second_class'] ?? '';
            $joined = trim(implode(' / ', array_filter([$first, $second], fn ($v) => $v !== '' && $v !== null)));
            return $joined !== '' ? $joined : null;
        }
        return null;
    }

    /**
     * 删除模板
     */
    public static function deleteTemplate(string $templateId, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($templateId) {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->postJson('cgi-bin/template/del_private_template', [
                'template_id' => $templateId,
            ]);
            return self::asArray($resp);
        }, '删除模板');

        if (self::isOk($result)) {
            WeChatTemplate::where('template_id', $templateId)
                ->where('app_type', $appType)
                ->delete();
            return true;
        }

        return false;
    }

    /**
     * 批量发送模板消息（顺序调用；如需大批量请改走消息队列）
     */
    public static function batchSendTemplateMessage(
        array $openidList,
        string $templateId,
        array $data,
        ?string $url = null,
        string $appType = 'official_account'
    ): int {
        $successCount = 0;

        foreach ($openidList as $openid) {
            $result = self::sendTemplateMessage($openid, $templateId, $data, $url, null, null, $appType);
            if (self::isOk($result)) {
                $successCount++;
            }
        }

        return $successCount;
    }
}
