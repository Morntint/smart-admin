<?php

namespace app\common\enum;

/**
 * 菜单类型
 */
enum MenuTypeEnum: int
{
    case DIR    = 1;
    case MENU   = 2;
    case BUTTON = 3;

    public function label(): string
    {
        return match ($this) {
            self::DIR    => '目录',
            self::MENU   => '菜单',
            self::BUTTON => '按钮',
        };
    }

    public function isContainer(): bool
    {
        return $this === self::DIR || $this === self::MENU;
    }
}
