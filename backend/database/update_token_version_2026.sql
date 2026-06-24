-- ================================================
-- 用户表新增 token_version 字段
-- 执行时间: 2026-06-24
-- 说明: 引入 JWT 主动失效机制。改密 / 重置密码 / 禁用 / 删除 / 登出 时自增本字段，
--       AuthMiddleware 校验 Token 内的 tv 与库内 token_version 是否一致，
--       不一致即视为失效（无需逐 Token 拉黑），实现「改密即下线」「禁用即踢出」。
-- 兼容: 已签发的旧 Token 不带 tv，按 tv=0 处理，存量用户 token_version 默认为 0，
--       因此本次升级不会强制踢出在线用户；如需全员重新登录，可执行下方可选语句。
-- ================================================

ALTER TABLE `sys_user`
    ADD COLUMN `token_version` int unsigned NOT NULL DEFAULT '0'
    COMMENT 'Token版本号: 改密/重置/禁用/登出时自增, 使旧Token失效'
    AFTER `login_count`;

-- 可选：升级后强制全部用户重新登录（按需执行）
-- UPDATE `sys_user` SET `token_version` = `token_version` + 1;
