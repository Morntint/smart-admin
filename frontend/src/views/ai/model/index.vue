<!-- AI 模型管理页面 -->
<template>
  <div class="ai-model-page art-full-height">
    <!-- 搜索栏 -->
    <ArtSearchBar
      v-model="formFilters"
      :items="formItems"
      :show-expand="false"
      @reset="handleReset"
      @search="handleSearch"
    />

    <ElCard class="art-table-card">
      <!-- 表格头部 -->
      <ArtTableHeader
        :loading="loading"
        v-model:columns="columnChecks"
        @refresh="refreshData"
      >
        <template #left>
          <ElButton v-auth="'ai:model:create'" @click="handleCreate" v-ripple>新增模型</ElButton>
        </template>
      </ArtTableHeader>

      <!-- 表格 -->
      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />

      <!-- 弹窗 -->
      <ModelDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :edit-data="currentRow"
        @success="handleSuccess"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { ElMessageBox, ElMessage, ElTag, ElSwitch } from 'element-plus'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import { fetchGetAiModelList, fetchDeleteAiModel, fetchUpdateAiModel } from '@/api/ai-manage'
  import ModelDialog from './model-dialog.vue'

  defineOptions({ name: 'AiModel' })

  const { hasAuth } = useAuth()

  type ModelItem = {
    id: number
    name: string
    provider: string
    model_name: string
    api_key: string
    base_url?: string
    supports_vision: number
    supports_function_calling: number
    status: number
  }

  // 弹窗控制
  const dialogType = ref<'add' | 'edit'>('add')
  const dialogVisible = ref(false)
  const currentRow = ref<ModelItem | null>(null)

  // 供应商中文映射
  const PROVIDER_MAP: Record<string, string> = {
    openai: 'OpenAI',
    deepseek: 'DeepSeek',
    qwen: '通义千问',
    zhipu: '智谱 AI',
    moonshot: 'Moonshot',
    custom: '自定义'
  }

  // 搜索表单
  const formFilters = reactive({
    keyword: '',
    provider: '',
    status: ''
  })

  // 搜索项配置
  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: { clearable: true, placeholder: '名称/模型名' }
    },
    {
      label: '供应商',
      key: 'provider',
      type: 'select',
      props: {
        clearable: true,
        placeholder: '全部',
        options: Object.entries(PROVIDER_MAP).map(([value, label]) => ({ value, label }))
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

  // useTable 核心
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
  } = useTable<typeof fetchGetAiModelList>({
    core: {
      apiFn: fetchGetAiModelList,
      apiParams: { page: 1, limit: 15, keyword: '', provider: '', status: '' },
      immediate: false,
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'globalIndex', label: '序号', width: 70 },
        { prop: 'name', label: '名称', minWidth: 150 },
        {
          prop: 'provider',
          label: '供应商',
          width: 110,
          formatter: (row: ModelItem) =>
            h(ElTag, null, () => PROVIDER_MAP[row.provider] || row.provider)
        },
        { prop: 'model_name', label: '模型标识', minWidth: 180 },
        {
          prop: 'api_key',
          label: 'API Key',
          width: 200,
          showOverflowTooltip: true,
          formatter: (row: ModelItem) => row.api_key ? `sk-***${row.api_key.slice(-4)}` : '-'
        },
        {
          prop: 'supports_vision',
          label: '视觉',
          width: 70,
          align: 'center' as const,
          formatter: (row: ModelItem) =>
            h(
              ElTag,
              { type: row.supports_vision ? 'success' : 'info', size: 'small' },
              () => (row.supports_vision ? '✓' : '✗')
            )
        },
        {
          prop: 'supports_function_calling',
          label: '工具调用',
          width: 90,
          align: 'center' as const,
          formatter: (row: ModelItem) =>
            h(
              ElTag,
              { type: row.supports_function_calling ? 'success' : 'info', size: 'small' },
              () => (row.supports_function_calling ? '支持' : '不支持')
            )
        },
        {
          prop: 'status',
          label: '状态',
          width: 80,
          align: 'center' as const,
          formatter: (row: ModelItem) =>
            h(ElSwitch, {
              modelValue: row.status === 1,
              disabled: !hasAuth('ai:model:update'),
              onChange: (val: boolean | string | number) => handleToggleStatus(row, !!val)
            })
        },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row: ModelItem) =>
            h('div', { onClick: (e: Event) => e.stopPropagation() }, [
              hasAuth('ai:model:update') &&
                h(resolveComponent('ArtButtonTable'), {
                  type: 'edit',
                  onClick: () => handleEdit(row)
                }),
              hasAuth('ai:model:delete') &&
                h(resolveComponent('ArtButtonTable'), {
                  type: 'delete',
                  onClick: () => handleDelete(row)
                })
            ].filter(Boolean))
        }
      ]
    }
  })

  // 搜索
  const handleSearch = (params: Record<string, any>) => {
    replaceSearchParams(params)
    getData()
  }

  // 重置
  const handleReset = () => {
    Object.assign(formFilters, { keyword: '', provider: '', status: '' })
    replaceSearchParams({ keyword: '', provider: '', status: '' })
    getData()
  }

  // 状态切换
  const handleToggleStatus = async (row: ModelItem, val: boolean) => {
    try {
      await fetchUpdateAiModel(row.id, { status: val ? 1 : 0 })
      ElMessage.success('状态已更新')
      refreshData()
    } catch {
      // handled
    }
  }

  // 弹窗
  const handleCreate = () => {
    dialogType.value = 'add'
    currentRow.value = null
    dialogVisible.value = true
  }
  const handleEdit = (row: ModelItem) => {
    dialogType.value = 'edit'
    currentRow.value = { ...row }
    dialogVisible.value = true
  }
  const handleSuccess = () => {
    refreshData()
  }

  // 删除
  const handleDelete = (row: ModelItem) => {
    ElMessageBox.confirm(`确认删除模型"${row.name}"？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteAiModel(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  onMounted(() => {
    replaceSearchParams({ keyword: '', provider: '', status: '' })
    getData()
  })
</script>
