-- ================================================
-- 企业通用后台管理系统 - 数据库表结构
-- 适用于 MySQL 5.7+ / MySQL 8.0
-- ================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. 用户表 sys_user
-- ----------------------------
DROP TABLE IF EXISTS `sys_user`;
CREATE TABLE `sys_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码(加密)',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `sex` tinyint unsigned DEFAULT '0' COMMENT '性别: 0=未知, 1=男, 2=女',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `dept_id` bigint unsigned DEFAULT NULL COMMENT '部门ID',
  `login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
  `login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int unsigned DEFAULT '0' COMMENT '登录次数',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建者',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新者',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_dept_id` (`dept_id`),
  KEY `idx_status` (`status`),
  KEY `idx_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统用户表';

-- ----------------------------
-- 2. 角色表 sys_role
-- ----------------------------
DROP TABLE IF EXISTS `sys_role`;
CREATE TABLE `sys_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '角色ID',
  `name` varchar(50) NOT NULL COMMENT '角色名称',
  `code` varchar(50) NOT NULL COMMENT '角色代码',
  `sort` int unsigned DEFAULT '0' COMMENT '显示顺序',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `data_scope` tinyint unsigned DEFAULT '1' COMMENT '数据范围: 1=全部, 2=本部门, 3=本部门及以下, 4=仅本人, 5=自定义',
  `data_scope_depts` varchar(500) DEFAULT NULL COMMENT '自定义数据范围部门ID列表',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建者',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新者',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统角色表';

-- ----------------------------
-- 3. 菜单表 sys_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_menu`;
CREATE TABLE `sys_menu` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '菜单ID',
  `parent_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '父菜单ID',
  `name` varchar(50) NOT NULL COMMENT '菜单名称',
  `route_name` varchar(100) DEFAULT NULL COMMENT '路由名称(PascalCase)',
  `icon` varchar(100) DEFAULT NULL COMMENT '菜单图标',
  `path` varchar(200) DEFAULT NULL COMMENT '路由地址',
  `component` varchar(255) DEFAULT NULL COMMENT '组件路径',
  `redirect` varchar(255) DEFAULT NULL COMMENT '重定向地址',
  `is_external` tinyint unsigned DEFAULT '0' COMMENT '是否外链: 0=否, 1=是',
  `is_cache` tinyint unsigned DEFAULT '0' COMMENT '是否缓存: 0=否, 1=是',
  `is_visible` tinyint unsigned DEFAULT '1' COMMENT '是否显示: 0=隐藏, 1=显示',
  `is_hide_tab` tinyint unsigned DEFAULT '0' COMMENT '是否隐藏标签: 0=否, 1=是',
  `is_iframe` tinyint unsigned DEFAULT '0' COMMENT '是否iframe: 0=否, 1=是',
  `is_full_page` tinyint unsigned DEFAULT '0' COMMENT '是否全屏页面: 0=否, 1=是',
  `fixed_tab` tinyint unsigned DEFAULT '0' COMMENT '是否固定标签: 0=否, 1=是',
  `active_path` varchar(200) DEFAULT NULL COMMENT '激活菜单路径',
  `sort` int unsigned DEFAULT '0' COMMENT '显示顺序',
  `type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '菜单类型: 1=目录, 2=菜单, 3=按钮',
  `permission` varchar(100) DEFAULT NULL COMMENT '权限标识',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建者',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新者',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统菜单表';

-- ----------------------------
-- 4. 部门表 sys_department
-- ----------------------------
DROP TABLE IF EXISTS `sys_department`;
CREATE TABLE `sys_department` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '部门ID',
  `parent_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '父部门ID',
  `name` varchar(50) NOT NULL COMMENT '部门名称',
  `leader` varchar(50) DEFAULT NULL COMMENT '负责人',
  `mobile` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `sort` int unsigned DEFAULT '0' COMMENT '显示顺序',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建者',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新者',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统部门表';

-- ----------------------------
-- 5. 用户角色关联表 sys_user_role
-- ----------------------------
DROP TABLE IF EXISTS `sys_user_role`;
CREATE TABLE `sys_user_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `role_id` bigint unsigned NOT NULL COMMENT '角色ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`user_id`, `role_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色关联表';

-- ----------------------------
-- 6. 角色菜单关联表 sys_role_menu
-- ----------------------------
DROP TABLE IF EXISTS `sys_role_menu`;
CREATE TABLE `sys_role_menu` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `role_id` bigint unsigned NOT NULL COMMENT '角色ID',
  `menu_id` bigint unsigned NOT NULL COMMENT '菜单ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_menu` (`role_id`, `menu_id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_menu_id` (`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色菜单关联表';

-- ----------------------------
-- 7. 操作日志表 sys_operation_log
-- ----------------------------
DROP TABLE IF EXISTS `sys_operation_log`;
CREATE TABLE `sys_operation_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `module` varchar(50) DEFAULT NULL COMMENT '操作模块',
  `action` varchar(50) DEFAULT NULL COMMENT '操作类型',
  `method` varchar(10) NOT NULL COMMENT '请求方法',
  `url` varchar(500) NOT NULL COMMENT '请求地址',
  `ip` varchar(50) DEFAULT NULL COMMENT '请求IP',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'User-Agent',
  `user_id` bigint unsigned DEFAULT NULL COMMENT '操作用户ID',
  `username` varchar(50) DEFAULT NULL COMMENT '操作用户名',
  `param` text COMMENT '请求参数',
  `result` text COMMENT '返回结果',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=异常, 1=正常',
  `error_msg` varchar(1000) DEFAULT NULL COMMENT '错误信息',
  `duration` int unsigned DEFAULT NULL COMMENT '执行时长(ms)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_action` (`action`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统操作日志表';

-- ----------------------------
-- 8. 系统配置表 sys_config
-- ----------------------------
DROP TABLE IF EXISTS `sys_config`;
CREATE TABLE `sys_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `name` varchar(100) NOT NULL COMMENT '配置名称',
  `key` varchar(100) NOT NULL COMMENT '配置键名',
  `value` text COMMENT '配置值',
  `type` varchar(20) NOT NULL DEFAULT 'string' COMMENT '配置类型: string, number, boolean, json',
  `group` varchar(50) DEFAULT 'default' COMMENT '配置分组',
  `options` varchar(500) DEFAULT NULL COMMENT '可选值(JSON格式)',
  `sort` int unsigned DEFAULT '0' COMMENT '显示顺序',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建者',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新者',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`),
  KEY `idx_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ----------------------------
-- 9. 字典类型表 sys_dict
-- ----------------------------
DROP TABLE IF EXISTS `sys_dict`;
CREATE TABLE `sys_dict` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '字典ID',
  `name` varchar(100) NOT NULL COMMENT '字典名称',
  `code` varchar(100) NOT NULL COMMENT '字典编码',
  `type` varchar(20) NOT NULL DEFAULT 'string' COMMENT '字典类型',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建者',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新者',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典类型表';

-- ----------------------------
-- 10. 字典数据表 sys_dict_data
-- ----------------------------
DROP TABLE IF EXISTS `sys_dict_data`;
CREATE TABLE `sys_dict_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '字典数据ID',
  `dict_id` bigint unsigned NOT NULL COMMENT '字典ID',
  `label` varchar(100) NOT NULL COMMENT '字典标签',
  `value` varchar(255) NOT NULL COMMENT '字典键值',
  `type` varchar(20) DEFAULT NULL COMMENT '样式类型',
  `sort` int unsigned DEFAULT '0' COMMENT '显示顺序',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建者',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新者',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dict_value` (`dict_id`, `value`),
  KEY `idx_dict_id` (`dict_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典数据表';

-- ----------------------------
-- 11. 文件管理表 sys_file
-- ----------------------------
DROP TABLE IF EXISTS `sys_file`;
CREATE TABLE `sys_file` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `name` varchar(255) NOT NULL COMMENT '文件名称',
  `original_name` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL COMMENT '文件路径',
  `file_url` varchar(500) DEFAULT NULL COMMENT '访问URL',
  `file_size` bigint unsigned NOT NULL COMMENT '文件大小(字节)',
  `file_ext` varchar(50) DEFAULT NULL COMMENT '文件扩展名',
  `file_type` varchar(100) DEFAULT NULL COMMENT '文件MIME类型',
  `storage_type` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储类型: local, oss, cos, s3',
  `upload_ip` varchar(50) DEFAULT NULL COMMENT '上传IP',
  `upload_user_id` bigint unsigned DEFAULT NULL COMMENT '上传用户ID',
  `download_count` int unsigned DEFAULT '0' COMMENT '下载次数',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=正常',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_file_ext` (`file_ext`),
  KEY `idx_upload_user_id` (`upload_user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件管理表';

-- ----------------------------
-- 12. 登录日志表 sys_login_log
-- ----------------------------
DROP TABLE IF EXISTS `sys_login_log`;
CREATE TABLE `sys_login_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `user_id` bigint unsigned DEFAULT NULL COMMENT '用户ID',
  `username` varchar(50) DEFAULT NULL COMMENT '用户名',
  `login_type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '登录类型: 1=登录, 2=登出',
  `ip` varchar(50) DEFAULT NULL COMMENT '登录IP',
  `ip_location` varchar(255) DEFAULT NULL COMMENT 'IP所在地',
  `user_agent` varchar(500) DEFAULT NULL COMMENT 'User-Agent',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=失败, 1=成功',
  `msg` varchar(255) DEFAULT NULL COMMENT '提示消息',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '登录时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_username` (`username`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统登录日志表';

-- ----------------------------
-- 初始管理员数据 (密码: admin123)
-- ----------------------------
-- 注：此 hash 必须与 make_password('admin123') 一致；旧值 $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
--     实际上是 Laravel 默认 "password" 的 hash，会导致 admin/admin123 登录失败
INSERT INTO `sys_user` (`id`, `username`, `password`, `nickname`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$O9DuwMJ.hvBtrSAhb9vJb.lJZUxQN87qUbb1qWnXyAoJiirVN7mCS', '系统管理员', 1, NOW());

INSERT INTO `sys_department` (`id`, `name`, `sort`, `status`) VALUES
(1, '总公司', 0, 1);

INSERT INTO `sys_role` (`id`, `name`, `code`, `sort`, `status`) VALUES
(1, '超级管理员', 'super_admin', 1, 1),
(2, '普通管理员', 'admin', 2, 1);

INSERT INTO `sys_user_role` (`user_id`, `role_id`) VALUES
(1, 1);

INSERT INTO `sys_menu` (`id`, `parent_id`, `name`, `route_name`, `icon`, `path`, `component`, `redirect`, `type`, `is_visible`, `is_cache`, `sort`, `permission`, `status`) VALUES
-- 一级菜单（目录）
(1, 0, '系统管理', 'System', 'Setting', '/system', '', '/system/user', 1, 1, 0, 100, '', 1),
-- 系统管理子菜单
(2, 1, '用户管理', 'User', 'User', '/system/user', '/system/user', NULL, 2, 1, 0, 1, '', 1),
(3, 1, '角色管理', 'Role', 'Role', '/system/role', '/system/role', NULL, 2, 1, 0, 2, '', 1),
(4, 1, '菜单管理', 'Menu', 'Tree', '/system/menu', '/system/menu', NULL, 2, 1, 0, 3, '', 1),
(5, 1, '部门管理', 'Dept', 'Office', '/system/dept', '/system/dept', NULL, 2, 1, 0, 4, '', 1),
(101, 1, '系统配置', 'Config', 'Config', '/system/config', '/system/config', NULL, 2, 1, 0, 5, '', 1),
(102, 1, '字典管理', 'Dict', 'Book', '/system/dict', '/system/dict', NULL, 2, 1, 0, 6, '', 1),
(103, 1, '操作日志', 'OperationLog', 'Document', '/system/log', '/system/log', NULL, 2, 0, 0, 7, '', 1),
(104, 1, '登录日志', 'LoginLog', 'LoginLog', '/system/loginLog', '/system/loginLog', NULL, 2, 0, 0, 8, '', 1),
(105, 1, '系统日志', 'SystemLog', 'Files', '/system/systemLog', '/system/systemLog', NULL, 2, 1, 0, 9, '', 1),
-- 用户管理按钮
(201, 2, '用户查询', '', '', '', '', 3, 1, 0, 1, 'system:user:list', 1),
(202, 2, '用户新增', '', '', '', '', 3, 1, 0, 2, 'system:user:add', 1),
(203, 2, '用户编辑', '', '', '', '', 3, 1, 0, 3, 'system:user:edit', 1),
(204, 2, '用户删除', '', '', '', '', 3, 1, 0, 4, 'system:user:del', 1),
(205, 2, '重置密码', '', '', '', '', 3, 1, 0, 5, 'system:user:resetPwd', 1),
-- 角色管理按钮
(301, 3, '角色查询', '', '', '', '', 3, 1, 0, 1, 'system:role:list', 1),
(302, 3, '角色新增', '', '', '', '', 3, 1, 0, 2, 'system:role:add', 1),
(303, 3, '角色编辑', '', '', '', '', 3, 1, 0, 3, 'system:role:edit', 1),
(304, 3, '角色删除', '', '', '', '', 3, 1, 0, 4, 'system:role:del', 1),
(305, 3, '分配权限', '', '', '', '', 3, 1, 0, 5, 'system:role:assign', 1),
-- 菜单管理按钮
(401, 4, '菜单查询', '', '', '', '', 3, 1, 0, 1, 'system:menu:list', 1),
(402, 4, '菜单新增', '', '', '', '', 3, 1, 0, 2, 'system:menu:add', 1),
(403, 4, '菜单编辑', '', '', '', '', 3, 1, 0, 3, 'system:menu:edit', 1),
(404, 4, '菜单删除', '', '', '', '', 3, 1, 0, 4, 'system:menu:del', 1),
-- 部门管理按钮
(501, 5, '部门查询', '', '', '', '', 3, 1, 0, 1, 'system:dept:list', 1),
(502, 5, '部门新增', '', '', '', '', 3, 1, 0, 2, 'system:dept:add', 1),
(503, 5, '部门编辑', '', '', '', '', 3, 1, 0, 3, 'system:dept:edit', 1),
(504, 5, '部门删除', '', '', '', '', 3, 1, 0, 4, 'system:dept:del', 1),
-- 系统配置按钮
(1011, 101, '配置查询', '', '', '', '', 3, 1, 0, 1, 'system:config:list', 1),
(1012, 101, '配置新增', '', '', '', '', 3, 1, 0, 2, 'system:config:add', 1),
(1013, 101, '配置编辑', '', '', '', '', 3, 1, 0, 3, 'system:config:edit', 1),
(1014, 101, '配置删除', '', '', '', '', 3, 1, 0, 4, 'system:config:del', 1),
-- 字典管理按钮
(1021, 102, '字典查询', '', '', '', '', 3, 1, 0, 1, 'system:dict:list', 1),
(1022, 102, '字典新增', '', '', '', '', 3, 1, 0, 2, 'system:dict:add', 1),
(1023, 102, '字典编辑', '', '', '', '', 3, 1, 0, 3, 'system:dict:edit', 1),
(1024, 102, '字典删除', '', '', '', '', 3, 1, 0, 4, 'system:dict:del', 1),
-- 操作日志按钮
(1031, 103, '操作日志查询', '', '', '', '', 3, 1, 0, 1, 'system:log:operationList', 1),
(1032, 103, '操作日志删除', '', '', '', '', 3, 1, 0, 2, 'system:log:operationDel', 1),
-- 登录日志按钮
(1041, 104, '登录日志查询', '', '', '', '', 3, 1, 0, 1, 'system:log:loginList', 1),
(1042, 104, '登录日志删除', '', '', '', '', 3, 1, 0, 2, 'system:log:loginDel', 1);

INSERT INTO `sys_role_menu` (`role_id`, `menu_id`) VALUES
-- 超级管理员拥有所有菜单权限
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5),
(1, 101), (1, 102), (1, 103), (1, 104), (1, 105),
(1, 201), (1, 202), (1, 203), (1, 204), (1, 205),
(1, 301), (1, 302), (1, 303), (1, 304), (1, 305),
(1, 401), (1, 402), (1, 403), (1, 404),
(1, 501), (1, 502), (1, 503), (1, 504),
(1, 1011), (1, 1012), (1, 1013), (1, 1014),
(1, 1021), (1, 1022), (1, 1023), (1, 1024),
(1, 1031), (1, 1032), (1, 1041), (1, 1042);

-- 字典初始数据
INSERT INTO `sys_dict` (`id`, `name`, `code`, `type`, `status`) VALUES
(1, '用户性别', 'sys_user_sex', 'string', 1),
(2, '用户状态', 'sys_user_status', 'string', 1),
(3, '菜单状态', 'sys_menu_status', 'string', 1),
(4, '角色状态', 'sys_role_status', 'string', 1),
(5, '部门状态', 'sys_dept_status', 'string', 1),
(6, '是否可见', 'sys_yes_no', 'string', 1),
(7, '是否外链', 'sys_external', 'string', 1),
(8, '菜单类型', 'sys_menu_type', 'string', 1);

INSERT INTO `sys_dict_data` (`dict_id`, `label`, `value`, `sort`, `status`) VALUES
-- 用户性别
(1, '未知', '0', 0, 1),
(1, '男', '1', 1, 1),
(1, '女', '2', 2, 1),
-- 用户状态
(2, '正常', '1', 1, 1),
(2, '禁用', '0', 2, 1),
-- 菜单状态
(3, '正常', '1', 1, 1),
(3, '禁用', '0', 2, 1),
-- 角色状态
(4, '正常', '1', 1, 1),
(4, '禁用', '0', 2, 1),
-- 部门状态
(5, '正常', '1', 1, 1),
(5, '禁用', '0', 2, 1),
-- 是否可见
(6, '否', '0', 1, 1),
(6, '是', '1', 2, 1),
-- 是否外链
(7, '否', '0', 1, 1),
(7, '是', '1', 2, 1),
-- 菜单类型
(8, '目录', '1', 1, 1),
(8, '菜单', '2', 2, 1),
(8, '按钮', '3', 3, 1);

-- 系统配置初始数据
INSERT INTO `sys_config` (`name`, `key`, `value`, `type`, `group`, `sort`, `remark`) VALUES
('系统名称', 'sys_name', '企业后台管理系统', 'string', 'basic', 1, '系统名称'),
('系统Logo', 'sys_logo', '/static/admin/images/logo.png', 'string', 'basic', 2, '系统Logo'),
('系统版本', 'sys_version', '1.0.0', 'string', 'basic', 3, '系统版本'),
('版权信息', 'sys_copyright', '© 2024 Company. All rights reserved.', 'string', 'basic', 4, '版权信息'),
('文件上传大小限制', 'upload_file_size', '10', 'number', 'upload', 1, '单位: MB'),
('图片上传大小限制', 'upload_image_size', '5', 'number', 'upload', 2, '单位: MB'),
('允许上传的文件类型', 'upload_allowed_ext', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,zip', 'string', 'upload', 3, '文件扩展名');

SET FOREIGN_KEY_CHECKS = 1;
