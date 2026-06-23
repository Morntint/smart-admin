<?php

namespace app\common\enum;

/**
 * 角色数据权限范围
 */
enum DataScopeEnum: int
{
    case ALL             = 1;
    case DEPT            = 2;
    case DEPT_AND_CHILD  = 3;
    case SELF            = 4;
    case CUSTOM          = 5;

    public function label(): string
    {
        return match ($this) {
            self::ALL            => '全部数据',
            self::DEPT           => '本部门数据',
            self::DEPT_AND_CHILD => '本部门及下级数据',
            self::SELF           => '仅本人数据',
            self::CUSTOM         => '自定义数据',
        };
    }
}
