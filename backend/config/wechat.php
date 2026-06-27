<?php
/**
 * 微信多应用配置文件
 * 支持：公众号、小程序、开放平台、企业微信、微信支付等
 */
return [
    /*
    |--------------------------------------------------------------------------
    | 默认应用
    |--------------------------------------------------------------------------
    */
    'default' => 'official_account',

    /*
    |--------------------------------------------------------------------------
    | 公众号配置
    |--------------------------------------------------------------------------
    */
    'official_account' => [
        'app_id' => env('WECHAT_OFFICIAL_APPID', ''),
        'secret' => env('WECHAT_OFFICIAL_SECRET', ''),
        'token' => env('WECHAT_OFFICIAL_TOKEN', ''),
        'aes_key' => env('WECHAT_OFFICIAL_AES_KEY', ''),

        // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
        'response_type' => 'array',

        // 接口请求相关配置，超时时间等，具体可用参数请参考：
        // https://github.com/symfony/http-client/blob/5.2/HttpClient.php#L317
        'http' => [
            'timeout' => 5.0,
            'retry' => [
                'retries' => 3,
                'delay' => 500,
            ],
        ],

        // OAuth 授权配置
        // 注意：oauth.callback 必须对应到 controller 中实际实现的路由；
        // 当前后端未提供专门的 oauth callback，移除此处可避免误用。
        // 'oauth' => [
        //     'scopes' => ['snsapi_userinfo'],
        //     'callback' => '/wechat/oauth/callback',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 小程序配置
    |--------------------------------------------------------------------------
    */
    'mini_program' => [
        'app_id' => env('WECHAT_MINI_APPID', ''),
        'secret' => env('WECHAT_MINI_SECRET', ''),
        'token' => env('WECHAT_MINI_TOKEN', ''),
        'aes_key' => env('WECHAT_MINI_AES_KEY', ''),

        'response_type' => 'array',

        'http' => [
            'timeout' => 5.0,
            'retry' => [
                'retries' => 3,
                'delay' => 500,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 开放平台配置
    |--------------------------------------------------------------------------
    */
    'open_platform' => [
        'app_id' => env('WECHAT_OPEN_APPID', ''),
        'secret' => env('WECHAT_OPEN_SECRET', ''),
        'token' => env('WECHAT_OPEN_TOKEN', ''),
        'aes_key' => env('WECHAT_OPEN_AES_KEY', ''),

        'response_type' => 'array',

        'http' => [
            'timeout' => 5.0,
            'retry' => [
                'retries' => 3,
                'delay' => 500,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 企业微信配置
    |--------------------------------------------------------------------------
    */
    'work' => [
        'corp_id' => env('WECHAT_WORK_CORP_ID', ''),
        'agent_id' => env('WECHAT_WORK_AGENT_ID', ''),
        'secret' => env('WECHAT_WORK_SECRET', ''),
        'token' => env('WECHAT_WORK_TOKEN', ''),
        'aes_key' => env('WECHAT_WORK_AES_KEY', ''),

        'response_type' => 'array',

        'http' => [
            'timeout' => 5.0,
            'retry' => [
                'retries' => 3,
                'delay' => 500,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 微信支付配置（EasyWeChat v6 / API v3）
    |--------------------------------------------------------------------------
    | v3 必要字段：
    |   mch_id          商户号
    |   secret_key      APIv2 key（少量旧接口仍需要，可留空）
    |   v3_secret_key   APIv3 key
    |   private_key     商户私钥（建议是文件路径，也可直接是 PEM 字符串）
    |   certificate     商户证书（建议是文件路径，也可直接是 PEM 字符串）
    |   platform_certs  平台证书（可省略，SDK 会自动下载）
    */
    'pay' => [
        'app_id'         => env('WECHAT_PAY_APPID', ''),
        'mch_id'         => env('WECHAT_PAY_MCH_ID', ''),
        'secret_key'     => env('WECHAT_PAY_SECRET_KEY', ''),
        'v3_secret_key'  => env('WECHAT_PAY_V3_KEY', ''),
        'private_key'    => env('WECHAT_PAY_PRIVATE_KEY', ''),
        'certificate'    => env('WECHAT_PAY_CERTIFICATE', ''),
        'notify_url'     => env('WECHAT_PAY_NOTIFY_URL', ''),

        'response_type' => 'array',

        'http' => [
            'timeout' => 5.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 缓存配置
    |--------------------------------------------------------------------------
    | 使用 webman/redis 作为缓存驱动
    */
    'cache' => [
        'namespace' => 'wechat',
        'default_ttl' => 7200,
    ],
];
