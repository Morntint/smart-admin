# 贡献指南

感谢你愿意为本项目贡献代码！请遵循以下流程，让协作更顺畅。

## 提交前自查

- [ ] 代码符合 [`docs/CODE_STYLE.md`](docs/CODE_STYLE.md)
- [ ] 类型签名与 PHPDoc 完整
- [ ] 业务异常通过 `BusinessException` 抛出，未直接 `return` 错误响应
- [ ] 涉及缓存的写操作主动调用了缓存失效
- [ ] 涉及多表的写操作使用了 `$this->transaction()`
- [ ] 没有把请求级状态写到 Service 实例字段中
- [ ] 已更新 `CHANGELOG.md`

## 分支策略

- `main` / `master`  → 稳定版
- `develop`           → 集成分支
- `feature/<name>`    → 功能分支
- `fix/<name>`        → 修复分支
- `refactor/<name>`   → 重构分支

## Commit Message

```
<type>(<scope>): <subject>

<body>

<footer>
```

`type` 必须为：`feat | fix | refactor | perf | docs | test | chore | style`

示例：

```
feat(user): 增加批量导入接口

- 支持 Excel 上传
- 校验列头与重复用户名
- 写入失败行返回错误明细

Refs: #123
```

## Pull Request 模板

- **变更说明**：本次改了什么、为什么
- **影响范围**：哪些模块 / 接口受影响
- **测试方式**：列出验证步骤
- **风险评估**：是否有不兼容变更

## 报告 Bug

提 Issue 时请提供：

- 复现步骤
- 期望行为 vs 实际行为
- 环境信息（PHP 版本、操作系统、Webman 版本）
- 关键日志（脱敏后）
