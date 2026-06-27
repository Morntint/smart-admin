/*
 Navicat Premium Dump SQL

 Source Server         : system
 Source Server Type    : MySQL
 Source Server Version : 80012 (8.0.12)
 Source Host           : localhost:3306
 Source Schema         : system

 Target Server Type    : MySQL
 Target Server Version : 80012 (8.0.12)
 File Encoding         : 65001

 Date: 27/06/2026 16:19:47
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for ai_agent
-- ----------------------------
DROP TABLE IF EXISTS `ai_agent`;
CREATE TABLE `ai_agent`  (
                             `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Agent ID',
                             `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Agent 名称',
                             `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Agent 标识(唯一)',
                             `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'robot' COMMENT '图标',
                             `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
                             `model_id` bigint(20) UNSIGNED NOT NULL COMMENT '关联AI模型ID',
                             `system_prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '系统提示词',
                             `welcome_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '欢迎语',
                             `suggested_questions` json NULL COMMENT '推荐问题列表',
                             `max_history_rounds` int(10) UNSIGNED NULL DEFAULT 10 COMMENT '最大历史轮数',
                             `temperature` decimal(3, 2) NULL DEFAULT NULL COMMENT '温度(覆盖模型默认值)',
                             `max_tokens` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '最大Token(覆盖模型默认值)',
                             `knowledge_base_ids` json NULL COMMENT '关联知识库ID列表',
                             `is_public` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否公开: 0=否, 1=是',
                             `is_streaming` tinyint(3) UNSIGNED NULL DEFAULT 1 COMMENT '是否流式输出: 0=否, 1=是',
                             `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                             `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '显示顺序',
                             `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                             `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                             `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                             `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                             `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                             PRIMARY KEY (`id`) USING BTREE,
                             UNIQUE INDEX `uk_code`(`code` ASC) USING BTREE,
                             INDEX `idx_model_id`(`model_id` ASC) USING BTREE,
                             INDEX `idx_status`(`status` ASC) USING BTREE,
                             INDEX `idx_created_by`(`created_by` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI Agent定义表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_agent
-- ----------------------------
INSERT INTO `ai_agent` VALUES (1, '代码助手', 'code-helper', 'robot', '专业代码开发助手，支持PHP、Webman、Docker、Python、Go、前端等全栈代码编写、排错、性能优化、注释生成、Dockerfile/监控指标解读、SQL优化，可分析报错日志、解读Prometheus监控指标、提供可直接运行的完整代码方案。', 1, '你是资深全栈开发代码助手，精通 Webman、PHP、Swoole、Docker、Linux、MySQL、Prometheus监控、前端、Python、Go 等技术栈，严格遵守以下规则：\n1. 用户提供需求、报错日志、Dockerfile、监控指标、数据库表结构时，优先给出可直接复制运行的完整代码/配置，不省略关键步骤；\n2. 解读监控指标（如prometheus histogram耗时、worker内存）时，自动计算平均耗时、分位数、资源占用，输出清晰表格+性能总结；\n3. 处理Docker镜像构建时，区分普通PHP与Swoole协程环境，指出缺失扩展、依赖问题并给出修正后的Dockerfile；\n4. 代码输出附带详细注释，分步骤说明作用，出现报错时先定位根因，再给出修复方案；\n5. 回答简洁高效，拒绝无关废话，用户需要长文档时分段排版、使用标题表格区分内容；\n6. 支持需求：写接口、修复bug、性能调优、数据库优化、容器部署、监控分析、命令脚本生成。', '你好，我是代码助手！可以帮你写代码、修复报错、优化Docker配置、解读服务监控指标、SQL优化，直接粘贴你的需求/日志/配置文件即可。', '[\"帮我优化 Webman 的 Dockerfile，开启 Swoole 协程\", \"解读这段 Prometheus 接口监控指标，分析性能瓶颈\"]', 10, 0.20, NULL, NULL, 1, 1, 1, 0, 1, '2026-06-25 20:55:43', 1, '2026-06-25 20:55:43', NULL);

-- ----------------------------
-- Table structure for ai_agent_tool_relation
-- ----------------------------
DROP TABLE IF EXISTS `ai_agent_tool_relation`;
CREATE TABLE `ai_agent_tool_relation`  (
                                           `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '关联ID',
                                           `agent_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Agent ID',
                                           `tool_id` bigint(20) UNSIGNED NOT NULL COMMENT '工具ID',
                                           `config` json NULL COMMENT '针对此 Agent 的工具配置覆盖',
                                           `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                           `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                           PRIMARY KEY (`id`) USING BTREE,
                                           UNIQUE INDEX `uk_agent_tool`(`agent_id` ASC, `tool_id` ASC) USING BTREE,
                                           INDEX `idx_agent_id`(`agent_id` ASC) USING BTREE,
                                           INDEX `idx_tool_id`(`tool_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Agent-工具多对多关联表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_agent_tool_relation
-- ----------------------------
INSERT INTO `ai_agent_tool_relation` VALUES (2, 1, 6, NULL, '2026-06-27 18:00:00', '2026-06-27 18:00:00');

-- ----------------------------
-- Table structure for ai_conversation
-- ----------------------------
DROP TABLE IF EXISTS `ai_conversation`;
CREATE TABLE `ai_conversation`  (
                                    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '会话ID',
                                    `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
                                    `agent_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Agent ID',
                                    `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '新对话' COMMENT '会话标题',
                                    `round_count` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '对话轮数',
                                    `total_tokens` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '累计Token消耗',
                                    `total_cost` decimal(10, 6) NULL DEFAULT 0.000000 COMMENT '累计费用',
                                    `status` tinyint(3) UNSIGNED NULL DEFAULT 1 COMMENT '状态: 0=归档, 1=活跃',
                                    `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                    `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                    PRIMARY KEY (`id`) USING BTREE,
                                    INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
                                    INDEX `idx_agent_id`(`agent_id` ASC) USING BTREE,
                                    INDEX `idx_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI对话会话表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_conversation
-- ----------------------------
INSERT INTO `ai_conversation` VALUES (7, 1, 1, '你好', 1, 0, 0.000000, 1, '2026-06-26 21:27:18', '2026-06-27 14:08:03');
INSERT INTO `ai_conversation` VALUES (8, 1, 1, '你好', 5, 0, 0.000000, 1, '2026-06-27 14:12:05', '2026-06-27 14:26:30');
INSERT INTO `ai_conversation` VALUES (9, 1, 1, '帮我生成一段示例代码', 1, 0, 0.000000, 1, '2026-06-27 14:42:45', '2026-06-27 14:42:52');

-- ----------------------------
-- Table structure for ai_conversation_message
-- ----------------------------
DROP TABLE IF EXISTS `ai_conversation_message`;
CREATE TABLE `ai_conversation_message`  (
                                            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '消息ID',
                                            `conversation_id` bigint(20) UNSIGNED NOT NULL COMMENT '会话ID',
                                            `round_index` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT '对话轮次',
                                            `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色: user/assistant/system/tool',
                                            `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '消息内容',
                                            `tool_calls` json NULL COMMENT '工具调用记录',
                                            `tool_call_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工具调用ID（tool角色用）',
                                            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工具名称（tool角色用）',
                                            `token_usage` json NULL COMMENT 'Token用量',
                                            `cost` decimal(10, 6) NULL DEFAULT 0.000000 COMMENT '单次费用',
                                            `duration` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '响应时长(ms)',
                                            `model_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '使用的模型',
                                            `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                            `updated_at` datetime NULL DEFAULT NULL COMMENT '更新时间',
                                            PRIMARY KEY (`id`) USING BTREE,
                                            INDEX `idx_conversation_id`(`conversation_id` ASC) USING BTREE,
                                            INDEX `idx_round_index`(`conversation_id` ASC, `round_index` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 59 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI对话消息表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_conversation_message
-- ----------------------------
INSERT INTO `ai_conversation_message` VALUES (45, 7, 1, 'user', '你好', NULL, NULL, NULL, NULL, 0.000000, 0, NULL, '2026-06-27 14:08:01', '2026-06-27 14:08:01');
INSERT INTO `ai_conversation_message` VALUES (46, 7, 1, 'assistant', '你好！有什么我可以帮助你的吗？', NULL, NULL, NULL, '{\"total_tokens\": 0, \"prompt_tokens\": 0, \"completion_tokens\": 0}', 0.000000, 2187, 'qwen-max', '2026-06-27 14:08:03', '2026-06-27 14:08:03');
INSERT INTO `ai_conversation_message` VALUES (47, 8, 1, 'user', '你好', NULL, NULL, NULL, NULL, 0.000000, 0, NULL, '2026-06-27 14:12:05', '2026-06-27 14:12:05');
INSERT INTO `ai_conversation_message` VALUES (48, 8, 1, 'assistant', '你好！有什么我可以帮助你的吗？无论是关于Webman、PHP、Swoole、Docker、Linux、MySQL、Prometheus监控，还是前端、Python、Go相关的问题，都可以问我。', NULL, NULL, NULL, '{\"total_tokens\": 0, \"prompt_tokens\": 0, \"completion_tokens\": 0}', 0.000000, 2549, 'qwen-max', '2026-06-27 14:12:08', '2026-06-27 14:12:08');
INSERT INTO `ai_conversation_message` VALUES (49, 8, 2, 'user', '你好', NULL, NULL, NULL, NULL, 0.000000, 0, NULL, '2026-06-27 14:20:50', '2026-06-27 14:20:50');
INSERT INTO `ai_conversation_message` VALUES (50, 8, 2, 'assistant', '你好！请问有什么具体问题或需求吗？比如开发新功能、解决技术难题、性能优化等，我会尽力提供帮助。', NULL, NULL, NULL, '{\"total_tokens\": 0, \"prompt_tokens\": 0, \"completion_tokens\": 0}', 0.000000, 1793, 'qwen-max', '2026-06-27 14:20:52', '2026-06-27 14:20:52');
INSERT INTO `ai_conversation_message` VALUES (51, 8, 3, 'user', '你好', NULL, NULL, NULL, NULL, 0.000000, 0, NULL, '2026-06-27 14:23:23', '2026-06-27 14:23:23');
INSERT INTO `ai_conversation_message` VALUES (52, 8, 3, 'assistant', '你好！看起来你可能还没有具体的问题。如果你有关于项目开发、调试错误、性能优化或是任何技术栈相关的需求，随时可以告诉我。无论是Webman框架使用、PHP/Swoole编程、Docker容器化部署还是其他方面，我都在这里帮助你。请随时提出你的需求或问题。', NULL, NULL, NULL, '{\"total_tokens\": 0, \"prompt_tokens\": 0, \"completion_tokens\": 0}', 0.000000, 2458, 'qwen-max', '2026-06-27 14:23:25', '2026-06-27 14:23:25');
INSERT INTO `ai_conversation_message` VALUES (53, 8, 4, 'user', '你好', NULL, NULL, NULL, NULL, 0.000000, 0, NULL, '2026-06-27 14:26:15', '2026-06-27 14:26:15');
INSERT INTO `ai_conversation_message` VALUES (54, 8, 4, 'assistant', '你好！请告诉我你具体需要帮助的内容。无论是编写代码、解决技术问题，还是进行系统优化等，我都很乐意提供支持。如果有具体的项目或技术点想要讨论，请直接说明吧。', NULL, NULL, NULL, '{\"total_tokens\": 0, \"prompt_tokens\": 0, \"completion_tokens\": 0}', 0.000000, 2334, 'qwen-max', '2026-06-27 14:26:17', '2026-06-27 14:26:17');
INSERT INTO `ai_conversation_message` VALUES (55, 8, 5, 'user', '你好', NULL, NULL, NULL, NULL, 0.000000, 0, NULL, '2026-06-27 14:26:29', '2026-06-27 14:26:29');
INSERT INTO `ai_conversation_message` VALUES (56, 8, 5, 'assistant', '你好！看起来你可能还没有具体的问题。如果你有任何技术需求或问题，请直接告诉我，我会尽力帮助你。无论是开发新功能、修复bug、性能调优、数据库优化、容器部署还是监控分析，都可以详细说明一下。这样我可以更有效地提供帮助。', NULL, NULL, NULL, '{\"total_tokens\": 0, \"prompt_tokens\": 0, \"completion_tokens\": 0}', 0.000000, 1595, 'qwen-max', '2026-06-27 14:26:30', '2026-06-27 14:26:30');
INSERT INTO `ai_conversation_message` VALUES (57, 9, 1, 'user', '帮我生成一段示例代码', NULL, NULL, NULL, NULL, 0.000000, 0, NULL, '2026-06-27 14:42:45', '2026-06-27 14:42:45');
INSERT INTO `ai_conversation_message` VALUES (58, 9, 1, 'assistant', '当然可以帮助您，不过请您先明确一下需要哪种类型的示例代码？比如是Webman框架下的API接口、Swoole的WebSocket服务端、Dockerfile编写、MySQL数据库操作脚本、Prometheus监控配置、前端页面代码、Python脚本还是Go语言程序等。另外，请告知具体的功能需求或业务场景，这样我能更准确地提供帮助。', NULL, NULL, NULL, '{\"total_tokens\": 0, \"prompt_tokens\": 0, \"completion_tokens\": 0}', 0.000000, 6386, 'qwen-max', '2026-06-27 14:42:51', '2026-06-27 14:42:51');

-- ----------------------------
-- Table structure for ai_document_chunk
-- ----------------------------
DROP TABLE IF EXISTS `ai_document_chunk`;
CREATE TABLE `ai_document_chunk`  (
                                      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分块ID',
                                      `document_id` bigint(20) UNSIGNED NOT NULL COMMENT '文档ID',
                                      `kb_id` bigint(20) UNSIGNED NOT NULL COMMENT '知识库ID',
                                      `chunk_index` int(10) UNSIGNED NOT NULL COMMENT '分块序号',
                                      `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分块内容',
                                      `char_count` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '字符数',
                                      `embedding` json NULL COMMENT '向量数据(JSON存储)',
                                      `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                      PRIMARY KEY (`id`) USING BTREE,
                                      INDEX `idx_document_id`(`document_id` ASC) USING BTREE,
                                      INDEX `idx_kb_id`(`kb_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '文档向量分块表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_document_chunk
-- ----------------------------
INSERT INTO `ai_document_chunk` VALUES (1, 1, 1, 1, '# Webman 生产Docker镜像标准构建规范\n基础镜像：php:8.3-cli-alpine\n必须安装扩展：pdo、pdo_mysql、pcntl、bcmath、swoole协程扩展\n系统依赖：openssl-dev libaio-dev\nOPcache配置：内存512M，jit_buffer_size=128M\n启动命令：php start.php start\n健康检查：/ping 接口验证服务存活\nWorker进程建议4进程，内存单进程控制20MB以内', 233, NULL, '2026-06-25 20:56:56');

-- ----------------------------
-- Table structure for ai_knowledge_base
-- ----------------------------
DROP TABLE IF EXISTS `ai_knowledge_base`;
CREATE TABLE `ai_knowledge_base`  (
                                      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '知识库ID',
                                      `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '知识库名称',
                                      `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
                                      `embedding_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'text-embedding-3-small' COMMENT '向量化模型',
                                      `embedding_dimension` int(10) UNSIGNED NULL DEFAULT 1536 COMMENT '向量维度',
                                      `chunk_size` int(10) UNSIGNED NULL DEFAULT 1000 COMMENT '分块大小(字符数)',
                                      `chunk_overlap` int(10) UNSIGNED NULL DEFAULT 200 COMMENT '分块重叠(字符数)',
                                      `top_k` int(10) UNSIGNED NULL DEFAULT 5 COMMENT '检索返回条数',
                                      `similarity_threshold` decimal(4, 3) NULL DEFAULT 0.700 COMMENT '相似度阈值',
                                      `document_count` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '文档数量',
                                      `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                                      `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '排序',
                                      `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                                      `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                      `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                                      `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                      `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                                      PRIMARY KEY (`id`) USING BTREE,
                                      INDEX `idx_status`(`status` ASC) USING BTREE,
                                      INDEX `idx_created_by`(`created_by` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI知识库表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_knowledge_base
-- ----------------------------
INSERT INTO `ai_knowledge_base` VALUES (1, '技术文档库', '存放Webman、PHP、Swoole、Docker、MySQL、Prometheus监控、项目部署、接口开发、容器镜像、运维排错相关内部技术文档、配置模板、故障解决方案，用于代码助手Agent检索内部业务规范与项目资料。', 'text-embedding-3-small', 1536, 1000, 200, 5, 0.700, 1, 1, 0, 1, '2026-06-25 20:56:30', 1, '2026-06-25 20:56:56', NULL);

-- ----------------------------
-- Table structure for ai_knowledge_document
-- ----------------------------
DROP TABLE IF EXISTS `ai_knowledge_document`;
CREATE TABLE `ai_knowledge_document`  (
                                          `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '文档ID',
                                          `kb_id` bigint(20) UNSIGNED NOT NULL COMMENT '知识库ID',
                                          `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文档标题',
                                          `file_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'txt' COMMENT '文件类型: txt/pdf/docx/md/html/url',
                                          `file_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件地址',
                                          `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '文档原始内容',
                                          `char_count` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '字符数',
                                          `chunk_count` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '分块数量',
                                          `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待处理, 1=处理中, 2=已完成, 3=失败',
                                          `error_msg` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '错误信息',
                                          `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                                          `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                          `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                          `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                                          PRIMARY KEY (`id`) USING BTREE,
                                          INDEX `idx_kb_id`(`kb_id` ASC) USING BTREE,
                                          INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '知识库文档表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_knowledge_document
-- ----------------------------
INSERT INTO `ai_knowledge_document` VALUES (1, 1, 'Webman 生产环境 Dockerfile 标准模板', 'txt', NULL, '# Webman 生产Docker镜像标准构建规范\n基础镜像：php:8.3-cli-alpine\n必须安装扩展：pdo、pdo_mysql、pcntl、bcmath、swoole协程扩展\n系统依赖：openssl-dev libaio-dev\nOPcache配置：内存512M，jit_buffer_size=128M\n启动命令：php start.php start\n健康检查：/ping 接口验证服务存活\nWorker进程建议4进程，内存单进程控制20MB以内', 233, 1, 2, NULL, 1, '2026-06-25 20:56:56', '2026-06-25 20:56:56', NULL);

-- ----------------------------
-- Table structure for ai_model
-- ----------------------------
DROP TABLE IF EXISTS `ai_model`;
CREATE TABLE `ai_model`  (
                             `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '模型ID',
                             `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模型名称',
                             `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '供应商: openai/deepseek/qwen/zhipu/moonshot/custom',
                             `model_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模型标识: gpt-4o/deepseek-chat/qwen-max',
                             `base_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'API 基础地址',
                             `api_key` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'API密钥(加密存储)',
                             `max_tokens` int(10) UNSIGNED NULL DEFAULT 4096 COMMENT '最大输出 Token',
                             `temperature` decimal(3, 2) NULL DEFAULT 0.70 COMMENT '默认温度 0-2',
                             `top_p` decimal(3, 2) NULL DEFAULT 1.00 COMMENT '核采样参数',
                             `context_window` int(10) UNSIGNED NULL DEFAULT 128000 COMMENT '上下文窗口大小',
                             `supports_vision` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否支持视觉: 0=否, 1=是',
                             `supports_function_calling` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否支持函数调用: 0=否, 1=是',
                             `supports_streaming` tinyint(3) UNSIGNED NULL DEFAULT 1 COMMENT '是否支持流式输出: 0=否, 1=是',
                             `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                             `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '显示顺序',
                             `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                             `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                             `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                             `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                             `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                             `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                             PRIMARY KEY (`id`) USING BTREE,
                             INDEX `idx_provider`(`provider` ASC) USING BTREE,
                             INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI模型供应商表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_model
-- ----------------------------
INSERT INTO `ai_model` VALUES (1, 'qwen-max', 'qwen', 'qwen-max', 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'sk-ws-H.RPPIRDL.oZ9a.MEUCIQCsJFovK6ZiFac7TJQAd7Nap5YU4nY4Va80i6ZhPAjuLQIgcWp-rsBGgrWsZquyB55DCTJ4dzxBB6CGDslrFuvkAs0', 4096, 0.70, 1.00, 128000, 0, 0, 1, 1, 0, '', 1, '2026-06-25 20:53:45', 1, '2026-06-25 21:07:37', NULL);

-- ----------------------------
-- Table structure for ai_prompt_template
-- ----------------------------
DROP TABLE IF EXISTS `ai_prompt_template`;
CREATE TABLE `ai_prompt_template`  (
                                       `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '模板ID',
                                       `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板名称',
                                       `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板标识(唯一)',
                                       `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'general' COMMENT '分类: general/coding/marketing/analysis/custom',
                                       `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
                                       `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提示词内容(支持变量占位符 {{variable}})',
                                       `variables` json NULL COMMENT '变量定义',
                                       `is_system` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否系统内置: 0=否, 1=是',
                                       `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                                       `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '排序',
                                       `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                                       `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                       `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                                       `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                       `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                                       PRIMARY KEY (`id`) USING BTREE,
                                       UNIQUE INDEX `uk_code`(`code` ASC) USING BTREE,
                                       INDEX `idx_category`(`category` ASC) USING BTREE,
                                       INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI提示词模板表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_prompt_template
-- ----------------------------
INSERT INTO `ai_prompt_template` VALUES (1, '代码审查助手', 'code-review', 'general', '用于PHP/Webman/Swoole项目代码自动审查，检测语法错误、协程安全问题、SQL注入、内存泄漏、性能隐患、Dockerfile不规范、接口阻塞问题，输出整改清单+修复后完整代码。', '你是资深PHP/Webman/Swoole代码审计专家，严格按照下面规则审查用户提供的{{code_content}}代码片段，输出完整审计报告：\n1. 风险分级：严重阻断bug、性能隐患、规范问题、安全漏洞；\n2. 重点检查项：\n   - Swoole协程安全：全局变量污染、协程客户端复用、阻塞IO；\n   - Webman规范：进程内存、健康检查、启动配置、Prometheus埋点；\n   - 数据库：SQL注入、无索引查询、慢SQL、事务遗漏；\n   - Dockerfile：缺失Swoole扩展、镜像体积过大、缺少缓存清理、时区配置；\n   - 内存泄漏：循环创建对象、未释放连接、大数组常驻内存；\n   - 接口并发：无限流、同步阻塞、异常未捕获；\n3. 输出格式：\n   1）问题清单（每条标注风险等级+代码行数）\n   2）问题根因说明\n   3）修复后完整可运行代码\n   4）优化建议（性能/安全/规范）\n用户待审计代码：\n{{code_content}}', '{\"code_content\": \"待审查的完整代码/配置文件内容\"}', 0, 1, 0, 1, '2026-06-25 20:57:30', 1, '2026-06-25 20:57:30', NULL);
INSERT INTO `ai_prompt_template` VALUES (2, '系统时间信息注入', 'system_time_info', 'system', '在对话开始时向 AI 注入当前日期和时间，解决日期查询问题。支持变量：{{current_date}}（当前日期 Y-m-d）、{{current_date_time}}（当前日期时间 Y-m-d H:i:s）', '\r\n\r\n## 当前时间信息\r\n- 今天日期：{{current_date}}\r\n- 当前时间：{{current_date_time}}\r\n\r\n重要提示：当用户说\"今天\"、\"今日\"、\"本周\"、\"本月\"等时间相关词时，请使用上述正确的日期计算。\r\n', '[\"current_date\", \"current_date_time\"]', 1, 1, 0, NULL, '2026-06-26 14:11:35', NULL, '2026-06-26 14:11:35', NULL);

-- ----------------------------
-- Table structure for ai_tool
-- ----------------------------
DROP TABLE IF EXISTS `ai_tool`;
CREATE TABLE `ai_tool`  (
                            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '工具ID',
                            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工具名称',
                            `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工具标识(函数名)',
                            `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工具描述',
                            `tool_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'function' COMMENT '工具类型: function/api/plugin',
                            `parameters_schema` json NULL COMMENT '参数定义(JSON Schema)',
                            `handler` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '处理器: 类名或URL',
                            `config` json NULL COMMENT '工具配置',
                            `status` tinyint(3) UNSIGNED NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                            `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '排序',
                            `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                            `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                            `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                            `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                            PRIMARY KEY (`id`) USING BTREE,
                            UNIQUE INDEX `uk_code`(`code` ASC) USING BTREE,
                            INDEX `idx_status`(`status` ASC) USING BTREE,
                            INDEX `idx_created_by`(`created_by` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI工具库表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_tool
-- ----------------------------
INSERT INTO `ai_tool` VALUES (6, '数据查询', 'query_database', '使用结构化 DSL 查询业务数据库（表/字段/操作符全部白名单限制，禁止裸 SQL）。可查询的表与字段、相对日期约定由 system prompt 自动注入；调用时仅需给出 table/filters/order_by/limit/aggregations/group_by。', 'function', '{\"type\": \"object\", \"required\": [\"table\"], \"properties\": {\"table\": {\"type\": \"string\", \"description\": \"要查询的表名（必须是白名单内的表）\"}, \"fields\": {\"type\": \"array\", \"items\": {\"type\": \"string\"}, \"description\": \"要返回的字段，省略则返回该表所有可见字段；与 aggregations 互斥\"}, \"filters\": {\"type\": \"array\", \"items\": {\"type\": \"object\", \"required\": [\"field\", \"op\"], \"properties\": {\"field\": {\"type\": \"string\"}, \"op\": {\"type\": \"string\", \"enum\": [\"=\", \"!=\", \">\", \">=\", \"<\", \"<=\", \"like\", \"in\", \"between\", \"is_null\", \"is_not_null\"]}, \"value\": {}}}, \"description\": \"过滤条件列表（AND 关系），最多 10 条\"}, \"order_by\": {\"type\": \"array\", \"items\": {\"type\": \"object\", \"required\": [\"field\"], \"properties\": {\"field\": {\"type\": \"string\"}, \"direction\": {\"type\": \"string\", \"enum\": [\"asc\", \"desc\"]}}}}, \"limit\": {\"type\": \"integer\", \"minimum\": 1, \"maximum\": 200}, \"offset\": {\"type\": \"integer\", \"minimum\": 0}, \"aggregations\": {\"type\": \"array\", \"items\": {\"type\": \"object\", \"required\": [\"fn\"], \"properties\": {\"fn\": {\"type\": \"string\", \"enum\": [\"count\", \"sum\", \"avg\", \"max\", \"min\"]}, \"field\": {\"type\": \"string\"}, \"alias\": {\"type\": \"string\"}}}, \"description\": \"聚合查询；指定后建议同时给 group_by\"}, \"group_by\": {\"type\": \"array\", \"items\": {\"type\": \"string\"}}}}', 'app\\admin\\service\\ai\\tools\\NL2SqlTool@execute', '{}', 1, 1, NULL, NULL, '2026-06-27 18:00:00', '2026-06-27 18:00:00');

-- ----------------------------
-- Table structure for ai_usage_record
-- ----------------------------
DROP TABLE IF EXISTS `ai_usage_record`;
CREATE TABLE `ai_usage_record`  (
                                    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
                                    `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
                                    `agent_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT 'Agent ID',
                                    `model_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模型名称',
                                    `prompt_tokens` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '输入Token',
                                    `completion_tokens` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '输出Token',
                                    `total_tokens` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '总Token',
                                    `cost` decimal(10, 6) NULL DEFAULT 0.000000 COMMENT '费用(美元)',
                                    `endpoint` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '调用接口',
                                    `duration` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '耗时(ms)',
                                    `status` tinyint(3) UNSIGNED NULL DEFAULT 1 COMMENT '状态: 0=失败, 1=成功',
                                    `error_msg` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '错误信息',
                                    `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                    PRIMARY KEY (`id`) USING BTREE,
                                    INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
                                    INDEX `idx_agent_id`(`agent_id` ASC) USING BTREE,
                                    INDEX `idx_created_at`(`created_at` ASC) USING BTREE,
                                    INDEX `idx_model_name`(`model_name` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 25 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI用量统计表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ai_usage_record
-- ----------------------------
INSERT INTO `ai_usage_record` VALUES (17, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 3475, 1, NULL, '2026-06-26 17:58:34');
INSERT INTO `ai_usage_record` VALUES (18, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 2187, 1, NULL, '2026-06-27 14:08:03');
INSERT INTO `ai_usage_record` VALUES (19, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 2549, 1, NULL, '2026-06-27 14:12:08');
INSERT INTO `ai_usage_record` VALUES (20, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 1793, 1, NULL, '2026-06-27 14:20:52');
INSERT INTO `ai_usage_record` VALUES (21, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 2458, 1, NULL, '2026-06-27 14:23:25');
INSERT INTO `ai_usage_record` VALUES (22, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 2334, 1, NULL, '2026-06-27 14:26:17');
INSERT INTO `ai_usage_record` VALUES (23, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 1595, 1, NULL, '2026-06-27 14:26:30');
INSERT INTO `ai_usage_record` VALUES (24, 1, 1, 'qwen-max', 0, 0, 0, 0.000000, 'chat', 6386, 1, NULL, '2026-06-27 14:42:52');

-- ----------------------------
-- Table structure for sys_announcement
-- ----------------------------
DROP TABLE IF EXISTS `sys_announcement`;
CREATE TABLE `sys_announcement`  (
                                     `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '公告ID',
                                     `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '公告标题',
                                     `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '公告内容(支持富文本)',
                                     `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'notice' COMMENT '分类: notice通知/announcement公告/activity活动/maintenance维护',
                                     `level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info' COMMENT '级别: info普通/important重要/urgent紧急',
                                     `is_top` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否置顶: 0=否, 1=是',
                                     `is_popup` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否弹窗强提示: 0=否, 1=是',
                                     `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿, 1=已发布, 2=已下线',
                                     `publisher_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '发布人ID',
                                     `published_at` datetime NULL DEFAULT NULL COMMENT '发布时间',
                                     `effective_at` datetime NULL DEFAULT NULL COMMENT '生效时间',
                                     `expire_at` datetime NULL DEFAULT NULL COMMENT '失效时间(为空则长期有效)',
                                     `view_count` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '阅读量',
                                     `sort` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '显示顺序',
                                     `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                                     `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                                     `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                     `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                                     `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                     `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                                     PRIMARY KEY (`id`) USING BTREE,
                                     INDEX `idx_status`(`status` ASC) USING BTREE,
                                     INDEX `idx_category`(`category` ASC) USING BTREE,
                                     INDEX `idx_is_top`(`is_top` ASC) USING BTREE,
                                     INDEX `idx_published_at`(`published_at` ASC) USING BTREE,
                                     INDEX `idx_effective`(`effective_at` ASC, `expire_at` ASC) USING BTREE,
                                     INDEX `idx_publisher_id`(`publisher_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统公告表(全员可见的公告)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of sys_announcement
-- ----------------------------
INSERT INTO `sys_announcement` VALUES (1, '测试公告', '<p>1111asdsadas</p>', 'announcement', 'info', 1, 1, 1, 1, '2026-06-27 10:48:05', NULL, NULL, 0, 0, NULL, 1, '2026-06-27 10:48:05', 1, '2026-06-27 13:09:32', '2026-06-27 13:09:32');
INSERT INTO `sys_announcement` VALUES (2, '测试公告..', '<p>asddddasd</p>', 'announcement', 'info', 1, 1, 1, 1, '2026-06-27 13:01:34', NULL, NULL, 0, 0, NULL, 1, '2026-06-27 13:01:34', 1, '2026-06-27 13:10:14', NULL);

-- ----------------------------
-- Table structure for sys_config
-- ----------------------------
DROP TABLE IF EXISTS `sys_config`;
CREATE TABLE `sys_config`  (
                               `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
                               `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置名称',
                               `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键名',
                               `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '配置值',
                               `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string' COMMENT '配置类型: string, number, boolean, json',
                               `group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'default' COMMENT '配置分组',
                               `options` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '可选值(JSON格式)',
                               `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '显示顺序',
                               `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                               `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                               `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                               `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                               `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                               PRIMARY KEY (`id`) USING BTREE,
                               UNIQUE INDEX `uk_key`(`key` ASC) USING BTREE,
                               INDEX `idx_group`(`group` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 146 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_config
-- ----------------------------
INSERT INTO `sys_config` VALUES (1, '系统名称', 'sys_name', '企业后台管理系统', 'string', 'basic', NULL, 1, '系统名称', NULL, '2026-04-23 18:28:31', NULL, NULL);
INSERT INTO `sys_config` VALUES (2, '系统Logo', 'sys_logo', '/static/admin/images/logo.png', 'string', 'basic', NULL, 2, '系统Logo', NULL, '2026-04-23 18:28:31', NULL, NULL);
INSERT INTO `sys_config` VALUES (3, '系统版本', 'sys_version', '1.0.0', 'string', 'basic', NULL, 3, '系统版本', NULL, '2026-04-23 18:28:31', NULL, NULL);
INSERT INTO `sys_config` VALUES (4, '版权信息', 'sys_copyright', '© 2024 Company. All rights reserved.', 'string', 'basic', NULL, 4, '版权信息', NULL, '2026-04-23 18:28:31', NULL, NULL);
INSERT INTO `sys_config` VALUES (5, '文件上传大小限制', 'upload_file_size', '10', 'number', 'upload', NULL, 1, '单位: MB', NULL, '2026-04-23 18:28:31', NULL, NULL);
INSERT INTO `sys_config` VALUES (6, '图片上传大小限制', 'upload_image_size', '5', 'number', 'upload', NULL, 2, '单位: MB', NULL, '2026-04-23 18:28:31', NULL, NULL);
INSERT INTO `sys_config` VALUES (7, '允许上传的文件类型', 'upload_allowed_ext', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,zip', 'string', 'upload', NULL, 3, '文件扩展名', NULL, '2026-04-23 18:28:31', 1, '2026-06-21 20:37:17');
INSERT INTO `sys_config` VALUES (100, '公众号AppID', 'wechat_official_appid', '', 'string', 'wechat', NULL, 1, '微信公众号开发者ID', NULL, '2026-06-27 15:53:44', NULL, NULL);
INSERT INTO `sys_config` VALUES (101, '公众号AppSecret', 'wechat_official_secret', '', 'string', 'wechat', NULL, 2, '微信公众号开发者密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (102, '公众号Token', 'wechat_official_token', '', 'string', 'wechat', NULL, 3, '微信公众号服务器配置令牌', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (103, '公众号EncodingAESKey', 'wechat_official_aes_key', '', 'string', 'wechat', NULL, 4, '微信公众号消息加解密密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (110, '小程序AppID', 'wechat_mini_appid', '', 'string', 'wechat', NULL, 11, '微信小程序开发者ID', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (111, '小程序AppSecret', 'wechat_mini_secret', '', 'string', 'wechat', NULL, 12, '微信小程序开发者密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (112, '小程序Token', 'wechat_mini_token', '', 'string', 'wechat', NULL, 13, '微信小程序服务器配置令牌', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (113, '小程序EncodingAESKey', 'wechat_mini_aes_key', '', 'string', 'wechat', NULL, 14, '微信小程序消息加解密密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (120, '开放平台AppID', 'wechat_open_appid', '', 'string', 'wechat', NULL, 21, '微信开放平台开发者ID', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (121, '开放平台AppSecret', 'wechat_open_secret', '', 'string', 'wechat', NULL, 22, '微信开放平台开发者密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (122, '开放平台Token', 'wechat_open_token', '', 'string', 'wechat', NULL, 23, '微信开放平台服务器配置令牌', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (123, '开放平台EncodingAESKey', 'wechat_open_aes_key', '', 'string', 'wechat', NULL, 24, '微信开放平台消息加解密密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (130, '企业微信CorpID', 'wechat_work_corp_id', '', 'string', 'wechat', NULL, 31, '企业微信企业ID', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (131, '企业微信AgentID', 'wechat_work_agent_id', '', 'string', 'wechat', NULL, 32, '企业微信应用AgentID', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (132, '企业微信Secret', 'wechat_work_secret', '', 'string', 'wechat', NULL, 33, '企业微信应用密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (133, '企业微信Token', 'wechat_work_token', '', 'string', 'wechat', NULL, 34, '企业微信服务器配置令牌', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (134, '企业微信EncodingAESKey', 'wechat_work_aes_key', '', 'string', 'wechat', NULL, 35, '企业微信消息加解密密钥', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (140, '微信支付AppID', 'wechat_pay_appid', '', 'string', 'wechat', NULL, 41, '微信支付绑定的AppID', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (141, '微信支付商户号', 'wechat_pay_mch_id', '', 'string', 'wechat', NULL, 42, '微信支付商户号', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (142, '微信支付APIv2密钥', 'wechat_pay_secret_key', '', 'string', 'wechat', NULL, 43, '微信支付 APIv2 key（部分旧接口仍需）', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (143, '微信支付APIv3密钥', 'wechat_pay_v3_key', '', 'string', 'wechat', NULL, 44, '微信支付 APIv3 key', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (144, '微信支付商户私钥', 'wechat_pay_private_key', '', 'string', 'wechat', NULL, 45, '商户私钥（推荐填写文件绝对路径，apiclient_key.pem）', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (145, '微信支付商户证书', 'wechat_pay_certificate', '', 'string', 'wechat', NULL, 46, '商户证书（推荐填写文件绝对路径，apiclient_cert.pem）', NULL, '2026-06-27 15:53:45', NULL, NULL);
INSERT INTO `sys_config` VALUES (146, '微信支付回调地址', 'wechat_pay_notify_url', '', 'string', 'wechat', NULL, 47, '微信支付异步通知回调URL', NULL, '2026-06-27 15:53:45', NULL, NULL);

-- ----------------------------
-- Table structure for sys_department
-- ----------------------------
DROP TABLE IF EXISTS `sys_department`;
CREATE TABLE `sys_department`  (
                                   `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '部门ID',
                                   `parent_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父部门ID',
                                   `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '部门名称',
                                   `leader` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '负责人',
                                   `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系电话',
                                   `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱',
                                   `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '显示顺序',
                                   `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                                   `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                                   `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                                   `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                   `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                                   `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                   `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                                   PRIMARY KEY (`id`) USING BTREE,
                                   INDEX `idx_parent_id`(`parent_id` ASC) USING BTREE,
                                   INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统部门表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_department
-- ----------------------------
INSERT INTO `sys_department` VALUES (1, 0, '总公司', '-', '-', '-', 1, 1, '', NULL, '2026-04-23 18:28:31', 1, '2026-06-23 01:43:16', NULL);
INSERT INTO `sys_department` VALUES (2, 1, '技术部', NULL, NULL, NULL, 1, 1, '', 1, '2026-06-22 13:41:07', 1, '2026-06-23 23:20:49', NULL);
INSERT INTO `sys_department` VALUES (3, 1, '运营部', NULL, NULL, NULL, 1, 1, '', 1, '2026-06-22 13:42:03', 1, '2026-06-23 23:20:55', NULL);

-- ----------------------------
-- Table structure for sys_dict
-- ----------------------------
DROP TABLE IF EXISTS `sys_dict`;
CREATE TABLE `sys_dict`  (
                             `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '字典ID',
                             `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '字典名称',
                             `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '字典编码',
                             `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string' COMMENT '字典类型',
                             `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                             `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                             `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                             `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                             `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                             `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                             `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                             PRIMARY KEY (`id`) USING BTREE,
                             UNIQUE INDEX `uk_code`(`code` ASC) USING BTREE,
                             INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '字典类型表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_dict
-- ----------------------------
INSERT INTO `sys_dict` VALUES (1, '用户性别', 'sys_user_sex', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict` VALUES (2, '用户状态', 'sys_user_status', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict` VALUES (3, '菜单状态', 'sys_menu_status', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict` VALUES (4, '角色状态', 'sys_role_status', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict` VALUES (5, '部门状态', 'sys_dept_status', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict` VALUES (6, '是否可见', 'sys_yes_no', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict` VALUES (7, '是否外链', 'sys_external', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict` VALUES (8, '菜单类型', 'sys_menu_type', 'string', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);

-- ----------------------------
-- Table structure for sys_dict_data
-- ----------------------------
DROP TABLE IF EXISTS `sys_dict_data`;
CREATE TABLE `sys_dict_data`  (
                                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '字典数据ID',
                                  `dict_id` bigint(20) UNSIGNED NOT NULL COMMENT '字典ID',
                                  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '字典标签',
                                  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '字典键值',
                                  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '样式类型',
                                  `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '显示顺序',
                                  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                                  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                                  `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                                  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                  `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                                  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                                  `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                                  PRIMARY KEY (`id`) USING BTREE,
                                  UNIQUE INDEX `uk_dict_value`(`dict_id` ASC, `value` ASC) USING BTREE,
                                  INDEX `idx_dict_id`(`dict_id` ASC) USING BTREE,
                                  INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 20 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '字典数据表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_dict_data
-- ----------------------------
INSERT INTO `sys_dict_data` VALUES (1, 1, '未知', '0', NULL, 0, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (2, 1, '男', '1', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (3, 1, '女', '2', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (4, 2, '正常', '1', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (5, 2, '禁用', '0', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (6, 3, '正常', '1', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (7, 3, '禁用', '0', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (8, 4, '正常', '1', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (9, 4, '禁用', '0', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (10, 5, '正常', '1', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (11, 5, '禁用', '0', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (12, 6, '否', '0', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (13, 6, '是', '1', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (14, 7, '否', '0', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (15, 7, '是', '1', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (16, 8, '目录', '1', NULL, 1, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (17, 8, '菜单', '2', NULL, 2, 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_dict_data` VALUES (18, 8, '按钮', '3', NULL, 4, 1, '', NULL, '2026-04-23 18:28:31', 1, '2026-06-21 16:52:51', NULL);

-- ----------------------------
-- Table structure for sys_file
-- ----------------------------
DROP TABLE IF EXISTS `sys_file`;
CREATE TABLE `sys_file`  (
                             `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '文件ID',
                             `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名称',
                             `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
                             `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
                             `file_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '访问URL',
                             `file_size` bigint(20) UNSIGNED NOT NULL COMMENT '文件大小(字节)',
                             `file_ext` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件扩展名',
                             `file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件MIME类型',
                             `storage_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local' COMMENT '存储类型: local, oss, cos, s3',
                             `upload_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '上传IP',
                             `upload_user_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '上传用户ID',
                             `download_count` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '下载次数',
                             `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                             `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                             `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                             `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                             PRIMARY KEY (`id`) USING BTREE,
                             INDEX `idx_file_ext`(`file_ext` ASC) USING BTREE,
                             INDEX `idx_upload_user_id`(`upload_user_id` ASC) USING BTREE,
                             INDEX `idx_status`(`status` ASC) USING BTREE,
                             INDEX `idx_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '文件管理表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_file
-- ----------------------------

-- ----------------------------
-- Table structure for sys_login_log
-- ----------------------------
DROP TABLE IF EXISTS `sys_login_log`;
CREATE TABLE `sys_login_log`  (
                                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日志ID',
                                  `user_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '用户ID',
                                  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '用户名',
                                  `login_type` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '登录类型: 1=登录, 2=登出',
                                  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '登录IP',
                                  `ip_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'IP所在地',
                                  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User-Agent',
                                  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=失败, 1=成功',
                                  `msg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '提示消息',
                                  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',
                                  PRIMARY KEY (`id`) USING BTREE,
                                  INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
                                  INDEX `idx_username`(`username` ASC) USING BTREE,
                                  INDEX `idx_status`(`status` ASC) USING BTREE,
                                  INDEX `idx_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 65 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统登录日志表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_login_log
-- ----------------------------
INSERT INTO `sys_login_log` VALUES (1, NULL, '', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '用户名格式不正确', '2026-06-16 16:30:09');
INSERT INTO `sys_login_log` VALUES (2, NULL, '', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '用户名格式不正确', '2026-06-16 16:32:27');
INSERT INTO `sys_login_log` VALUES (3, NULL, '', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '用户名格式不正确', '2026-06-16 16:33:29');
INSERT INTO `sys_login_log` VALUES (4, NULL, '', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '用户名格式不正确', '2026-06-16 16:35:04');
INSERT INTO `sys_login_log` VALUES (5, NULL, '', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '用户名格式不正确', '2026-06-16 16:35:32');
INSERT INTO `sys_login_log` VALUES (6, NULL, '', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '用户名格式不正确', '2026-06-16 16:35:38');
INSERT INTO `sys_login_log` VALUES (7, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '密码错误', '2026-06-16 16:38:07');
INSERT INTO `sys_login_log` VALUES (8, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '密码错误', '2026-06-16 16:40:16');
INSERT INTO `sys_login_log` VALUES (9, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 0, '密码错误', '2026-06-16 16:47:28');
INSERT INTO `sys_login_log` VALUES (10, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '登录成功', '2026-06-16 16:48:32');
INSERT INTO `sys_login_log` VALUES (11, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '登录成功', '2026-06-16 16:51:23');
INSERT INTO `sys_login_log` VALUES (12, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '登录成功', '2026-06-16 16:51:42');
INSERT INTO `sys_login_log` VALUES (13, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '登录成功', '2026-06-16 19:23:34');
INSERT INTO `sys_login_log` VALUES (14, 1, 'admin', 2, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '退出成功', '2026-06-16 19:23:56');
INSERT INTO `sys_login_log` VALUES (15, 1, 'admin', 2, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '退出成功', '2026-06-16 19:23:58');
INSERT INTO `sys_login_log` VALUES (16, 1, 'admin', 2, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '退出成功', '2026-06-16 19:24:00');
INSERT INTO `sys_login_log` VALUES (17, 1, 'admin', 2, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '退出成功', '2026-06-16 19:24:06');
INSERT INTO `sys_login_log` VALUES (18, 1, 'admin', 1, '127.0.0.1', NULL, 'Apifox/1.0.0 (https://apifox.com)', 1, '登录成功', '2026-06-16 21:57:04');
INSERT INTO `sys_login_log` VALUES (19, NULL, 'Super', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 0, '用户不存在', '2026-06-17 15:03:27');
INSERT INTO `sys_login_log` VALUES (20, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-17 15:03:45');
INSERT INTO `sys_login_log` VALUES (21, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-17 18:34:16');
INSERT INTO `sys_login_log` VALUES (22, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-18 13:56:47');
INSERT INTO `sys_login_log` VALUES (23, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-18 16:16:26');
INSERT INTO `sys_login_log` VALUES (24, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-19 00:38:42');
INSERT INTO `sys_login_log` VALUES (25, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/149.0.0.0', 1, '登录成功', '2026-06-21 14:16:56');
INSERT INTO `sys_login_log` VALUES (26, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-21 16:45:46');
INSERT INTO `sys_login_log` VALUES (27, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-21 20:30:19');
INSERT INTO `sys_login_log` VALUES (28, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 12:42:03');
INSERT INTO `sys_login_log` VALUES (29, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 15:07:17');
INSERT INTO `sys_login_log` VALUES (30, 2, 'user', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 1, '登录成功', '2026-06-22 17:06:38');
INSERT INTO `sys_login_log` VALUES (31, 2, 'user', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 17:23:22');
INSERT INTO `sys_login_log` VALUES (32, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 17:23:41');
INSERT INTO `sys_login_log` VALUES (33, 2, 'user', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 17:24:38');
INSERT INTO `sys_login_log` VALUES (34, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 17:25:50');
INSERT INTO `sys_login_log` VALUES (35, 1, 'admin', 1, '127.0.0.1', NULL, NULL, 0, '密码错误', '2026-06-22 17:37:24');
INSERT INTO `sys_login_log` VALUES (36, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 19:42:22');
INSERT INTO `sys_login_log` VALUES (37, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-22 23:36:34');
INSERT INTO `sys_login_log` VALUES (38, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-23 01:37:12');
INSERT INTO `sys_login_log` VALUES (39, 2, 'user', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 1, '登录成功', '2026-06-23 03:11:08');
INSERT INTO `sys_login_log` VALUES (40, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-23 10:35:47');
INSERT INTO `sys_login_log` VALUES (41, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-23 11:20:44');
INSERT INTO `sys_login_log` VALUES (42, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-23 11:33:59');
INSERT INTO `sys_login_log` VALUES (43, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-23 21:07:44');
INSERT INTO `sys_login_log` VALUES (44, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-23 23:20:04');
INSERT INTO `sys_login_log` VALUES (45, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-24 17:20:07');
INSERT INTO `sys_login_log` VALUES (46, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-24 19:05:37');
INSERT INTO `sys_login_log` VALUES (47, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-24 22:52:24');
INSERT INTO `sys_login_log` VALUES (48, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-25 02:43:06');
INSERT INTO `sys_login_log` VALUES (49, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-25 12:17:50');
INSERT INTO `sys_login_log` VALUES (50, 1, 'admin', 1, '127.0.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-25 12:21:13');
INSERT INTO `sys_login_log` VALUES (51, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-25 15:07:16');
INSERT INTO `sys_login_log` VALUES (52, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT; Windows NT 10.0; zh-CN) WindowsPowerShell/5.1.26100.8655', 1, '登录成功', '2026-06-25 15:08:25');
INSERT INTO `sys_login_log` VALUES (53, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-25 17:16:24');
INSERT INTO `sys_login_log` VALUES (54, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-26 00:16:38');
INSERT INTO `sys_login_log` VALUES (55, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-26 07:35:21');
INSERT INTO `sys_login_log` VALUES (56, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-26 11:48:16');
INSERT INTO `sys_login_log` VALUES (57, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-26 17:39:54');
INSERT INTO `sys_login_log` VALUES (58, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-26 21:13:48');
INSERT INTO `sys_login_log` VALUES (59, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-26 23:16:57');
INSERT INTO `sys_login_log` VALUES (60, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-27 01:38:19');
INSERT INTO `sys_login_log` VALUES (61, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-27 09:22:39');
INSERT INTO `sys_login_log` VALUES (62, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-27 12:56:00');
INSERT INTO `sys_login_log` VALUES (63, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-27 13:00:20');
INSERT INTO `sys_login_log` VALUES (64, 1, 'admin', 1, '172.18.0.1', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, '登录成功', '2026-06-27 14:44:04');

-- ----------------------------
-- Table structure for sys_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_menu`;
CREATE TABLE `sys_menu`  (
                             `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '菜单ID',
                             `parent_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父菜单ID',
                             `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '菜单名称',
                             `route_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '路由名称(PascalCase)',
                             `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '菜单图标',
                             `path` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '路由地址',
                             `component` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '组件路径',
                             `redirect` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '重定向地址',
                             `is_external` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否外链: 0=否, 1=是',
                             `is_cache` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否缓存: 0=否, 1=是',
                             `is_visible` tinyint(3) UNSIGNED NULL DEFAULT 1 COMMENT '是否显示: 0=隐藏, 1=显示',
                             `is_hide_tab` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否隐藏标签: 0=否, 1=是',
                             `is_iframe` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否iframe: 0=否, 1=是',
                             `is_full_page` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否全屏页面: 0=否, 1=是',
                             `fixed_tab` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '是否固定标签: 0=否, 1=是',
                             `active_path` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '激活菜单路径',
                             `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '显示顺序',
                             `type` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '菜单类型: 1=目录, 2=菜单, 3=按钮',
                             `permission` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '权限标识',
                             `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                             `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                             `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                             `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                             `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                             `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                             `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                             PRIMARY KEY (`id`) USING BTREE,
                             INDEX `idx_parent_id`(`parent_id` ASC) USING BTREE,
                             INDEX `idx_type`(`type` ASC) USING BTREE,
                             INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1049 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统菜单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_menu
-- ----------------------------
INSERT INTO `sys_menu` VALUES (1, 0, '系统管理', 'System', 'ri:list-settings-fill', '/system', '/index/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 100, 1, '', 1, '', 1, '2026-04-23 18:28:31', 1, '2026-06-23 02:58:09', NULL);
INSERT INTO `sys_menu` VALUES (2, 1, '用户管理', 'User', 'ri:user-line', 'user', '/system/user', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 101, 2, '', 1, '', NULL, '2026-04-23 18:28:31', NULL, '2026-06-23 02:57:05', NULL);
INSERT INTO `sys_menu` VALUES (3, 1, '角色管理', 'Role', 'ri:user-settings-line', 'role', '/system/role', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 102, 2, '', 1, '', NULL, '2026-04-23 18:28:31', NULL, '2026-06-23 02:57:11', NULL);
INSERT INTO `sys_menu` VALUES (4, 1, '菜单管理', 'Menu', 'ri:menu-2-fill', 'menu', '/system/menu', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 103, 2, '', 1, '', NULL, '2026-04-23 18:28:31', 1, '2026-06-23 02:57:14', NULL);
INSERT INTO `sys_menu` VALUES (5, 1, '部门管理', 'Dept', 'ri:building-line', 'dept', '/system/dept', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 104, 2, '', 1, '', NULL, '2026-04-23 18:28:31', 1, '2026-06-23 02:57:19', NULL);
INSERT INTO `sys_menu` VALUES (101, 1, '系统配置', 'Config', 'ri:settings-4-line', 'config', '/system/config', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 2, '', 1, '', NULL, '2026-04-23 18:28:31', 1, '2026-06-23 02:56:47', NULL);
INSERT INTO `sys_menu` VALUES (102, 1, '字典管理', 'Dict', 'ri:book-marked-line', 'dict', '/system/dict', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 2, '', 1, '', NULL, '2026-04-23 18:28:31', 1, '2026-06-23 02:56:46', NULL);
INSERT INTO `sys_menu` VALUES (103, 1, '操作日志', 'Log', 'ri:file-list-3-line', 'log', '/system/log', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 2, '', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, '2026-06-23 02:57:28', NULL);
INSERT INTO `sys_menu` VALUES (104, 1, '文件管理', 'File', 'ri:folder-shield-2-line', 'file', '/system/file', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 2, NULL, 1, NULL, NULL, '2026-06-18 14:49:49', NULL, '2026-06-23 02:57:33', NULL);
INSERT INTO `sys_menu` VALUES (110, 1, '通知公告', 'Notice', 'ri:notification-3-line', 'notice', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 10, 1, '', 1, '', 1, '2026-06-26 22:55:00', 1, '2026-06-27 11:15:58', NULL);
INSERT INTO `sys_menu` VALUES (111, 110, '系统通知', 'SystemNotice', 'ri:mail-line', 'system-notice', '/system/notice/system', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 2, '', 1, '', 1, '2026-06-26 22:55:00', 1, '2026-06-27 09:58:36', NULL);
INSERT INTO `sys_menu` VALUES (112, 110, '系统公告', 'SystemAnnouncement', 'ri:volume-up-line', 'system-announcement', '/system/notice/announcement', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 2, '', 1, '', 1, '2026-06-26 22:55:00', 1, '2026-06-26 22:55:00', NULL);
INSERT INTO `sys_menu` VALUES (113, 111, '通知查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:notice:list', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (114, 111, '通知发送', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:notice:add', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (115, 111, '通知编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:notice:edit', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (116, 111, '通知删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:notice:del', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (117, 111, '标记已读', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 5, 3, 'system:notice:read', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (118, 112, '公告查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:announcement:list', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (119, 112, '公告新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:announcement:add', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (120, 112, '公告编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:announcement:edit', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (121, 112, '公告删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:announcement:del', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (122, 112, '公告发布', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 5, 3, 'system:announcement:publish', 1, NULL, 1, '2026-06-26 22:55:00', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (125, 1, '用户中心', 'UserCenter', 'ri:user-line', 'user-center', '/system/user-center', '', 0, 0, 0, 0, 0, 0, 0, NULL, 7, 2, '', 1, NULL, 1, '2026-06-27 09:25:04', 1, '2026-06-27 09:25:04', NULL);
INSERT INTO `sys_menu` VALUES (200, 0, 'AI 智能', 'AI', 'ri:cpu-line', '/ai', '/index/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 6, 1, '', 1, '', NULL, '2026-06-25 18:18:36', 1, '2026-06-27 09:56:01', NULL);
INSERT INTO `sys_menu` VALUES (201, 2, '用户查询', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:user:list', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (202, 2, '用户新增', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:user:add', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (203, 2, '用户编辑', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:user:edit', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (204, 2, '用户删除', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:user:del', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (205, 2, '重置密码', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 5, 3, 'system:user:resetPwd', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (210, 200, '对话工作台', 'AIChat', 'ri:chat-ai-line', 'chat', '/ai/chat/index', NULL, 0, 1, 1, 0, 0, 0, 0, NULL, 1, 2, '', 1, '', NULL, '2026-06-25 18:18:36', 1, '2026-06-27 09:56:13', NULL);
INSERT INTO `sys_menu` VALUES (220, 200, 'Agent 管理', 'AIAgent', 'ri:robot-2-line', 'agent', '/ai/agent/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 2, '', 1, '', NULL, '2026-06-25 18:18:36', 1, '2026-06-27 09:56:09', NULL);
INSERT INTO `sys_menu` VALUES (221, 220, 'Agent 新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'ai:agent:create', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (222, 220, 'Agent 编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'ai:agent:update', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (223, 220, 'Agent 删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'ai:agent:delete', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (224, 200, '工具库', 'AIAgentTool', 'ri:tools-fill', 'agent-tool', '/ai/agent-tool/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 2, '', 1, '', NULL, '2026-06-25 20:50:58', 1, '2026-06-27 09:56:34', NULL);
INSERT INTO `sys_menu` VALUES (225, 224, '工具新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'ai:tool:create', 1, NULL, NULL, '2026-06-25 20:50:58', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (226, 224, '工具编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'ai:tool:update', 1, NULL, NULL, '2026-06-25 20:50:58', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (227, 224, '工具删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'ai:tool:delete', 1, NULL, NULL, '2026-06-25 20:50:58', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (230, 200, '模型管理', 'AIModel', 'ri:brain-ai-3-line', 'model', '/ai/model/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 2, '', 1, '', NULL, '2026-06-25 18:18:36', 1, '2026-06-27 09:56:10', NULL);
INSERT INTO `sys_menu` VALUES (231, 230, '模型新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'ai:model:create', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (232, 230, '模型编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'ai:model:update', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (233, 230, '模型删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'ai:model:delete', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (240, 200, '知识库', 'AIKnowledge', 'ri:file-text-line', 'knowledge', '/ai/knowledge/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 2, '', 1, '', NULL, '2026-06-25 18:18:36', 1, '2026-06-27 09:56:14', NULL);
INSERT INTO `sys_menu` VALUES (241, 240, '知识库新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'ai:knowledge:create', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (242, 240, '知识库编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'ai:knowledge:update', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (243, 240, '知识库删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'ai:knowledge:delete', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (244, 240, '文档上传', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'ai:knowledge:upload', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (250, 200, '提示词管理', 'AIPrompt', 'ri:pencil-ai-line', 'prompt', '/ai/prompt/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 5, 2, '', 1, '', NULL, '2026-06-25 18:18:36', 1, '2026-06-27 09:56:19', NULL);
INSERT INTO `sys_menu` VALUES (251, 250, '提示词新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'ai:prompt:create', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (252, 250, '提示词编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'ai:prompt:update', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (253, 250, '提示词删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'ai:prompt:delete', 1, NULL, NULL, '2026-06-25 18:18:36', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (260, 200, '用量统计', 'AIUsage', 'ri:bar-chart-2-line', 'usage', '/ai/usage/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 6, 2, '', 1, '', NULL, '2026-06-25 18:18:36', 1, '2026-06-27 09:56:21', NULL);
INSERT INTO `sys_menu` VALUES (301, 3, '角色查询', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:role:list', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (302, 3, '角色新增', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:role:add', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (303, 3, '角色编辑', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:role:edit', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (304, 3, '角色删除', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:role:del', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (305, 3, '分配权限', NULL, '', '', '', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 5, 3, 'system:role:assign', 1, NULL, NULL, '2026-04-23 18:28:31', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (306, 1, '登录日志', NULL, 'ri:shield-user-line', 'login-log', '/system/login-log', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 2, NULL, 1, '', 1, '2026-06-22 15:21:03', NULL, '2026-06-22 15:22:53', NULL);
INSERT INTO `sys_menu` VALUES (401, 4, '菜单查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:menu:list', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (402, 4, '菜单新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:menu:add', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (403, 4, '菜单编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:menu:edit', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (404, 4, '菜单删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:menu:del', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (501, 5, '部门查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:dept:list', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (502, 5, '部门新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:dept:add', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (503, 5, '部门编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:dept:edit', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (504, 5, '部门删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:dept:del', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (600, 0, '微信管理', NULL, 'ri:wechat-2-line', '/wechat', '/index/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 80, 1, NULL, 1, NULL, NULL, '2026-06-27 15:21:35', NULL, '2026-06-27 15:22:14', NULL);
INSERT INTO `sys_menu` VALUES (601, 600, '配置管理', NULL, 'ri:settings-3-line', 'manage', '/wechat/config/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 2, 'wechat:config:list', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, '2026-06-27 15:55:48', NULL);
INSERT INTO `sys_menu` VALUES (602, 600, '用户管理', NULL, 'ri:user-line', 'wechat-user', '/wechat/user/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 2, 'wechat:user:list', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, '2026-06-27 15:43:46', NULL);
INSERT INTO `sys_menu` VALUES (603, 602, '同步用户', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'wechat:user:sync', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (604, 600, '消息管理', NULL, 'ri:message-2-line', 'message', '/wechat/message/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 2, 'wechat:message:list', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, '2026-06-27 15:42:12', NULL);
INSERT INTO `sys_menu` VALUES (605, 604, '发送消息', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'wechat:message:send', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (606, 600, '模板管理', NULL, 'ri:file-text-line', 'template', '/wechat/template/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 2, 'wechat:template:list', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, '2026-06-27 15:42:00', NULL);
INSERT INTO `sys_menu` VALUES (607, 606, '同步模板', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'wechat:template:sync', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (608, 600, '菜单管理', NULL, 'ri:menu-line', 'wechat-menu', '/wechat/menu/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 5, 2, 'wechat:menu:list', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, '2026-06-27 15:43:42', NULL);
INSERT INTO `sys_menu` VALUES (609, 608, '发布菜单', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'wechat:menu:publish', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (610, 600, '素材管理', NULL, 'ri:image-line', 'material', '/wechat/material/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 6, 2, 'wechat:material:list', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, '2026-06-27 15:42:22', NULL);
INSERT INTO `sys_menu` VALUES (611, 610, '同步素材', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'wechat:material:sync', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (612, 601, '配置编辑', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'wechat:config:edit', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (613, 601, '查看密钥', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'wechat:config:view-secret', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (614, 608, '菜单编辑', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'wechat:menu:edit', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (615, 610, '删除素材', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'wechat:material:del', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (616, 600, 'JSSDK 配置', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 90, 3, 'wechat:jssdk:view', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (617, 600, '小程序码', NULL, '', '', NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 91, 3, 'wechat:mini:qrcode', 1, NULL, NULL, '2026-06-27 15:21:35', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1011, 101, '配置查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:config:list', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1012, 101, '配置新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:config:add', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1013, 101, '配置编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:config:edit', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1014, 101, '配置删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:config:del', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1021, 102, '字典查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:dict:list', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1022, 102, '字典新增', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:dict:add', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1023, 102, '字典编辑', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 3, 3, 'system:dict:edit', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1024, 102, '字典删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 4, 3, 'system:dict:del', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1031, 103, '操作日志查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:log:operationList', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1032, 103, '操作日志删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:log:operationDel', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1041, 104, '登录日志查询', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 3, 'system:log:loginList', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1042, 104, '登录日志删除', NULL, NULL, NULL, NULL, NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 2, 3, 'system:log:loginDel', 1, NULL, NULL, '2026-06-22 19:02:30', NULL, NULL, NULL);
INSERT INTO `sys_menu` VALUES (1044, 0, '仪表盘', 'Dashboard', 'ri:pie-chart-line', '/dashboard', '/index/index', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 1, '', 1, '', 1, '2026-06-22 19:52:11', 1, '2026-06-27 09:56:26', NULL);
INSERT INTO `sys_menu` VALUES (1045, 1044, '工作台', 'Console', 'ri:home-smile-2-line', 'console', '/dashboard/console', NULL, 0, 0, 1, 0, 0, 0, 0, NULL, 1, 2, '', 1, '', 1, '2026-06-22 19:59:53', 1, '2026-06-23 02:55:00', NULL);
INSERT INTO `sys_menu` VALUES (1048, 110, '通知收件箱', 'NoticeInbox', '', 'notice-inbox', '/system/notice/notice-inbox', NULL, 0, 0, 0, 0, 0, 0, 0, NULL, 1, 2, 'NoticeInbox', 1, '', 1, '2026-06-27 11:53:42', NULL, '2026-06-27 11:53:42', NULL);

-- ----------------------------
-- Table structure for sys_notice
-- ----------------------------
DROP TABLE IF EXISTS `sys_notice`;
CREATE TABLE `sys_notice`  (
                               `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '通知ID',
                               `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '接收用户ID',
                               `type` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '通知类型: 1=系统通知, 2=待办, 3=预警, 4=个人',
                               `level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info' COMMENT '级别: info/success/warning/danger',
                               `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通知标题',
                               `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '通知内容',
                               `biz_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '业务类型(如 order/contract/audit 等)',
                               `biz_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '业务ID',
                               `link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '跳转链接',
                               `is_read` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否已读: 0=未读, 1=已读',
                               `read_time` datetime NULL DEFAULT NULL COMMENT '阅读时间',
                               `sender_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '发送者ID(系统通知为NULL)',
                               `expire_time` datetime NULL DEFAULT NULL COMMENT '过期时间(为空则永不过期)',
                               `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                               `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                               `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                               PRIMARY KEY (`id`) USING BTREE,
                               INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
                               INDEX `idx_type`(`type` ASC) USING BTREE,
                               INDEX `idx_is_read`(`is_read` ASC) USING BTREE,
                               INDEX `idx_biz`(`biz_type` ASC, `biz_id` ASC) USING BTREE,
                               INDEX `idx_user_unread`(`user_id` ASC, `is_read` ASC) USING BTREE,
                               INDEX `idx_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统通知表(用户维度的站内信)' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of sys_notice
-- ----------------------------
INSERT INTO `sys_notice` VALUES (1, 1, 1, 'info', '测试通知', '<p>1111</p>', NULL, NULL, NULL, 1, '2026-06-27 10:49:02', 1, NULL, '2026-06-27 10:47:48', '2026-06-27 10:49:02', NULL);
INSERT INTO `sys_notice` VALUES (2, 1, 1, 'info', '踩踩踩', '得得', NULL, NULL, NULL, 1, '2026-06-27 10:56:26', 1, NULL, '2026-06-27 10:52:54', '2026-06-27 10:56:26', NULL);
INSERT INTO `sys_notice` VALUES (3, 2, 1, 'info', '踩踩踩', '得得', NULL, NULL, NULL, 0, NULL, 1, NULL, '2026-06-27 10:52:54', NULL, NULL);

-- ----------------------------
-- Table structure for sys_operation_log
-- ----------------------------
DROP TABLE IF EXISTS `sys_operation_log`;
CREATE TABLE `sys_operation_log`  (
                                      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日志ID',
                                      `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '操作模块',
                                      `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '操作类型',
                                      `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '请求方法',
                                      `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '请求地址',
                                      `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '请求IP',
                                      `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'User-Agent',
                                      `user_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '操作用户ID',
                                      `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '操作用户名',
                                      `param` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '请求参数',
                                      `result` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '返回结果',
                                      `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=异常, 1=正常',
                                      `error_msg` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '错误信息',
                                      `duration` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '执行时长(ms)',
                                      `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
                                      PRIMARY KEY (`id`) USING BTREE,
                                      INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
                                      INDEX `idx_module`(`module` ASC) USING BTREE,
                                      INDEX `idx_action`(`action` ASC) USING BTREE,
                                      INDEX `idx_status`(`status` ASC) USING BTREE,
                                      INDEX `idx_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 203 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统操作日志表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_operation_log
-- ----------------------------
INSERT INTO `sys_operation_log` VALUES (176, 'ai', NULL, 'POST', '/admin/ai/chat/conversations', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"agent_id\":1}', '{\"code\":200,\"msg\":\"创建成功\",\"data\":{\"user_id\":1,\"agent_id\":1,\"title\":\"你好，我是代码助手！可以帮你写代码、修复报错、优化Docker配置、解读服务监控指标、SQL优化，直\",\"updated_at\":\"2026-06-26 17:58:15\",\"created_at\":\"2026-06-26 17:58:15\",\"id\":6}}', 1, NULL, 19, '2026-06-26 17:58:15');
INSERT INTO `sys_operation_log` VALUES (177, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"content\":\"你好\",\"conversation_id\":6,\"tool_ids\":[]}', NULL, 0, '未知错误', 3589, '2026-06-26 17:58:34');
INSERT INTO `sys_operation_log` VALUES (178, 'ai', NULL, 'DELETE', '/admin/ai/chat/conversations/6', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"删除成功\"}', 1, NULL, 57, '2026-06-26 17:58:39');
INSERT INTO `sys_operation_log` VALUES (179, 'ai', NULL, 'POST', '/admin/ai/chat/conversations', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"agent_id\":1}', '{\"code\":200,\"msg\":\"创建成功\",\"data\":{\"user_id\":1,\"agent_id\":1,\"title\":\"你好，我是代码助手！可以帮你写代码、修复报错、优化Docker配置、解读服务监控指标、SQL优化，直\",\"updated_at\":\"2026-06-26 21:27:18\",\"created_at\":\"2026-06-26 21:27:18\",\"id\":7}}', 1, NULL, 127, '2026-06-26 21:27:18');
INSERT INTO `sys_operation_log` VALUES (180, 'notice', NULL, 'POST', '/admin/notice', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"user_id\":2,\"type\":1,\"level\":\"info\",\"title\":\"测试通知\",\"content\":\"<p>1111<\\/p>\",\"biz_type\":\"\",\"biz_id\":\"\",\"link\":\"\",\"expire_time\":\"\"}', '{\"code\":200,\"msg\":\"发送成功\",\"data\":{\"id\":1}}', 1, NULL, 130, '2026-06-27 10:47:48');
INSERT INTO `sys_operation_log` VALUES (181, 'announcement', NULL, 'POST', '/admin/announcement', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"title\":\"测试公告\",\"content\":\"<p>1111<\\/p>\",\"category\":\"announcement\",\"level\":\"info\",\"is_top\":0,\"is_popup\":0,\"status\":0,\"effective_at\":\"\",\"expire_at\":\"\",\"sort\":0,\"remark\":\"\"}', '{\"code\":200,\"msg\":\"创建成功\",\"data\":{\"id\":1}}', 1, NULL, 59, '2026-06-27 10:48:05');
INSERT INTO `sys_operation_log` VALUES (182, 'announcement', NULL, 'PUT', '/admin/announcement/1/publish', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"发布成功\"}', 1, NULL, 23, '2026-06-27 10:48:05');
INSERT INTO `sys_operation_log` VALUES (183, 'notice', NULL, 'PUT', '/admin/notice/1/read', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"已标记为已读\"}', 1, NULL, 27, '2026-06-27 10:49:02');
INSERT INTO `sys_operation_log` VALUES (184, 'notice', NULL, 'POST', '/admin/notice/batch', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"user_ids\":[2,1],\"type\":1,\"level\":\"info\",\"title\":\"踩踩踩\",\"biz_type\":\"\",\"biz_id\":\"\",\"link\":\"\",\"expire_time\":\"\",\"content\":\"得得\"}', '{\"code\":200,\"msg\":\"发送成功\",\"data\":{\"count\":2}}', 1, NULL, 42, '2026-06-27 10:52:54');
INSERT INTO `sys_operation_log` VALUES (185, 'notice', NULL, 'PUT', '/admin/notice/read-all', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"已全部标记为已读\",\"data\":{\"count\":1}}', 1, NULL, 49, '2026-06-27 10:56:26');
INSERT INTO `sys_operation_log` VALUES (186, 'menu', NULL, 'POST', '/admin/menu', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"parent_id\":110,\"name\":\"通知收件箱\",\"route_name\":\"NoticeInbox\",\"path\":\"notice-inbox\",\"component\":\"\\/system\\/notice\\/notice-inbox\",\"icon\":\"\",\"type\":2,\"permission\":\"NoticeInbox\",\"sort\":1,\"status\":1,\"is_visible\":0,\"is_cache\":0,\"is_hide_tab\":0,\"is_iframe\":0,\"is_full_page\":0,\"fixed_tab\":0,\"is_external\":0}', '{\"code\":200,\"msg\":\"创建成功\",\"data\":{\"id\":1048}}', 1, NULL, 99, '2026-06-27 11:53:42');
INSERT INTO `sys_operation_log` VALUES (187, 'announcement', NULL, 'PUT', '/admin/announcement/1', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"title\":\"测试公告\",\"content\":\"<p>1111<\\/p>\",\"category\":\"announcement\",\"level\":\"info\",\"is_top\":1,\"is_popup\":1,\"status\":1,\"effective_at\":\"\",\"expire_at\":\"\",\"sort\":0,\"remark\":\"\"}', '{\"code\":200,\"msg\":\"更新成功\"}', 1, NULL, 101, '2026-06-27 12:44:03');
INSERT INTO `sys_operation_log` VALUES (188, 'announcement', NULL, 'PUT', '/admin/announcement/1/publish', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"发布成功\"}', 1, NULL, 6, '2026-06-27 12:44:03');
INSERT INTO `sys_operation_log` VALUES (189, 'announcement', NULL, 'PUT', '/admin/announcement/1', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"title\":\"测试公告\",\"content\":\"<p>1111asdsadas<\\/p>\",\"category\":\"announcement\",\"level\":\"info\",\"is_top\":1,\"is_popup\":1,\"status\":1,\"effective_at\":\"\",\"expire_at\":\"\",\"sort\":0,\"remark\":\"\"}', '{\"code\":200,\"msg\":\"更新成功\"}', 1, NULL, 34, '2026-06-27 13:01:18');
INSERT INTO `sys_operation_log` VALUES (190, 'announcement', NULL, 'PUT', '/admin/announcement/1/publish', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"发布成功\"}', 1, NULL, 9, '2026-06-27 13:01:18');
INSERT INTO `sys_operation_log` VALUES (191, 'announcement', NULL, 'POST', '/admin/announcement', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"title\":\"ccccc\",\"content\":\"<p>asddddasd<\\/p>\",\"category\":\"announcement\",\"level\":\"info\",\"is_top\":1,\"is_popup\":1,\"status\":0,\"effective_at\":\"\",\"expire_at\":\"\",\"sort\":0,\"remark\":\"\"}', '{\"code\":200,\"msg\":\"创建成功\",\"data\":{\"id\":2}}', 1, NULL, 39, '2026-06-27 13:01:34');
INSERT INTO `sys_operation_log` VALUES (192, 'announcement', NULL, 'PUT', '/admin/announcement/2/publish', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"发布成功\"}', 1, NULL, 22, '2026-06-27 13:01:34');
INSERT INTO `sys_operation_log` VALUES (193, 'announcement', NULL, 'DELETE', '/admin/announcement/1', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"删除成功\"}', 1, NULL, 130, '2026-06-27 13:09:32');
INSERT INTO `sys_operation_log` VALUES (194, 'announcement', NULL, 'PUT', '/admin/announcement/2', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"title\":\"测试公告..\",\"content\":\"<p>asddddasd<\\/p>\",\"category\":\"announcement\",\"level\":\"info\",\"is_top\":1,\"is_popup\":1,\"status\":1,\"effective_at\":\"\",\"expire_at\":\"\",\"sort\":0,\"remark\":\"\"}', '{\"code\":200,\"msg\":\"更新成功\"}', 1, NULL, 53, '2026-06-27 13:10:14');
INSERT INTO `sys_operation_log` VALUES (195, 'announcement', NULL, 'PUT', '/admin/announcement/2/publish', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', NULL, '{\"code\":200,\"msg\":\"发布成功\"}', 1, NULL, 7, '2026-06-27 13:10:14');
INSERT INTO `sys_operation_log` VALUES (196, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"conversation_id\":7,\"agent_id\":1,\"content\":\"你好\"}', NULL, 0, '未知错误', 2278, '2026-06-27 14:08:03');
INSERT INTO `sys_operation_log` VALUES (197, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"agent_id\":1,\"content\":\"你好\"}', NULL, 0, '未知错误', 2761, '2026-06-27 14:12:08');
INSERT INTO `sys_operation_log` VALUES (198, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"conversation_id\":8,\"agent_id\":1,\"content\":\"你好\"}', NULL, 0, '未知错误', 1859, '2026-06-27 14:20:52');
INSERT INTO `sys_operation_log` VALUES (199, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"conversation_id\":8,\"agent_id\":1,\"content\":\"你好\"}', NULL, 0, '未知错误', 2509, '2026-06-27 14:23:25');
INSERT INTO `sys_operation_log` VALUES (200, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"conversation_id\":8,\"agent_id\":1,\"content\":\"你好\"}', NULL, 0, '未知错误', 2400, '2026-06-27 14:26:17');
INSERT INTO `sys_operation_log` VALUES (201, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"content\":\"你好\",\"conversation_id\":8,\"tool_ids\":[]}', NULL, 0, '未知错误', 1654, '2026-06-27 14:26:30');
INSERT INTO `sys_operation_log` VALUES (202, 'ai', NULL, 'POST', '/admin/ai/chat/stream', '172.18.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', 1, 'admin', '{\"agent_id\":1,\"content\":\"帮我生成一段示例代码\"}', NULL, 0, '未知错误', 6544, '2026-06-27 14:42:52');

-- ----------------------------
-- Table structure for sys_role
-- ----------------------------
DROP TABLE IF EXISTS `sys_role`;
CREATE TABLE `sys_role`  (
                             `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '角色ID',
                             `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色名称',
                             `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色代码',
                             `sort` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '显示顺序',
                             `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                             `data_scope` tinyint(3) UNSIGNED NULL DEFAULT 1 COMMENT '数据范围: 1=全部, 2=本部门, 3=本部门及以下, 4=仅本人, 5=自定义',
                             `data_scope_depts` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '自定义数据范围部门ID列表',
                             `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                             `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                             `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                             `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                             `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                             `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                             PRIMARY KEY (`id`) USING BTREE,
                             UNIQUE INDEX `uk_code`(`code` ASC) USING BTREE,
                             INDEX `idx_status`(`status` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统角色表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_role
-- ----------------------------
INSERT INTO `sys_role` VALUES (1, '超级管理员', 'super_admin', 1, 1, 1, NULL, NULL, 1, '2026-04-23 18:28:31', NULL, '2026-06-23 01:39:06', NULL);
INSERT INTO `sys_role` VALUES (2, '普通管理员', 'admin', 2, 1, 1, NULL, NULL, 1, '2026-04-23 18:28:31', NULL, '2026-06-23 01:39:07', NULL);

-- ----------------------------
-- Table structure for sys_role_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_role_menu`;
CREATE TABLE `sys_role_menu`  (
                                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                  `role_id` bigint(20) UNSIGNED NOT NULL COMMENT '角色ID',
                                  `menu_id` bigint(20) UNSIGNED NOT NULL COMMENT '菜单ID',
                                  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                  PRIMARY KEY (`id`) USING BTREE,
                                  UNIQUE INDEX `uk_role_menu`(`role_id` ASC, `menu_id` ASC) USING BTREE,
                                  INDEX `idx_role_id`(`role_id` ASC) USING BTREE,
                                  INDEX `idx_menu_id`(`menu_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 187 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '角色菜单关联表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_role_menu
-- ----------------------------
INSERT INTO `sys_role_menu` VALUES (1, 1, 1, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (2, 1, 2, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (3, 1, 3, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (4, 1, 4, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (5, 1, 5, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (6, 1, 101, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (7, 1, 102, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (8, 1, 103, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (9, 1, 104, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (10, 1, 201, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (11, 1, 202, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (12, 1, 203, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (13, 1, 204, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (14, 1, 205, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (15, 1, 301, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (16, 1, 302, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (17, 1, 303, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (18, 1, 304, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (19, 1, 305, '2026-04-23 18:28:31');
INSERT INTO `sys_role_menu` VALUES (84, 1, 105, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (85, 1, 401, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (86, 1, 402, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (87, 1, 403, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (88, 1, 404, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (89, 1, 501, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (90, 1, 502, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (91, 1, 503, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (92, 1, 504, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (93, 1, 1011, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (94, 1, 1012, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (95, 1, 1013, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (96, 1, 1014, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (97, 1, 1021, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (98, 1, 1022, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (99, 1, 1023, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (100, 1, 1024, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (101, 1, 1031, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (102, 1, 1032, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (103, 1, 1041, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (104, 1, 1042, '2026-06-22 19:02:30');
INSERT INTO `sys_role_menu` VALUES (121, 2, 1011, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (122, 2, 1021, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (123, 2, 1031, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (124, 2, 201, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (125, 2, 202, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (126, 2, 1, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (127, 2, 101, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (128, 2, 102, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (129, 2, 103, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (130, 2, 2, '2026-06-23 03:11:32');
INSERT INTO `sys_role_menu` VALUES (131, 1, 210, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (132, 1, 220, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (133, 1, 221, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (134, 1, 222, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (135, 1, 223, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (136, 1, 230, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (137, 1, 231, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (138, 1, 232, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (139, 1, 233, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (140, 1, 240, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (141, 1, 241, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (142, 1, 242, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (143, 1, 243, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (144, 1, 244, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (145, 1, 250, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (146, 1, 251, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (147, 1, 252, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (148, 1, 253, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (149, 1, 260, '2026-06-25 18:18:36');
INSERT INTO `sys_role_menu` VALUES (162, 1, 224, '2026-06-25 20:50:58');
INSERT INTO `sys_role_menu` VALUES (163, 1, 225, '2026-06-25 20:50:58');
INSERT INTO `sys_role_menu` VALUES (164, 1, 226, '2026-06-25 20:50:58');
INSERT INTO `sys_role_menu` VALUES (165, 1, 227, '2026-06-25 20:50:58');
INSERT INTO `sys_role_menu` VALUES (170, 1, 110, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (171, 1, 111, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (172, 1, 112, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (173, 1, 113, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (174, 1, 114, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (175, 1, 115, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (176, 1, 116, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (177, 1, 117, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (178, 1, 118, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (179, 1, 119, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (180, 1, 120, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (181, 1, 121, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (182, 1, 122, '2026-06-26 22:55:00');
INSERT INTO `sys_role_menu` VALUES (183, 1, 125, '2026-06-27 09:25:04');
INSERT INTO `sys_role_menu` VALUES (184, 2, 125, '2026-06-27 09:25:04');
INSERT INTO `sys_role_menu` VALUES (186, 1, 200, '2026-06-27 15:21:35');

-- ----------------------------
-- Table structure for sys_user
-- ----------------------------
DROP TABLE IF EXISTS `sys_user`;
CREATE TABLE `sys_user`  (
                             `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
                             `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
                             `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码(加密)',
                             `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '昵称',
                             `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '头像',
                             `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱',
                             `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号',
                             `sex` tinyint(3) UNSIGNED NULL DEFAULT 0 COMMENT '性别: 0=未知, 1=男, 2=女',
                             `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
                             `dept_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '部门ID',
                             `login_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '最后登录IP',
                             `login_time` datetime NULL DEFAULT NULL COMMENT '最后登录时间',
                             `login_count` int(10) UNSIGNED NULL DEFAULT 0 COMMENT '登录次数',
                             `token_version` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Token版本号: 改密/重置/禁用/登出时自增, 使旧Token失效',
                             `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                             `created_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '创建者',
                             `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                             `updated_by` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '更新者',
                             `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                             `deleted_at` datetime NULL DEFAULT NULL COMMENT '删除时间',
                             PRIMARY KEY (`id`) USING BTREE,
                             UNIQUE INDEX `uk_username`(`username` ASC) USING BTREE,
                             INDEX `idx_dept_id`(`dept_id` ASC) USING BTREE,
                             INDEX `idx_status`(`status` ASC) USING BTREE,
                             INDEX `idx_mobile`(`mobile` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统用户表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_user
-- ----------------------------
INSERT INTO `sys_user` VALUES (1, 'admin', '$2y$10$O9DuwMJ.hvBtrSAhb9vJb.lJZUxQN87qUbb1qWnXyAoJiirVN7mCS', 'hacked', NULL, '251853527@qq.com', '18181818811', 1, 1, 2, '172.18.0.1', '2026-06-27 14:44:03', 45, 2, NULL, NULL, '2026-04-23 18:28:31', 1, '2026-06-27 14:55:44', NULL);
INSERT INTO `sys_user` VALUES (2, 'user', '$2y$10$mtQ5lkNaMaYpbiHYCLyGPOw5DtzG9rov/vxN8D9P1psPIjHugnpHK', 'user', NULL, NULL, NULL, 0, 1, 1, '127.0.0.1', '2026-06-23 03:11:08', 4, 0, NULL, 1, '2026-06-22 17:05:53', 1, '2026-06-24 22:59:36', NULL);

-- ----------------------------
-- Table structure for sys_user_role
-- ----------------------------
DROP TABLE IF EXISTS `sys_user_role`;
CREATE TABLE `sys_user_role`  (
                                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT '用户ID',
                                  `role_id` bigint(20) UNSIGNED NOT NULL COMMENT '角色ID',
                                  `created_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                                  PRIMARY KEY (`id`) USING BTREE,
                                  UNIQUE INDEX `uk_user_role`(`user_id` ASC, `role_id` ASC) USING BTREE,
                                  INDEX `idx_user_id`(`user_id` ASC) USING BTREE,
                                  INDEX `idx_role_id`(`role_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户角色关联表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sys_user_role
-- ----------------------------
INSERT INTO `sys_user_role` VALUES (12, 1, 1, '2026-06-23 01:48:44');
INSERT INTO `sys_user_role` VALUES (13, 2, 2, '2026-06-24 22:59:36');

-- ----------------------------
-- Table structure for wechat_config
-- ----------------------------
DROP TABLE IF EXISTS `wechat_config`;
CREATE TABLE `wechat_config`  (
                                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置名称',
                                  `app_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '应用类型:official_account/mini_program/open_platform/work/pay',
                                  `app_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'AppID / CorpID',
                                  `secret` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'AppSecret',
                                  `token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Token',
                                  `aes_key` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'EncodingAESKey',
                                  `mch_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '商户号',
                                  `pay_key` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '支付密钥',
                                  `cert_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '证书路径',
                                  `key_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '密钥路径',
                                  `extra` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '扩展配置(JSON)',
                                  `is_default` tinyint(1) NULL DEFAULT 0 COMMENT '是否默认',
                                  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态:0禁用/1启用',
                                  `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
                                  `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
                                  PRIMARY KEY (`id`) USING BTREE,
                                  INDEX `wechat_config_app_type_index`(`app_type` ASC) USING BTREE,
                                  INDEX `wechat_config_status_index`(`status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '微信配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of wechat_config
-- ----------------------------

-- ----------------------------
-- Table structure for wechat_material
-- ----------------------------
DROP TABLE IF EXISTS `wechat_material`;
CREATE TABLE `wechat_material`  (
                                    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                    `app_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'official_account' COMMENT '应用类型',
                                    `media_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '媒体ID',
                                    `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '素材类型:image/voice/video/news',
                                    `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '标题',
                                    `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
                                    `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '素材URL',
                                    `local_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '本地路径',
                                    `extra` json NULL COMMENT '扩展信息(JSON)',
                                    `sync_time` timestamp NULL DEFAULT NULL COMMENT '同步时间',
                                    `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
                                    `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
                                    PRIMARY KEY (`id`) USING BTREE,
                                    INDEX `wechat_material_media_id_index`(`media_id` ASC) USING BTREE,
                                    INDEX `wechat_material_type_index`(`type` ASC) USING BTREE,
                                    INDEX `wechat_material_app_type_index`(`app_type` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '微信素材表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of wechat_material
-- ----------------------------

-- ----------------------------
-- Table structure for wechat_menu
-- ----------------------------
DROP TABLE IF EXISTS `wechat_menu`;
CREATE TABLE `wechat_menu`  (
                                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                `app_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'official_account' COMMENT '应用类型',
                                `parent_id` bigint(20) UNSIGNED NULL DEFAULT 0 COMMENT '父菜单ID',
                                `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '菜单名称',
                                `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '菜单类型:click/view/miniprogram等',
                                `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '菜单KEY值',
                                `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '网页链接',
                                `appid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '小程序AppID',
                                `pagepath` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '小程序页面路径',
                                `sort` int(11) NULL DEFAULT 0 COMMENT '排序',
                                `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态:0禁用/1启用',
                                `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
                                `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
                                PRIMARY KEY (`id`) USING BTREE,
                                INDEX `wechat_menu_parent_id_index`(`parent_id` ASC) USING BTREE,
                                INDEX `wechat_menu_app_type_index`(`app_type` ASC) USING BTREE,
                                INDEX `wechat_menu_sort_index`(`sort` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '微信菜单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of wechat_menu
-- ----------------------------

-- ----------------------------
-- Table structure for wechat_message
-- ----------------------------
DROP TABLE IF EXISTS `wechat_message`;
CREATE TABLE `wechat_message`  (
                                   `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                   `msg_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '消息ID',
                                   `app_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'official_account' COMMENT '应用类型',
                                   `msg_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '消息类型:text/image/voice/video/event等',
                                   `event` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '事件类型:subscribe/unsubscribe/SCAN等',
                                   `from_user` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '发送方帐号(OpenID)',
                                   `to_user` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '接收方帐号',
                                   `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '消息内容(JSON)',
                                   `receive_time` timestamp NULL DEFAULT NULL COMMENT '接收时间',
                                   `send_status` tinyint(4) NULL DEFAULT 0 COMMENT '发送状态:0待发送/1成功/2失败',
                                   `send_time` timestamp NULL DEFAULT NULL COMMENT '发送时间',
                                   `send_result` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '发送结果',
                                   `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
                                   `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
                                   PRIMARY KEY (`id`) USING BTREE,
                                   INDEX `wechat_message_msg_id_index`(`msg_id` ASC) USING BTREE,
                                   INDEX `wechat_message_from_user_index`(`from_user` ASC) USING BTREE,
                                   INDEX `wechat_message_msg_type_index`(`msg_type` ASC) USING BTREE,
                                   INDEX `wechat_message_receive_time_index`(`receive_time` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '微信消息记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of wechat_message
-- ----------------------------

-- ----------------------------
-- Table structure for wechat_template
-- ----------------------------
DROP TABLE IF EXISTS `wechat_template`;
CREATE TABLE `wechat_template`  (
                                    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                    `app_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'official_account' COMMENT '应用类型',
                                    `template_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板ID',
                                    `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板标题',
                                    `primary_industry` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '主行业',
                                    `deputy_industry` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '副行业',
                                    `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板内容',
                                    `example` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '示例',
                                    `params` json NULL COMMENT '参数配置(JSON)',
                                    `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态:0禁用/1启用',
                                    `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
                                    `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
                                    PRIMARY KEY (`id`) USING BTREE,
                                    INDEX `wechat_template_template_id_index`(`template_id` ASC) USING BTREE,
                                    INDEX `wechat_template_app_type_index`(`app_type` ASC) USING BTREE,
                                    INDEX `wechat_template_status_index`(`status` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '微信模板消息表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of wechat_template
-- ----------------------------

-- ----------------------------
-- Table structure for wechat_user
-- ----------------------------
DROP TABLE IF EXISTS `wechat_user`;
CREATE TABLE `wechat_user`  (
                                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
                                `user_id` bigint(20) UNSIGNED NULL DEFAULT NULL COMMENT '绑定系统用户ID',
                                `openid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户标识',
                                `unionid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '开放平台统一ID',
                                `app_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'official_account' COMMENT '应用类型:official_account/mini_program/work',
                                `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '昵称',
                                `gender` tinyint(4) NULL DEFAULT 0 COMMENT '性别:0未知/1男/2女',
                                `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '头像',
                                `city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '城市',
                                `province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '省份',
                                `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '国家',
                                `language` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '语言',
                                `subscribe` tinyint(4) NULL DEFAULT 0 COMMENT '是否关注:0否/1是',
                                `subscribe_time` timestamp NULL DEFAULT NULL COMMENT '关注时间',
                                `subscribe_scene` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '关注来源',
                                `qr_scene` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '扫码场景值',
                                `qr_scan_time` timestamp NULL DEFAULT NULL COMMENT '扫码时间',
                                `latitude` decimal(10, 7) NULL DEFAULT NULL COMMENT '纬度',
                                `longitude` decimal(10, 7) NULL DEFAULT NULL COMMENT '经度',
                                `location_precision` decimal(10, 2) NULL DEFAULT NULL COMMENT '地理位置精度',
                                `location_time` timestamp NULL DEFAULT NULL COMMENT '位置上报时间',
                                `remark` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
                                `group_id` int(11) NULL DEFAULT NULL COMMENT '分组ID',
                                `tagid_list` json NULL COMMENT '标签ID列表',
                                `extra` json NULL COMMENT '扩展信息',
                                `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
                                `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
                                `deleted_at` timestamp NULL DEFAULT NULL COMMENT '删除时间',
                                PRIMARY KEY (`id`) USING BTREE,
                                UNIQUE INDEX `wechat_user_openid_unique`(`openid` ASC) USING BTREE,
                                UNIQUE INDEX `wechat_user_unionid_unique`(`unionid` ASC) USING BTREE,
                                INDEX `wechat_user_user_id_index`(`user_id` ASC) USING BTREE,
                                INDEX `wechat_user_subscribe_index`(`subscribe` ASC) USING BTREE,
                                INDEX `wechat_user_app_type_index`(`app_type` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '微信用户表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of wechat_user
-- ----------------------------

SET FOREIGN_KEY_CHECKS = 1;
