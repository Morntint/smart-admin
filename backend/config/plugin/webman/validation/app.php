<?php

return [
    // 验证失败时返回的 JSON 响应格式
    'response' => [
        'code' => 422,
        'msg' => '验证失败',
    ],
    
    // 自定义异常处理类（可选）
    'exception' => null,
];
