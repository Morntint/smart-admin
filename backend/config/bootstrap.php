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
];
