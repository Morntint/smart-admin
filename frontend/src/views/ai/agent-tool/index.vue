<!-- AI Agent 工具库管理页面 -->
<template>
  <div class="ai-tool-page art-full-height">
    <ArtSearchBar
      v-model="formFilters"
      :items="formItems"
      :show-expand="false"
      @reset="handleReset"
      @search="handleSearch"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader
        :loading="loading"
        v-model:columns="columnChecks"
        @refresh="refreshData"
      >
        <template #left>
          <ElButton v-auth="'ai:tool:create'" @click="handleCreate" v-ripple>新增工具</ElButton>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />

      <ToolDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :edit-data="currentRow"
        @success="handleSuccess"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { Tools } from '@element-plus/icons-vue'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import {
    fetchGetAiAgentToolList,
    fetchDeleteAiAgentTool
  } from '@/api/ai-manage'
  import ToolDialog from './tool-dialog.vue'

  defineOptions({ name: 'AiAgentTool' })

  const { hasAuth } = useAuth()

  type ToolItem = {
    id: number
    name: string
    code: string
    description?: string
    tool_type: string
    handler?: string
    status: number
    sort: number
  }

  const TOOL_TYPE_MAP: Record<string, { label: string; type: string }> = {
    function: { label: '函数', type: 'primary' },
    api: { label: 'API', type: 'success' },
    plugin: { label: '插件', type: 'warning' }
  }

  const dialogType = ref<'add' | 'edit'>('add')
  const dialogVisible = ref(false)
  const currentRow = ref<ToolItem | null>(null)

  // 搜索
  const formFilters = reactive({
    keyword: '',
    tool_type: '',
    status: ''
  })

  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: { clearable: true, placeholder: '名称/标识' }
    },
    {
      label: '类型',
      key: 'tool_type',
      type: 'select',
      props: {
        clearable: true,
        placeholder: '全部',
        options: Object.entries(TOOL_TYPE_MAP).map(([value, v]) => ({
          value,
          label: v.label
        }))
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        clearable: true,
        placeholder: '全部',
        options: [
          { value: '1', label: '正常' },
          { value: '0', label: '禁用' }
        ]
      }
    }
  ])

  const {
    columns,
    columnChecks,
    data,
    loading,
    pagination,
    getData,
    replaceSearchParams,
    refreshData,
    handleSizeChange,
    handleCurrentChange
  } = useTable<typeof fetchGetAiAgentToolList>({
    core: {
      apiFn: fetchGetAiAgentToolList,
      apiParams: { page: 1, limit: 15, keyword: '', tool_type: '', status: '' },
      immediate: false,
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'globalIndex', label: '序号', width: 70 },
        {
          prop: 'name',
          label: '工具名称',
          minWidth: 160,
          formatter: (row: ToolItem) =>
            h('div', { class: 'flex items-center gap-2' }, [
              h(resolveComponent('el-icon'), null, () => h(Tools)),
              h('span', null, row.name)
            ])
        },
        { prop: 'code', label: '标识', minWidth: 140 },
        {
          prop: 'tool_type',
          label: '类型',
          width: 90,
          align: 'center' as const,
          formatter: (row: ToolItem) => {
            const cfg = TOOL_TYPE_MAP[row.tool_type] || {
              label: row.tool_type,
              type: 'info'
            }
            return h(ElTag, { type: cfg.type as any, size: 'small' }, () => cfg.label)
          }
        },
        {
          prop: 'handler',
          label: '处理器',
          minWidth: 200,
          showOverflowTooltip: true,
          formatter: (row: ToolItem) =>
            row.handler
              ? h(
                  'code',
                  {
                    class: 'px-1.5 py-0.5 text-xs rounded bg-g-200 text-g-700'
                  },
                  row.handler
                )
              : h('span', { class: 'text-g-500' }, '-')
        },
        {
          prop: 'description',
          label: '描述',
          minWidth: 220,
          showOverflowTooltip: true,
          formatter: (row: ToolItem) => row.description || '-'
        },
        { prop: 'sort', label: '排序', width: 70, align: 'center' as const },
        {
          prop: 'status',
          label: '状态',
          width: 80,
          align: 'center' as const,
          formatter: (row: ToolItem) =>
            h(
              ElTag,
              { type: row.status === 1 ? 'success' : 'danger', size: 'small' },
              () => (row.status === 1 ? '正常' : '禁用')
            )
        },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row: ToolItem) =>
            h('div', { onClick: (e: Event) => e.stopPropagation() }, [
              hasAuth('ai:tool:update') &&
                h(resolveComponent('ArtButtonTable'), {
                  type: 'edit',
                  onClick: () => handleEdit(row)
                }),
              hasAuth('ai:tool:delete') &&
                h(resolveComponent('ArtButtonTable'), {
                  type: 'delete',
                  onClick: () => handleDelete(row)
                })
            ].filter(Boolean))
        }
      ]
    }
  })

  const handleSearch = (params: Record<string, any>) => {
    replaceSearchParams(params)
    getData()
  }

  const handleReset = () => {
    Object.assign(formFilters, { keyword: '', tool_type: '', status: '' })
    replaceSearchParams({ keyword: '', tool_type: '', status: '' })
    getData()
  }

  const handleCreate = () => {
    dialogType.value = 'add'
    currentRow.value = null
    dialogVisible.value = true
  }
  const handleEdit = (row: ToolItem) => {
    dialogType.value = 'edit'
    currentRow.value = { ...row }
    dialogVisible.value = true
  }
  const handleSuccess = () => refreshData()

  const handleDelete = (row: ToolItem) => {
    ElMessageBox.confirm(`确认删除工具"${row.name}"？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteAiAgentTool(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => ElMessage.info('已取消删除'))
  }

  onMounted(() => {
    replaceSearchParams({ keyword: '', tool_type: '', status: '' })
    getData()
  })
</script>
