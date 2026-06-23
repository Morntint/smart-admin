<?php

namespace app\common\enum;

/**
 * 登录/操作结果状态
 */
enum ResultStatusEnum: int
{
    case FAIL    = 0;
    case SUCCESS = 1;

    public function label(): string
    {
        return match ($this) {
            self::FAIL    => '失败',
            self::SUCCESS => '成功',
        };
    }
}
