<?php

namespace app\common\enum;

/**
 * 通用状态枚举（启用/禁用）
 */
enum StatusEnum: int
{
    case DISABLED = 0;
    case NORMAL  = 1;

    public function label(): string
    {
        return match ($this) {
            self::DISABLED => '禁用',
            self::NORMAL   => '正常',
        };
    }
}
