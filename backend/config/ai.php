<?php

/**
 * AI 相关运行时配置
 */

return [
    /**
     * 模型价格表（美元 / 1K tokens）。
     * 未列出的模型 cost 记为 0，并在 worker 内每 5 分钟最多打一次 warning。
     * 新模型上线时改本表，不需要改代码。
     */
    'model_prices' => [
        'gpt-4o'             => ['prompt' => 0.005,   'completion' => 0.015],
        'gpt-4o-mini'        => ['prompt' => 0.00015, 'completion' => 0.0006],
        'gpt-3.5-turbo'      => ['prompt' => 0.0005,  'completion' => 0.0015],
        'deepseek-chat'      => ['prompt' => 0.00014, 'completion' => 0.00028],
        'deepseek-reasoner'  => ['prompt' => 0.00055, 'completion' => 0.00219],
        'qwen-max'           => ['prompt' => 0.00286, 'completion' => 0.00857],
        'glm-4'              => ['prompt' => 0.0143,  'completion' => 0.0143],
        'moonshot-v1-8k'     => ['prompt' => 0.00171, 'completion' => 0.00171],
    ],

    /**
     * AI NL2SQL（query_database 工具）白名单与限制
     *
     * 设计原则：
     *  - 表名、字段名、操作符、聚合函数全部 in_array 校验；任何透传到 SQL 的字符串都拒绝
     *  - 字段强类型：日期/字符串/数字按本表声明强转
     *  - LLM 仅能看到本表声明的字段（敏感字段如 sys_user.password 不出现 → 天然不可查）
     *  - max_limit 是硬上限，强制 LIMIT 让任何"扫全表"的意图都被截断
     *
     * 扩展新表：在 tables 下新增一项，列出可查字段、类型、描述与默认排序即可，无需写 PHP。
     */
    'nl2sql' => [
        'tables' => [
            'sys_operation_log' => [
                'description' => '系统操作日志（用户在后台的接口调用记录）',
                'fields' => [
                    'id'         => ['type' => 'int',      'description' => '日志ID'],
                    'module'     => ['type' => 'string',   'description' => '所属模块'],
                    'action'     => ['type' => 'string',   'description' => '操作类型'],
                    'method'     => ['type' => 'string',   'description' => 'HTTP 方法', 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']],
                    'url'        => ['type' => 'string',   'description' => '请求 URL'],
                    'ip'         => ['type' => 'string',   'description' => '请求 IP'],
                    'user_id'    => ['type' => 'int',      'description' => '操作用户 ID'],
                    'username'   => ['type' => 'string',   'description' => '操作用户名'],
                    'status'     => ['type' => 'int',      'description' => '状态：0=异常 1=正常'],
                    'duration'   => ['type' => 'int',      'description' => '执行耗时（ms）'],
                    'created_at' => ['type' => 'datetime', 'description' => '操作时间'],
                ],
                'order_by_default' => ['field' => 'id', 'direction' => 'desc'],
            ],

            'sys_login_log' => [
                'description' => '系统登录/登出日志',
                'fields' => [
                    'id'          => ['type' => 'int',      'description' => '日志ID'],
                    'user_id'     => ['type' => 'int',      'description' => '用户 ID'],
                    'username'    => ['type' => 'string',   'description' => '用户名'],
                    'login_type'  => ['type' => 'int',      'description' => '类型：1=登录 2=登出'],
                    'ip'          => ['type' => 'string',   'description' => '登录 IP'],
                    'ip_location' => ['type' => 'string',   'description' => 'IP 归属地'],
                    'status'      => ['type' => 'int',      'description' => '状态：0=失败 1=成功'],
                    'msg'         => ['type' => 'string',   'description' => '提示消息'],
                    'created_at'  => ['type' => 'datetime', 'description' => '登录时间'],
                ],
                'order_by_default' => ['field' => 'id', 'direction' => 'desc'],
            ],

            'sys_user' => [
                'description' => '系统用户（不含密码等敏感字段）',
                'fields' => [
                    'id'          => ['type' => 'int',      'description' => '用户ID'],
                    'username'    => ['type' => 'string',   'description' => '账号'],
                    'nickname'    => ['type' => 'string',   'description' => '昵称'],
                    'email'       => ['type' => 'string',   'description' => '邮箱'],
                    'mobile'      => ['type' => 'string',   'description' => '手机号'],
                    'sex'         => ['type' => 'int',      'description' => '性别：0=未知 1=男 2=女'],
                    'status'      => ['type' => 'int',      'description' => '状态：0=禁用 1=正常'],
                    'dept_id'     => ['type' => 'int',      'description' => '部门 ID'],
                    'login_ip'    => ['type' => 'string',   'description' => '最后登录 IP'],
                    'login_time'  => ['type' => 'datetime', 'description' => '最后登录时间'],
                    'login_count' => ['type' => 'int',      'description' => '登录次数'],
                    'created_at'  => ['type' => 'datetime', 'description' => '创建时间'],
                ],
                'order_by_default' => ['field' => 'id', 'direction' => 'desc'],
            ],

            'ai_usage_record' => [
                'description' => 'AI 调用用量与费用记录',
                'fields' => [
                    'id'                => ['type' => 'int',      'description' => '记录ID'],
                    'user_id'           => ['type' => 'int',      'description' => '用户 ID'],
                    'agent_id'          => ['type' => 'int',      'description' => 'Agent ID'],
                    'model_name'        => ['type' => 'string',   'description' => '模型名称'],
                    'prompt_tokens'     => ['type' => 'int',      'description' => '输入 Token 数'],
                    'completion_tokens' => ['type' => 'int',      'description' => '输出 Token 数'],
                    'total_tokens'      => ['type' => 'int',      'description' => '合计 Token 数'],
                    'cost'              => ['type' => 'number',   'description' => '费用（美元）'],
                    'endpoint'          => ['type' => 'string',   'description' => '调用接口（chat/embedding 等）'],
                    'duration'          => ['type' => 'int',      'description' => '耗时（ms）'],
                    'status'            => ['type' => 'int',      'description' => '状态：0=失败 1=成功'],
                    'created_at'        => ['type' => 'datetime', 'description' => '创建时间'],
                ],
                'order_by_default' => ['field' => 'id', 'direction' => 'desc'],
            ],
        ],

        /**
         * 允许的过滤操作符。
         * like 自动转 LIKE %v% （单字符通配符），不接受用户传入 % 自己拼。
         */
        'operators' => ['=', '!=', '>', '>=', '<', '<=', 'like', 'in', 'between', 'is_null', 'is_not_null'],

        /**
         * 允许的聚合函数（仅作用于 int/number 字段，count 例外）。
         */
        'aggregations' => ['count', 'sum', 'avg', 'max', 'min'],

        'limits' => [
            'max_limit'       => 200, // 单次查询行数硬上限
            'default_limit'   => 50,
            'max_filters'     => 10,  // 单次查询 filter 条数上限
            'max_fields'      => 20,  // 单次查询返回字段数上限
            'max_in_values'   => 50,  // in 操作的值数量上限
        ],
    ],
];
