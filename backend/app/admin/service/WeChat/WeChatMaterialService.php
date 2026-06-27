<?php

namespace app\admin\service\WeChat;

use app\common\WeChat\WeChatFactory;
use app\model\WeChatMaterial;
use support\Log;

/**
 * 微信素材服务
 *
 * EasyWeChat v6 不再提供 `$app->getMaterial()` / `$app->getMedia()` helper，
 * 所有 OpenAPI 通过 `$app->getClient()->postJson|request|get` 调用。
 *
 * 所有「调微信 API」方法都按 $appType 路由到对应 EasyWeChat 应用——
 * 历史代码硬编码了 officialAccount，导致 mini_program / open_platform 素材永远不可达。
 */
class WeChatMaterialService
{
    /**
     * 根据 appType 取对应的 EasyWeChat 应用实例
     */
    protected static function app(string $appType): mixed
    {
        return match ($appType) {
            'mini_program'  => WeChatFactory::miniProgram(),
            'open_platform' => WeChatFactory::openPlatform(),
            default          => WeChatFactory::officialAccount(),
        };
    }

    /**
     * 把 PSR-7 响应规约为关联数组
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
     * 上传临时素材
     */
    public static function uploadTemp(string $path, string $type = 'image', string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($path, $type, $appType) {
            $client = self::app($appType)->getClient();
            $resp = $client->request('POST', 'cgi-bin/media/upload', [
                'query' => ['type' => $type],
                'body'  => ['media' => fopen($path, 'r')],
            ]);
            return self::asArray($resp);
        }, '上传临时素材');
    }

    /**
     * 上传永久素材
     */
    public static function upload(
        string $path,
        string $type = 'image',
        ?array $description = null,
        string $appType = 'official_account'
    ): ?array {
        $result = WeChatFactory::safeCall(function () use ($path, $type, $description, $appType) {
            $client = self::app($appType)->getClient();
            $body = ['media' => fopen($path, 'r')];
            if ($type === 'video' && $description) {
                $body['description'] = json_encode($description, JSON_UNESCAPED_UNICODE);
            }
            $resp = $client->request('POST', 'cgi-bin/material/add_material', [
                'query' => ['type' => $type],
                'body'  => $body,
            ]);
            return self::asArray($resp);
        }, '上传永久素材');

        if (is_array($result) && isset($result['media_id'])) {
            WeChatMaterial::create([
                'app_type'   => $appType,
                'media_id'   => $result['media_id'],
                'type'       => $type,
                'title'      => $description['title']        ?? '',
                'description'=> $description['introduction'] ?? '',
                'url'        => $result['url'] ?? '',
                'local_path' => $path,
                'sync_time'  => date('Y-m-d H:i:s'),
            ]);
        }

        return $result;
    }

    /**
     * 上传图片（仅返回 URL，供图文消息正文使用）
     */
    public static function uploadImage(string $path, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($path, $appType) {
            $client = self::app($appType)->getClient();
            $resp = $client->request('POST', 'cgi-bin/media/uploadimg', [
                'body' => ['media' => fopen($path, 'r')],
            ]);
            return self::asArray($resp);
        }, '上传图片');
    }

    /**
     * 获取永久素材
     */
    public static function getMaterial(string $mediaId, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($mediaId, $appType) {
            $resp = self::app($appType)->getClient()->postJson('cgi-bin/material/get_material', [
                'media_id' => $mediaId,
            ]);
            return self::asArray($resp);
        }, '获取永久素材');
    }

    /**
     * 删除永久素材
     */
    public static function deleteMaterial(string $mediaId, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($mediaId, $appType) {
            $resp = self::app($appType)->getClient()->postJson('cgi-bin/material/del_material', [
                'media_id' => $mediaId,
            ]);
            return self::asArray($resp);
        }, '删除永久素材');

        $isOk = is_array($result) && ((int) ($result['errcode'] ?? 0) === 0);
        if ($isOk) {
            WeChatMaterial::where('media_id', $mediaId)
                ->where('app_type', $appType)
                ->delete();
            return true;
        }
        return false;
    }

    /**
     * 获取素材列表
     */
    public static function getMaterialList(
        string $type = 'image',
        int $offset = 0,
        int $count = 20,
        string $appType = 'official_account'
    ): ?array {
        return WeChatFactory::safeCall(function () use ($type, $offset, $count, $appType) {
            $resp = self::app($appType)->getClient()->postJson('cgi-bin/material/batchget_material', [
                'type'   => $type,
                'offset' => $offset,
                'count'  => $count,
            ]);
            return self::asArray($resp);
        }, '获取素材列表');
    }

    /**
     * 获取素材统计
     */
    public static function getMaterialStats(string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($appType) {
            $resp = self::app($appType)->getClient()->get('cgi-bin/material/get_materialcount');
            return self::asArray($resp);
        }, '获取素材统计');
    }

    /**
     * 同步素材列表到本地
     *
     * @param string      $appType 应用类型
     * @param string|null $only    仅同步指定类型；为 null 时同步全部
     */
    public static function syncMaterials(string $appType = 'official_account', ?string $only = null): int
    {
        $types = $only !== null ? [$only] : ['image', 'voice', 'video', 'news'];
        $total = 0;

        foreach ($types as $type) {
            $count = (new WeChatMaterial())->getConnection()->transaction(function () use ($type, $appType) {
                $local = 0;
                $offset = 0;
                $batch = 20;

                do {
                    $result = self::getMaterialList($type, $offset, $batch, $appType);
                    if (!is_array($result) || empty($result['item']) || !is_array($result['item'])) {
                        break;
                    }

                    foreach ($result['item'] as $item) {
                        WeChatMaterial::updateOrCreate(
                            ['media_id' => $item['media_id'], 'app_type' => $appType],
                            [
                                'type'      => $type,
                                'title'     => $item['name'] ?? ($item['title'] ?? ''),
                                'url'       => $item['url'] ?? '',
                                'extra'     => $item,
                                'sync_time' => date('Y-m-d H:i:s'),
                            ]
                        );
                        $local++;
                    }

                    $offset += $batch;
                } while (count($result['item']) > 0);

                return $local;
            });

            $total += $count;
        }

        Log::info('[WeChat] 同步素材完成', ['app_type' => $appType, 'count' => $total]);

        return $total;
    }

    /**
     * 新增图文消息
     */
    public static function uploadNews(array $articles, string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () use ($articles, $appType) {
            $resp = self::app($appType)->getClient()->postJson('cgi-bin/material/add_news', [
                'articles' => $articles,
            ]);
            return self::asArray($resp);
        }, '上传图文消息');
    }

    /**
     * 更新图文消息
     */
    public static function updateNews(
        string $mediaId,
        array $article,
        int $index = 0,
        string $appType = 'official_account'
    ): ?array {
        return WeChatFactory::safeCall(function () use ($mediaId, $article, $index, $appType) {
            $resp = self::app($appType)->getClient()->postJson('cgi-bin/material/update_news', [
                'media_id' => $mediaId,
                'index'    => $index,
                'articles' => $article,
            ]);
            return self::asArray($resp);
        }, '更新图文消息');
    }
}
