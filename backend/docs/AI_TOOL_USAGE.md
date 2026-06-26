# AI 工具调用功能说明

## 功能概述

本系统实现了完整的 AI Agent 工具调用（Function Calling）功能，支持：

1. **全局工具库**：工具定义全局共享，可被多个 Agent 绑定
2. **多对多绑定**：一个 Agent 可以绑定多个工具，一个工具可以被多个 Agent 使用
3. **自动工具调用**：对话时 AI 自动判断是否需要调用工具，支持多轮调用
4. **多种工具类型**：
   - `function` - 本地类方法调用
   - `api` - 外部 HTTP API 调用
   - `plugin` - 插件（预留）

## 数据库变更

执行迁移脚本：
```bash
mysql database_name < database/migrate_ai_tool_20260626.sql
```

变更内容：
1. 新建 `ai_tool` 表 - 全局工具定义表
2. 新建 `ai_agent_tool_relation` 表 - Agent 与工具多对多关联表
3. `ai_conversation_message` 表新增 `tool_call_id` 和 `name` 字段

## 示例工具

执行种子数据：
```bash
mysql database_name < database/seed_ai_tools.sql
```

包含的示例工具：
1. **天气查询** (`get_weather`) - 查询当前天气
2. **数学计算** (`math_calculate`) - 执行数学表达式计算
3. **天气预报** (`get_weather_forecast`) - 查询未来天气预报

## 前端 API 变更

### 1. Agent 创建/编辑

**原字段**：`tools` (数组，每个元素是完整的工具对象)

**新字段**：`tool_ids` (工具 ID 数组，如 `[1, 2, 3]`)

```javascript
// 旧方式（废弃）
{
  "name": "我的助手",
  "tools": [
    {"name": "天气查询", "code": "get_weather", ...}
  ]
}

// 新方式
{
  "name": "我的助手",
  "tool_ids": [1, 2, 3]
}
```

### 2. 获取可用工具列表

新增接口：`GET /admin/ai/agent-tool/available`

返回所有启用的工具，供 Agent 绑定时选择使用。

### 3. 工具 CRUD

工具管理接口不变，但现在是全局工具库，不是每个 Agent 独立创建。

## 工具开发指南

### 1. 创建工具类

在 `app/admin/service/ai/tools/` 目录下创建工具类：

```php
<?php

namespace app\admin\service\ai\tools;

class MyCustomTool
{
    /**
     * @param array $args     AI 传入的参数（对应 parameters_schema 定义）
     * @param array $config   工具配置
     * @param array $context  上下文（user_id, conversation_id, agent_id）
     * @return mixed
     */
    public function myMethod(array $args, array $config, array $context): array
    {
        // 你的业务逻辑
        return [
            'success' => true,
            'data' => '...'
        ];
    }
}
```

### 2. 在后台创建工具

1. 进入「AI 智能」→「工具库」
2. 点击「新增工具」
3. 填写：
   - 工具名称：如 "股票查询"
   - 工具标识：如 `get_stock_price`（对应 function 名）
   - 描述：告诉 AI 这个工具的用途
   - 工具类型：`function` / `api` / `plugin`
   - 参数 Schema：JSON Schema 格式，定义入参
   - 处理器：`app\admin\service\ai\tools\YourClass@method`
   - 状态：启用

**参数 Schema 示例**：
```json
{
  "type": "object",
  "properties": {
    "symbol": {
      "type": "string",
      "description": "股票代码，如：AAPL、000001"
    }
  },
  "required": ["symbol"]
}
```

### 3. 绑定 Agent

在 Agent 编辑页面，勾选需要绑定的工具即可。

## 对话流程

### 非流式调用

```
用户提问 → 系统判断是否需要工具 → 调用工具 → 获取结果 → 再次调用 AI → 返回最终回答
```

支持多轮工具调用（最多 5 次，防止无限循环）。

### 流式调用

流式调用同样支持工具，但工具执行过程是非流式的，执行完成后继续流式输出最终回复。

## 消息结构

工具调用会产生多条消息：

1. **user** - 用户提问
2. **assistant** (带 tool_calls) - AI 决定调用工具
3. **tool** - 工具执行结果
4. **assistant** - AI 根据工具结果生成的最终回答

所有消息同属一个 `round_index`。

## 注意事项

1. **参数 Schema 很重要**：清晰的描述能帮助 AI 正确理解和使用工具
2. **工具返回值**：建议返回 JSON 可序列化的数组，便于 AI 理解
3. **异常处理**：工具执行失败时会返回 `{"error": "..."}`，AI 会根据错误信息调整
4. **幂等性**：工具调用可能被重试，确保工具方法是幂等的
5. **超时控制**：外部 API 调用建议设置合理超时（默认 30 秒）
