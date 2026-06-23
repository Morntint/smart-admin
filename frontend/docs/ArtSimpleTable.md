# ArtSimpleTable 简易表格组件

> 快速开发场景：仅需配置 `apiFn` + `columns` + `searchItems` 三项，即可完成「搜索 + 工具栏 + 表格 + 分页」完整页面。

## 1. 适用场景

- ✅ 标准 CRUD 列表页（用户管理、角色管理、订单列表等）
- ✅ 简单搜索 + 表格展示，无需复杂工具栏
- ✅ 想要快速完成、不希望写一堆模板代码的场景

不适合：
- ❌ 需要树形表格、虚拟滚动等复杂表格功能
- ❌ 需要复杂的工具栏（多个分组按钮、过滤器等）
- ❌ 需要自定义表格头部/底部的场景

## 2. 与传统写法对比

| 维度 | 传统写法 | ArtSimpleTable |
|------|----------|----------------|
| 文件数 | 1 page + 1 search component | 只需 1 page |
| 模板行数 | ~80 行 | ~30 行 |
| useTable 配置 | 手动配置 columnsFactory | 自动接管 |
| 搜索栏 | 单独写一个 search 组件 | 通过 searchItems 配置 |
| 数据刷新 | 手动调 refreshData | 通过 ref.refresh() |

## 3. Props

| 参数 | 说明 | 类型 | 默认值 |
|------|------|------|--------|
| `apiFn` | API 请求函数，返回 `{ list, total, page, limit }` | `Function` | 必填 |
| `columns` | 表格列配置 | `ColumnOption[]` | 必填 |
| `searchItems` | 搜索表单项配置（不传则不显示搜索栏） | `SimpleTableSearchItem[]` | `[]` |
| `defaultParams` | 默认请求参数 | `Record<string, any>` | `{}` |
| `paginationKey` | 分页字段映射 | `{ current?, size? }` | `{ current: 'page', size: 'limit' }` |
| `pageSize` | 默认每页条数 | `number` | `20` |
| `immediate` | 是否挂载时立即加载 | `boolean` | `true` |

## 4. Events

| 事件 | 说明 | 回调参数 |
|------|------|----------|
| `selection-change` | 选中行变化 | `(rows: T[]) => void` |
| `row-click` | 行点击 | `(row: T) => void` |
| `refresh` | 数据刷新完成 | `() => void` |

## 5. Slots

| 插槽 | 说明 |
|------|------|
| `toolbar` | 工具栏左侧按钮（如新增、批量删除） |
| `column-${prop}` | 自定义列渲染（与 useTable 一致） |

## 6. Expose

| 方法/属性 | 说明 |
|-----------|------|
| `refresh()` | 手动刷新当前页 |
| `data` | 当前表格数据（响应式） |
| `searchForm` | 当前搜索表单（响应式） |
| `pagination` | 当前分页状态（响应式） |

## 7. 完整示例

```vue
<template>
  <div class="user-page art-full-height">
    <ArtSimpleTable
      ref="tableRef"
      :api-fn="fetchGetUserList"
      :columns="columns"
      :search-items="searchItems"
      :default-params="{ status: 1 }"
      @selection-change="onSelectionChange"
    >
      <template #toolbar>
        <ElButton type="primary" @click="onAdd">新增用户</ElButton>
        <ElButton type="danger" :disabled="!selected.length" @click="onBatchDelete">
          批量删除
        </ElButton>
      </template>

      <!-- 自定义状态列 -->
      <template #status="{ row }">
        <ElTag :type="row.status === 1 ? 'success' : 'danger'">
          {{ row.status === 1 ? '启用' : '禁用' }}
        </ElTag>
      </template>

      <!-- 自定义操作列 -->
      <template #operation="{ row }">
        <ArtButtonTable type="edit" @click="onEdit(row)" />
        <ArtButtonTable type="delete" @click="onDelete(row)" />
      </template>
    </ArtSimpleTable>
  </div>
</template>

<script setup lang="ts">
  import { fetchGetUserList } from '@/api/system-manage'

  // 搜索项配置
  const searchItems = [
    { key: 'keyword', label: '关键词', type: 'input', placeholder: '搜索用户名/邮箱' },
    {
      key: 'status',
      label: '状态',
      type: 'select',
      props: {
        options: [
          { label: '启用', value: 1 },
          { label: '禁用', value: 0 }
        ]
      }
    },
    {
      key: 'daterange',
      label: '创建日期',
      type: 'daterange',
      props: { type: 'daterange', valueFormat: 'YYYY-MM-DD' }
    }
  ]

  // 列配置
  const columns = [
    { type: 'selection' },
    { type: 'globalIndex', label: '序号' },
    { prop: 'username', label: '用户名' },
    { prop: 'email', label: '邮箱' },
    { prop: 'status', label: '状态' },
    { prop: 'created_at', label: '创建时间', sortable: true },
    { prop: 'operation', label: '操作', width: 180, fixed: 'right' }
  ]

  const tableRef = ref()
  const selected = ref<any[]>([])

  const onSelectionChange = (rows: any[]) => {
    selected.value = rows
  }

  const onAdd = () => {
    /* ... */
  }
  const onBatchDelete = () => {
    /* ... */
    tableRef.value?.refresh()
  }
  const onEdit = (row: any) => {
    /* ... */
  }
  const onDelete = (row: any) => {
    /* ... */
    tableRef.value?.refresh()
  }
</script>
```

## 8. 列配置（ColumnOption）

与 `useTable` 的 `columnsFactory` 完全一致，支持：
- `type: 'selection'` - 多选列
- `type: 'globalIndex'` - 全局序号列
- `prop` - 字段名
- `label` - 表头
- `width` / `minWidth` - 宽度
- `sortable` - 是否可排序
- `formatter` - 自定义渲染
- `fixed` - 固定列
- `showOverflowTooltip` - 文本溢出提示

## 9. 搜索项配置（SimpleTableSearchItem）

基于 `ArtSearchBar` 的 `SearchFormItem`，支持的类型：
- `input` - 输入框
- `select` - 下拉选择
- `daterange` / `datetimerange` - 日期范围
- `number` - 数字输入
- `switch` - 开关
- `checkboxgroup` / `radiogroup` - 选择组
- 等等（参考 ArtSearchBar 文档）

## 10. 注意事项

1. **后端响应格式**：必须返回 `{ list, total, page, limit }` 格式，如不符合请用 `defaultParams` 配合后端约定字段
2. **daterange 拆分**：简易版不自动拆分 daterange 为 start_date/end_date，如需拆分请在 `handleSearch` 中处理，或在 searchItems 的 `props.valueFormat` 中约束
3. **缓存策略**：默认不开启缓存，如需开启在 useTable 中扩展
4. **复杂场景**：如需更复杂的功能（行内编辑、拖拽排序等），请使用传统 `useTable` + `ArtTable` 组合
