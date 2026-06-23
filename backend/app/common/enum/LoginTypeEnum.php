<?php

namespace app\common\enum;

/**
 * 登录/登出类型
 */
enum LoginTypeEnum: int
{
    case LOGIN  = 1;
    case LOGOUT = 2;

    public function label(): string
    {
        return match ($this) {
            self::LOGIN  => '登录',
            self::LOGOUT => '登出',
        };
    }
}
