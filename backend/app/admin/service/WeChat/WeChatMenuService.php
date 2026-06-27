<?php

namespace app\admin\service\WeChat;

use app\common\WeChat\WeChatFactory;
use app\model\WeChatMenu;
use support\Log;

/**
 * 微信菜单服务
 */
class WeChatMenuService
{
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
     * 获取当前菜单（从微信 API）
     */
    public static function getCurrentMenus(string $appType = 'official_account'): ?array
    {
        return WeChatFactory::safeCall(function () {
            $app = WeChatFactory::officialAccount();
            $resp = $app->getClient()->get('cgi-bin/menu/get');
            return self::asArray($resp);
        }, '获取当前菜单');
    }

    /**
     * 创建菜单
     */
    public static function createMenus(?array $buttons = null, ?array $matchRule = null, string $appType = 'official_account'): ?array
    {
        if ($buttons === null) {
            $buttons = WeChatMenu::toWechatFormat($appType);
        }

        return WeChatFactory::safeCall(function () use ($buttons, $matchRule) {
            $app = WeChatFactory::officialAccount();
            if ($matchRule) {
                $resp = $app->getClient()->postJson('cgi-bin/menu/addconditional', [
                    'button'    => $buttons,
                    'matchrule' => $matchRule,
                ]);
            } else {
                $resp = $app->getClient()->postJson('cgi-bin/menu/create', [
                    'button' => $buttons,
                ]);
            }
            return self::asArray($resp);
        }, '创建菜单');
    }

    /**
     * 删除菜单（远程）
     */
    public static function deleteMenus(?string $menuId = null, string $appType = 'official_account'): bool
    {
        $result = WeChatFactory::safeCall(function () use ($menuId) {
            $app = WeChatFactory::officialAccount();
            if ($menuId) {
                $resp = $app->getClient()->postJson('cgi-bin/menu/delconditional', ['menuid' => $menuId]);
            } else {
                $resp = $app->getClient()->get('cgi-bin/menu/delete');
            }
            return self::asArray($resp);
        }, '删除菜单');

        return is_array($result) && ((int) ($result['errcode'] ?? 0) === 0);
    }

    /**
     * 发布本地菜单到微信
     *
     * 返回 SDK 原始响应；调用方可读取 errcode/errmsg。
     */
    public static function publishMenus(string $appType = 'official_account'): ?array
    {
        return self::createMenus(null, null, $appType);
    }

    /**
     * 用前端提交的按钮结构整体替换本地菜单（不向微信发布）。
     *
     * 入参 `$buttons` 与微信发布格式一致：
     *   [
     *     ['name' => '一级菜单', 'sub_button' => [['type' => 'view', 'name' => '...', 'url' => '...'], ...]],
     *     ['name' => '一级菜单', 'type' => 'click', 'key' => '...'],
     *   ]
     *
     * @return int 写入的菜单条数（含子菜单）
     */
    public static function replaceTree(string $appType, array $buttons): int
    {
        $allowedTypes = [
            'click', 'view', 'miniprogram',
            'scancode_push', 'scancode_waitmsg',
            'pic_sysphoto', 'pic_photo_or_album', 'pic_weixin',
            'location_select', 'media_id', 'view_limited',
            'article_id', 'article_view_limited',
        ];

        return (new WeChatMenu())->getConnection()->transaction(function () use ($appType, $buttons, $allowedTypes) {
            // 清空旧菜单（同 app_type）
            WeChatMenu::where('app_type', $appType)->delete();

            $count = 0;
            foreach (array_values($buttons) as $i => $top) {
                if (!is_array($top) || empty($top['name'])) {
                    continue;
                }

                $hasChildren = !empty($top['sub_button']) && is_array($top['sub_button']);

                $parent = WeChatMenu::create([
                    'app_type'  => $appType,
                    'parent_id' => 0,
                    'name'      => (string) $top['name'],
                    'type'      => $hasChildren
                        ? null
                        : (in_array($top['type'] ?? null, $allowedTypes, true) ? $top['type'] : null),
                    'key'       => $top['key']       ?? null,
                    'url'       => $top['url']       ?? null,
                    'appid'     => $top['appid']     ?? null,
                    'pagepath'  => $top['pagepath']  ?? null,
                    'sort'      => $i,
                    'status'    => 1,
                ]);
                $count++;

                if ($hasChildren) {
                    foreach (array_values($top['sub_button']) as $j => $child) {
                        if (!is_array($child) || empty($child['name'])) {
                            continue;
                        }
                        WeChatMenu::create([
                            'app_type'  => $appType,
                            'parent_id' => $parent->id,
                            'name'      => (string) $child['name'],
                            'type'      => in_array($child['type'] ?? null, $allowedTypes, true)
                                ? $child['type']
                                : null,
                            'key'       => $child['key']      ?? null,
                            'url'       => $child['url']      ?? null,
                            'appid'     => $child['appid']    ?? null,
                            'pagepath'  => $child['pagepath'] ?? null,
                            'sort'      => $j,
                            'status'    => 1,
                        ]);
                        $count++;
                    }
                }
            }

            return $count;
        });
    }

    /**
     * 添加菜单
     */
    public static function addMenu(array $data, string $appType = 'official_account'): WeChatMenu
    {
        return WeChatMenu::create(array_merge($data, ['app_type' => $appType]));
    }

    /**
     * 更新菜单
     */
    public static function updateMenu(int $id, array $data): bool
    {
        $menu = WeChatMenu::find($id);
        if (!$menu) {
            return false;
        }

        return $menu->update($data);
    }

    /**
     * 删除菜单（本地）—— 父子菜单同事务删除，避免出现"父删了子未删"的孤儿数据。
     */
    public static function deleteLocalMenu(int $id): bool
    {
        $menu = WeChatMenu::find($id);
        if (!$menu) {
            return false;
        }

        return (bool) (new WeChatMenu())->getConnection()->transaction(function () use ($menu, $id) {
            WeChatMenu::where('parent_id', $id)->delete();
            return $menu->delete();
        });
    }

    /**
     * 获取菜单树形结构
     */
    public static function getMenuTree(string $appType = 'official_account', bool $onlyActive = true): array
    {
        return WeChatMenu::getTree($appType, $onlyActive)->toArray();
    }
}
