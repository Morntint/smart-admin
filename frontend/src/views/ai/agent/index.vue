<!-- AI Agent 管理页面 -->
<template>
  <div class="ai-agent-page art-full-height">
    <!-- 搜索栏 -->
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
          <ElButton v-auth="'ai:agent:create'" @click="handleCreate" v-ripple>新增 Agent</ElButton>
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

      <AgentDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :edit-data="currentRow"
        @success="handleSuccess"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { ElMessageBox, ElMessage, ElTag, ElIcon } from 'element-plus'
  import { Cpu } from '@element-plus/icons-vue'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import { fetchGetAiAgentList, fetchDeleteAiAgent } from '@/api/ai-manage'
  import AgentDialog from './agent-dialog.vue'

  defineOptions({ name: 'AiAgent' })

  const { hasAuth } = useAuth()

  type AgentItem = {
    id: number
    name: string
    code: string
    icon?: string
    model?: { name: string; model_name: string } | null
    is_public: number
    is_streaming: number
    status: number
    description?: string
  }

  const dialogType = ref<'add' | 'edit'>('add')
  const dialogVisible = ref(false)
  const currentRow = ref<AgentItem | null>(null)

  // 搜索表单
  const formFilters = reactive({
    keyword: '',
    status: ''
  })

  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: { clearable: true, placeholder: '名称/标识/描述' }
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
  } = useTable<typeof fetchGetAiAgentList>({
    core: {
      apiFn: fetchGetAiAgentList,
      apiParams: { page: 1, limit: 15, keyword: '', status: '' },
      immediate: false,
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'globalIndex', label: '序号', width: 70 },
        {
          prop: 'name',
          label: '名称',
          minWidth: 160,
          formatter: (row: AgentItem) =>
            h('div', { class: 'flex items-center gap-2' }, [
              h(ElIcon, null, () => h(Cpu)),
              h('span', null, row.name)
            ])
        },
        { prop: 'code', label: '标识', minWidth: 140 },
        {
          prop: 'model',
          label: '关联模型',
          minWidth: 160,
          formatter: (row: AgentItem) =>
            row.model
              ? h(ElTag, { type: 'info', size: 'small' }, () => row.model!.name)
              : h('span', { class: 'text-g-500' }, '未设置')
        },
        {
          prop: 'is_public',
          label: '公开',
          width: 70,
          align: 'center' as const,
          formatter: (row: AgentItem) =>
            h(
              ElTag,
              { type: row.is_public ? 'success' : 'info', size: 'small' },
              () => (row.is_public ? '是' : '否')
            )
        },
        {
          prop: 'is_streaming',
          label: '流式',
          width: 80,
          align: 'center' as const,
          formatter: (row: AgentItem) =>
            h(
              ElTag,
              { type: row.is_streaming ? 'success' : 'info', size: 'small' },
              () => (row.is_streaming ? 'SSE' : '同步')
            )
        },
        {
          prop: 'status',
          label: '状态',
          width: 80,
          align: 'center' as const,
          formatter: (row: AgentItem) =>
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
          formatter: (row: AgentItem) =>
            h('div', { onClick: (e: Event) => e.stopPropagation() }, [
              hasAuth('ai:agent:update') &&
                h(resolveComponent('ArtButtonTable'), {
                  type: 'edit',
                  onClick: () => handleEdit(row)
                }),
              hasAuth('ai:agent:delete') &&
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
    Object.assign(formFilters, { keyword: '', status: '' })
    replaceSearchParams({ keyword: '', status: '' })
    getData()
  }

  const handleCreate = () => {
    dialogType.value = 'add'
    currentRow.value = null
    dialogVisible.value = true
  }
  const handleEdit = (row: AgentItem) => {
    dialogType.value = 'edit'
    currentRow.value = { ...row }
    dialogVisible.value = true
  }
  const handleSuccess = () => refreshData()

  const handleDelete = (row: AgentItem) => {
    ElMessageBox.confirm(`确认删除 Agent"${row.name}"？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteAiAgent(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => ElMessage.info('已取消删除'))
  }

  onMounted(() => {
    replaceSearchParams({ keyword: '', status: '' })
    getData()
  })
</script>
