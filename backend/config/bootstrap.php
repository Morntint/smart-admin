<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    support\bootstrap\Session::class,
    // 慢查询监控：每个 Worker 启动时注册 DB::listen，超阈值 SQL 写日志（SLOW_QUERY_MS）
    app\bootstrap\SlowQueryLogger::class,
    // AI 工具治理：每个 Worker 启动时把内置工具注册到 ToolRegistry，
    // 杜绝 H-2 中"DB handler 字符串直接驱动反射任意类"的攻击面。
    app\bootstrap\AiToolBootstrap::class,
];
