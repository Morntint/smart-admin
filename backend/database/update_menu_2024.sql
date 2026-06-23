-- ================================================
-- 菜单表字段扩展（适配前端路由结构）
-- 执行时间: 2024
-- ================================================

-- 1. 添加前端路由需要的新字段
ALTER TABLE `sys_menu`
ADD COLUMN `is_hide_tab` TINYINT UNSIGNED DEFAULT 0 COMMENT '是否隐藏标签: 0=否, 1=是' AFTER `is_visible`,
ADD COLUMN `is_iframe` TINYINT UNSIGNED DEFAULT 0 COMMENT '是否iframe: 0=否, 1=是' AFTER `is_hide_tab`,
ADD COLUMN `is_full_page` TINYINT UNSIGNED DEFAULT 0 COMMENT '是否全屏页面: 0=否, 1=是' AFTER `is_iframe`,
ADD COLUMN `fixed_tab` TINYINT UNSIGNED DEFAULT 0 COMMENT '是否固定标签: 0=否, 1=是' AFTER `is_full_page`,
ADD COLUMN `active_path` VARCHAR(200) DEFAULT NULL COMMENT '激活菜单路径' AFTER `fixed_tab`,
ADD COLUMN `route_name` VARCHAR(100) DEFAULT NULL COMMENT '路由名称(PascalCase)' AFTER `name`;

-- 2. 更新现有数据的路由名称（按path转换为PascalCase）
UPDATE `sys_menu` SET `route_name` = 'System' WHERE `path` = '/system' AND `id` = 1;
UPDATE `sys_menu` SET `route_name` = 'User' WHERE `path` = '/system/user' AND `id` = 2;
UPDATE `sys_menu` SET `route_name` = 'Role' WHERE `path` = '/system/role' AND `id` = 3;
UPDATE `sys_menu` SET `route_name` = 'Menu' WHERE `path` = '/system/menu' AND `id` = 4;
UPDATE `sys_menu` SET `route_name` = 'Dept' WHERE `path` = '/system/dept' AND `id` = 5;
UPDATE `sys_menu` SET `route_name` = 'Config' WHERE `path` = '/system/config' AND `id` = 101;
UPDATE `sys_menu` SET `route_name` = 'Dict' WHERE `path` = '/system/dict' AND `id` = 102;
UPDATE `sys_menu` SET `route_name` = 'OperationLog' WHERE `path` = '/system/log' AND `id` = 103;
UPDATE `sys_menu` SET `route_name` = 'LoginLog' WHERE `path` = '/system/loginLog' AND `id` = 104;

-- 3. 更新组件路径（去掉目录的 Layout 组件，由前端自动处理）
UPDATE `sys_menu` SET `component` = '' WHERE `type` = 1;
UPDATE `sys_menu` SET `component` = '/system/user' WHERE `path` = '/system/user';
UPDATE `sys_menu` SET `component` = '/system/role' WHERE `path` = '/system/role';
UPDATE `sys_menu` SET `component` = '/system/menu' WHERE `path` = '/system/menu';
UPDATE `sys_menu` SET `component` = '/system/dept' WHERE `path` = '/system/dept';
UPDATE `sys_menu` SET `component` = '/system/config' WHERE `path` = '/system/config';
UPDATE `sys_menu` SET `component` = '/system/dict' WHERE `path` = '/system/dict';
UPDATE `sys_menu` SET `component` = '/system/log' WHERE `path` = '/system/log';
UPDATE `sys_menu` SET `component` = '/system/loginLog' WHERE `path` = '/system/loginLog';

-- 4. 更新系统管理的重定向
UPDATE `sys_menu` SET `redirect` = '/system/user' WHERE `id` = 1;
