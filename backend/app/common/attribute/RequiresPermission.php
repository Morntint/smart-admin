<?php

namespace app\common\attribute;

use Attribute;

/**
 * 接口权限注解
 *
 * 标注在控制器方法上，声明访问该接口所需的权限标识（permission）。
 * 由 AuthMiddleware 通过反射读取并校验：
 *  - 未标注本注解的方法默认放行（读接口/下拉等无需鉴权）
 *  - 标注后，超级管理员跳过校验，普通用户需满足其中「任意一个」权限
 *
 * 用法：
 *   #[RequiresPermission('system:user:add')]
 *   #[RequiresPermission('system:user:add', 'system:user:edit')] // 满足其一即可
 *
 * 权限标识需与 sys_menu.permission 完全一致。
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequiresPermission
{
    /** @var string[] 所需权限标识（满足其一即放行） */
    public readonly array $permissions;

    public function __construct(string ...$permissions)
    {
        $this->permissions = $permissions;
    }
}
