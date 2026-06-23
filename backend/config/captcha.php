<?php

/**
 * 验证码配置
 *
 * - enabled：是否在登录时启用验证码（建议生产环境开启）
 * - 其余视觉参数与 webman/captcha 保持一致；
 *   实际生成由 LoginService::captcha() 完成
 */
return [
    'enabled'    => (bool) env('CAPTCHA_ENABLED', false),

    'characters' => '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSUVWXY',
    'length'     => 4,
    'width'      => 120,
    'height'     => 40,
    'font_file'  => null,
    'font_size'  => 22,

    'colors' => [
        'background' => [255, 255, 255],
        'text'       => [0, 0, 0],
        'lines'      => [200, 200, 200],
    ],

    'draw_lines' => true,
    'line_count' => 3,
    'draw_dots'  => true,
    'dot_count'  => 50,
];
