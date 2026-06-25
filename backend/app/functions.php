<?php

use Illuminate\Database\Eloquent\Collection;
use app\admin\service\PermissionService;

/**
 * 全局函数定义
 *
 * 这些函数在整个应用中都可用，集中放置便于维护与复用。
 * 任何业务相关的 helper 建议优先放在 service 层（业务规则）或
 * app/helper.php（与缓存、系统配置相关的工具函数）。
 */

// -----------------------------------------------------------------------------
// Collection 宏
// -----------------------------------------------------------------------------

/**
 * 将扁平列表转为嵌套树形结构（要求模型有 id、parent_id 字段）。
 *
 * 用法：Department::query()->get()->toTree();
 */
Collection::macro('toTree', function () {
    $items = $this->keyBy('id')->toArray();
    $tree = [];

    foreach ($items as $id => &$item) {
        $parentId = (int) ($item['parent_id'] ?? 0);
        if ($parentId > 0 && isset($items[$parentId])) {
            $items[$parentId]['children'][] = &$item;
        } else {
            $tree[] = &$item;
        }
    }

    return $tree;
});

// 同时注册到 Eloquent Collection，让 PHPStan 在分析 Eloquent 查询结果时也能识别
\Illuminate\Database\Eloquent\Collection::macro('toTree', function () {
    $items = $this->keyBy('id')->toArray();
    $tree = [];

    foreach ($items as $id => &$item) {
        $parentId = (int) ($item['parent_id'] ?? 0);
        if ($parentId > 0 && isset($items[$parentId])) {
            $items[$parentId]['children'][] = &$item;
        } else {
            $tree[] = &$item;
        }
    }

    return $tree;
});

// -----------------------------------------------------------------------------
// 安全 / 通用工具
// -----------------------------------------------------------------------------

if (!function_exists('safe_like')) {
    /**
     * 过滤 LIKE 查询中的通配符（%, _），避免恶意模糊匹配。
     */
    function safe_like(string $value): string
    {
        return preg_replace('/[%_]/', '', $value) ?? '';
    }
}

if (!function_exists('safe_like_pattern')) {
    /**
     * 生成带 % 通配符的 LIKE 模式。
     *
     * @param string $value 原值
     * @param string $mode  both|left|right
     */
    function safe_like_pattern(string $value, string $mode = 'both'): string
    {
        $value = safe_like($value);
        return match ($mode) {
            'left'  => "%{$value}",
            'right' => "{$value}%",
            default => "%{$value}%",
        };
    }
}

if (!function_exists('now_datetime')) {
    /**
     * 获取当前时间字符串（统一格式：Y-m-d H:i:s）。
     *
     * 业务层所有需要写入 created_at/updated_at 的地方都应使用本函数，
     * 便于后续统一时区或切换为 Carbon。
     */
    function now_datetime(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('clear_permission_cache')) {
    /**
     * 静默清除用户权限缓存（用于用户/角色/菜单变更后的联动清理）。
     *
     * 包装 try/catch，防止缓存层异常影响主业务链路。
     */
    function clear_permission_cache(int $userId): void
    {
        try {
            PermissionService::getInstance()->clearCache($userId);
        } catch (\Throwable) {
            // 静默忽略：缓存清理失败不应阻断业务
        }
    }
}

if (!function_exists('csrf_token')) {
    /**
     * 生成/获取当前会话的 CSRF Token。
     */
    function csrf_token(): string
    {
        $token = request()->session()->get('_csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            request()->session()->put('_csrf_token', $token);
        }
        return $token;
    }
}

if (!function_exists('make_password')) {
    /**
     * 生成密码哈希（统一使用 bcrypt，cost = 10）。
     */
    function make_password(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}
