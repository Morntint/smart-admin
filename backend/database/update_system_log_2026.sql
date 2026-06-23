-- ================================================
-- 系统日志菜单新增脚本
-- 执行时间: 2026-06-22
-- 说明: 将「操作日志」与「登录日志」合并为「系统日志」选项卡页面
-- ================================================

-- 1. 隐藏原「操作日志」和「登录日志」菜单（保留路由以兼容直接访问）
UPDATE `sys_menu`
SET `is_visible` = 0
WHERE `id` IN (103, 104);

-- 2. 新增「系统日志」菜单
INSERT INTO `sys_menu` (
    `id`, `type`, `name`, `route_name`, `icon`, `path`, `component`,
    `redirect`, `parent_id`, `status`, `is_visible`, `sort`,
    `permission`, `is_external`, `remark`
) VALUES (
    105, 1, '系统日志', 'SystemLog', 'Files', '/system/systemLog',
    '/system/systemLog', NULL, 2, 1, 1, 9, '', 0, '系统日志-操作日志与登录日志'
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `route_name` = VALUES(`route_name`),
    `icon` = VALUES(`icon`),
    `path` = VALUES(`path`),
    `component` = VALUES(`component`),
    `is_visible` = VALUES(`is_visible`),
    `sort` = VALUES(`sort`),
    `remark` = VALUES(`remark`);

-- 3. 给超级管理员分配新菜单权限
INSERT IGNORE INTO `sys_role_menu` (`role_id`, `menu_id`)
SELECT r.id, 105
FROM `sys_role` r
WHERE r.code = 'R_SUPER';
