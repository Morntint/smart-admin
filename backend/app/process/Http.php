<?php

namespace app\process;

use Webman\App;

/**
 * HTTP 主进程
 *
 * 继承 webman 默认 App 即可启用全部 HTTP 行为；
 * 如需在请求生命周期前后做全局钩子，可在此重写 onMessage。
 */
class Http extends App
{
}
