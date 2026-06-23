<!--
  ArtSimpleTable 使用示例
  对比传统方式：原本需要 100+ 行（useTable + 搜索组件 + 列定义 + 模板）
  现在：只需配置 columns + searchItems + apiFn 即可
-->
<template>
  <div class="simple-table-demo art-full-height">
    <div class="demo-tip art-card-xs">
      <ArtSvgIcon icon="ri:lightbulb-line" :size="16" />
      <span>本页面演示 ArtSimpleTable 的用法。只需配置 columns、searchItems、apiFn 三项即可完成「搜索 + 表格 + 分页」完整页面。</span>
    </div>

    <ArtSimpleTable
      ref="tableRef"
      :api-fn="fetchDemoList"
      :columns="columns"
      :search-items="searchItems"
      :default-params="{ status: 1 }"
      :page-size="10"
      @selection-change="onSelectionChange"
    >
      <template #toolbar>
        <ElButton type="primary" @click="onAdd" v-ripple>
          <ArtSvgIcon icon="ri:add-line" :size="14" />
          <span>新增</span>
        </ElButton>
        <ElButton type="danger" :disabled="!selectedRows.length" @click="onBatchDelete" v-ripple>
          <ArtSvgIcon icon="ri:delete-bin-line" :size="14" />
          <span>批量删除 {{ selectedRows.length ? `(${selectedRows.length})` : '' }}</span>
        </ElButton>
      </template>

      <!-- 自定义列插槽：操作列 -->
      <template #operation="{ row }">
        <ArtButtonTable type="view" @click="onView(row)" />
        <ArtButtonTable type="edit" @click="onEdit(row)" />
        <ArtButtonTable type="delete" @click="onDelete(row)" />
      </template>
    </ArtSimpleTable>
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { ElMessage, ElMessageBox } from 'element-plus'

  defineOptions({ name: 'SimpleTableExample' })

  /** 表格 ref，用于调用 refresh() 等方法 */
  const tableRef = ref()

  /** 选中的行 */
  const selectedRows = ref<any[]>([])

  /**
   * 搜索项配置
   * 支持：input / select / daterange / datetime 等
   */
  const searchItems = [
    {
      key: 'keyword',
      label: '关键词',
      type: 'input',
      placeholder: '搜索名称或邮箱',
      clearable: true
    },
    {
      key: 'status',
      label: '状态',
      type: 'select',
      props: {
        placeholder: '请选择状态',
        clearable: true,
        options: [
          { label: '启用', value: 1 },
          { label: '禁用', value: 0 }
        ]
      }
    },
    {
      key: 'department',
      label: '部门',
      type: 'select',
      props: {
        placeholder: '请选择部门',
        clearable: true,
        options: [
          { label: '研发部', value: 'dev' },
          { label: '产品部', value: 'pm' },
          { label: '设计部', value: 'design' }
        ]
      }
    },
    {
      key: 'daterange',
      label: '创建日期',
      type: 'daterange',
      props: {
        type: 'daterange',
        valueFormat: 'YYYY-MM-DD',
        startPlaceholder: '开始日期',
        endPlaceholder: '结束日期'
      }
    }
  ]

  /**
   * 列配置
   * 与 useTable 的 columnsFactory 一致
   */
  const columns = [
    { type: 'selection', width: 50 },
    { type: 'globalIndex', label: '序号', width: 70 },
    { prop: 'name', label: '姓名', width: 120 },
    { prop: 'email', label: '邮箱', minWidth: 200, showOverflowTooltip: true },
    {
      prop: 'status',
      label: '状态',
      width: 100,
      formatter: (row: any) =>
        h(
          ElTag,
          { type: row.status === 1 ? 'success' : 'danger', size: 'small' },
          () => (row.status === 1 ? '启用' : '禁用')
        )
    },
    { prop: 'department_name', label: '部门', width: 120 },
    { prop: 'created_at', label: '创建时间', width: 180, sortable: true },
    { prop: 'operation', label: '操作', width: 180, fixed: 'right' }
  ]

  /**
   * 模拟 API 请求
   * 真实场景直接传入你的 API 函数即可
   */
  const fetchDemoList = (params: any) => {
    return new Promise<{ list: any[]; total: number; page: number; limit: number }>((resolve) => {
      setTimeout(() => {
        const { page = 1, limit = 10, keyword = '', status, department } = params
        const allData = Array.from({ length: 87 }, (_, i) => ({
          id: i + 1,
          name: `用户${i + 1}`,
          email: `user${i + 1}@example.com`,
          status: i % 3 === 0 ? 0 : 1,
          department_name: ['研发部', '产品部', '设计部'][i % 3],
          created_at: `2026-06-${String((i % 30) + 1).padStart(2, '0')} 10:00:00`
        }))

        const filtered = allData.filter((item) => {
          if (keyword && !item.name.includes(keyword) && !item.email.includes(keyword)) return false
          if (status !== undefined && status !== '' && item.status !== status) return false
          if (department && item.department_name !== department) return false
          return true
        })

        const start = (page - 1) * limit
        const list = filtered.slice(start, start + limit)
        resolve({ list, total: filtered.length, page, limit })
      }, 300)
    })
  }

  /** 选中行变化 */
  const onSelectionChange = (rows: any[]) => {
    selectedRows.value = rows
  }

  /** 新增 */
  const onAdd = () => {
    ElMessage.info('点击了新增')
  }

  /** 批量删除 */
  const onBatchDelete = () => {
    ElMessageBox.confirm(`确定要删除选中的 ${selectedRows.value.length} 条数据吗？`, '提示', {
      type: 'warning'
    })
      .then(() => {
        ElMessage.success('删除成功')
        tableRef.value?.refresh()
      })
      .catch(() => {})
  }

  const onView = (row: any) => ElMessage.info(`查看：${row.name}`)
  const onEdit = (row: any) => ElMessage.info(`编辑：${row.name}`)
  const onDelete = (row: any) => {
    ElMessageBox.confirm(`确定要删除「${row.name}」吗？`, '提示', { type: 'warning' })
      .then(() => {
        ElMessage.success('删除成功')
        tableRef.value?.refresh()
      })
      .catch(() => {})
  }
</script>

<style scoped lang="scss">
  .simple-table-demo {
    display: flex;
    flex-direction: column;
  }

  .demo-tip {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    margin-bottom: 12px;
    font-size: 13px;
    color: var(--el-color-primary);
    background-color: var(--el-color-primary-light-9);
    border-radius: 6px;
  }
</style>
