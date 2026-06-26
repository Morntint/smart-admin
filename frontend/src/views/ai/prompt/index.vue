<!-- AI 提示词管理页面 -->
<template>
  <div class="ai-prompt-page art-full-height">
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
          <ElButton v-auth="'ai:prompt:create'" @click="handleCreate" v-ripple>新增模板</ElButton>
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

      <PromptDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :edit-data="currentRow"
        @success="handleSuccess"
      />
    </ElCard>

    <!-- 测试渲染弹窗 -->
    <ElDialog v-model="showTestDialog" title="测试提示词渲染" width="680px" align-center>
      <div class="test-section">
        <div class="test-label">模板内容</div>
        <pre class="test-template">{{ testTemplate }}</pre>
      </div>
      <div class="test-section">
        <div class="test-label">变量 (JSON)</div>
        <ElInput
          v-model="testVariables"
          type="textarea"
          :rows="4"
          placeholder='{"name": "张三", "industry": "互联网"}'
        />
        <ElButton type="primary" class="mt-2" @click="handleRenderTest" v-ripple>渲染</ElButton>
      </div>
      <div v-if="testResult" class="test-section">
        <div class="test-label">渲染结果</div>
        <pre class="test-result">{{ testResult }}</pre>
      </div>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox, ElTag } from 'element-plus'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import {
    fetchGetPromptList,
    fetchDeletePrompt,
    fetchRenderPrompt,
    fetchGetPrompt
  } from '@/api/ai-manage'
  import PromptDialog from './prompt-dialog.vue'

  defineOptions({ name: 'AiPrompt' })

  const { hasAuth } = useAuth()

  type PromptItem = {
    id: number
    name: string
    code: string
    category: string
    description: string
    is_system: number
    status: number
  }

  const dialogType = ref<'add' | 'edit'>('add')
  const dialogVisible = ref(false)
  const currentRow = ref<PromptItem | null>(null)

  // 测试弹窗
  const showTestDialog = ref(false)
  const testTemplate = ref('')
  const testVariables = ref('')
  const testResult = ref('')
  const testCode = ref('')

  const CATEGORY_MAP: Record<string, string> = {
    general: '通用',
    coding: '编程',
    marketing: '营销',
    analysis: '分析',
    custom: '自定义'
  }

  // 搜索
  const formFilters = reactive({
    keyword: '',
    category: ''
  })

  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: { clearable: true, placeholder: '名称/标识' }
    },
    {
      label: '分类',
      key: 'category',
      type: 'select',
      props: {
        clearable: true,
        placeholder: '全部',
        options: Object.entries(CATEGORY_MAP).map(([value, label]) => ({ value, label }))
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
  } = useTable<typeof fetchGetPromptList>({
    core: {
      apiFn: fetchGetPromptList,
      apiParams: { page: 1, limit: 15, keyword: '', category: '' },
      immediate: false,
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'globalIndex', label: '序号', width: 70 },
        { prop: 'name', label: '模板名称', minWidth: 160 },
        { prop: 'code', label: '标识', minWidth: 140 },
        {
          prop: 'category',
          label: '分类',
          width: 100,
          formatter: (row: PromptItem) =>
            h(ElTag, { size: 'small' }, () => CATEGORY_MAP[row.category] || row.category)
        },
        {
          prop: 'description',
          label: '描述',
          minWidth: 200,
          showOverflowTooltip: true,
          formatter: (row: PromptItem) => row.description || '-'
        },
        {
          prop: 'is_system',
          label: '类型',
          width: 80,
          align: 'center' as const,
          formatter: (row: PromptItem) =>
            h(
              ElTag,
              { type: row.is_system ? 'info' : 'primary', size: 'small' },
              () => (row.is_system ? '内置' : '自定义')
            )
        },
        {
          prop: 'status',
          label: '状态',
          width: 80,
          align: 'center' as const,
          formatter: (row: PromptItem) =>
            h(
              ElTag,
              { type: row.status === 1 ? 'success' : 'danger', size: 'small' },
              () => (row.status === 1 ? '正常' : '禁用')
            )
        },
        {
          prop: 'operation',
          label: '操作',
          width: 200,
          fixed: 'right',
          formatter: (row: PromptItem) =>
            h('div', { onClick: (e: Event) => e.stopPropagation() }, [
              h(
                'span',
                {
                  class:
                    'inline-flex items-center justify-center min-w-8 h-8 px-2.5 mr-2.5 text-sm cursor-pointer rounded-md align-middle bg-warning/12 text-warning',
                  onClick: () => handleTest(row)
                },
                [h(resolveComponent('ArtSvgIcon'), { icon: 'ri:test-tube-line' })]
              ),
              hasAuth('ai:prompt:update') &&
                h(resolveComponent('ArtButtonTable'), {
                  type: 'edit',
                  onClick: () => handleEdit(row)
                }),
              !row.is_system &&
                hasAuth('ai:prompt:delete') &&
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
    Object.assign(formFilters, { keyword: '', category: '' })
    replaceSearchParams({ keyword: '', category: '' })
    getData()
  }

  const handleCreate = () => {
    dialogType.value = 'add'
    currentRow.value = null
    dialogVisible.value = true
  }
  const handleEdit = (row: PromptItem) => {
    dialogType.value = 'edit'
    currentRow.value = { ...row }
    dialogVisible.value = true
  }
  const handleSuccess = () => refreshData()

  const handleDelete = (row: PromptItem) => {
    ElMessageBox.confirm(`确认删除模板"${row.name}"？`, '删除确认', {
      type: 'warning'
    })
      .then(async () => {
        await fetchDeletePrompt(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => ElMessage.info('已取消删除'))
  }

  const handleTest = async (row: PromptItem) => {
    testCode.value = row.code
    testTemplate.value = row.code
    testVariables.value = '{}'
    testResult.value = ''
    showTestDialog.value = true
    // 获取完整模板
    try {
      const res: any = await fetchGetPrompt(row.id)
      testTemplate.value = res.content || row.code
    } catch {
      // handled
    }
  }

  const handleRenderTest = async () => {
    let vars: Record<string, string> = {}
    try {
      vars = JSON.parse(testVariables.value)
    } catch {
      ElMessage.warning('变量格式无效，应为 JSON')
      return
    }
    try {
      const res: any = await fetchRenderPrompt(testCode.value, vars)
      testResult.value = res.content
    } catch {
      // handled
    }
  }

  onMounted(() => {
    replaceSearchParams({ keyword: '', category: '' })
    getData()
  })
</script>

<style scoped lang="scss">
  .test-section {
    margin-bottom: 16px;
  }
  .test-label {
    font-weight: 600;
    margin-bottom: 8px;
  }
  .test-template,
  .test-result {
    background: var(--default-box-color);
    padding: 12px;
    border-radius: 6px;
    white-space: pre-wrap;
    font-size: 13px;
    max-height: 200px;
    overflow-y: auto;
  }
</style>
