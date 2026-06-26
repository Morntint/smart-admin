<!-- AI 知识库管理页面 -->
<template>
  <div class="ai-knowledge-page art-full-height">
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
          <ElButton v-auth="'ai:knowledge:create'" @click="handleCreate" v-ripple>新增知识库</ElButton>
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

      <!-- 知识库弹窗 -->
      <KbDialog
        v-model:visible="kbDialogVisible"
        :type="kbDialogType"
        :edit-data="currentRow"
        @success="handleSuccess"
      />

      <!-- 文档管理弹窗 -->
      <DocumentDialog
        v-model:visible="docDialogVisible"
        :kb-id="currentKbId"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { ElMessageBox, ElMessage, ElTag } from 'element-plus'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import { fetchGetKnowledgeList, fetchDeleteKnowledge } from '@/api/ai-manage'
  import KbDialog from './kb-dialog.vue'
  import DocumentDialog from './document-dialog.vue'

  defineOptions({ name: 'AiKnowledge' })

  const { hasAuth } = useAuth()

  type KbItem = {
    id: number
    name: string
    description: string
    document_count: number
    chunk_size: number
    similarity_threshold: number
    status: number
  }

  // 弹窗
  const kbDialogType = ref<'add' | 'edit'>('add')
  const kbDialogVisible = ref(false)
  const docDialogVisible = ref(false)
  const currentRow = ref<KbItem | null>(null)
  const currentKbId = ref(0)

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
      props: { clearable: true, placeholder: '名称/描述' }
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
  } = useTable<typeof fetchGetKnowledgeList>({
    core: {
      apiFn: fetchGetKnowledgeList,
      apiParams: { page: 1, limit: 15, keyword: '', status: '' },
      immediate: false,
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'globalIndex', label: '序号', width: 70 },
        { prop: 'name', label: '知识库名称', minWidth: 180 },
        {
          prop: 'description',
          label: '描述',
          minWidth: 200,
          showOverflowTooltip: true,
          formatter: (row: KbItem) => row.description || '-'
        },
        {
          prop: 'document_count',
          label: '文档数',
          width: 100,
          align: 'center' as const,
          formatter: (row: KbItem) => String(row.document_count ?? 0)
        },
        {
          prop: 'chunk_size',
          label: '分块大小',
          width: 100,
          align: 'center' as const
        },
        {
          prop: 'similarity_threshold',
          label: '相似度阈值',
          width: 110,
          align: 'center' as const,
          formatter: (row: KbItem) => Number(row.similarity_threshold).toFixed(2)
        },
        {
          prop: 'status',
          label: '状态',
          width: 80,
          align: 'center' as const,
          formatter: (row: KbItem) =>
            h(
              ElTag,
              { type: row.status === 1 ? 'success' : 'danger', size: 'small' },
              () => (row.status === 1 ? '正常' : '禁用')
            )
        },
        {
          prop: 'operation',
          label: '操作',
          width: 180,
          fixed: 'right',
          formatter: (row: KbItem) =>
            h('div', { onClick: (e: Event) => e.stopPropagation() }, [
              h(resolveComponent('ArtButtonTable'), {
                type: 'view',
                onClick: () => openDocuments(row)
              }),
              hasAuth('ai:knowledge:update') &&
                h(resolveComponent('ArtButtonTable'), {
                  type: 'edit',
                  onClick: () => handleEdit(row)
                }),
              hasAuth('ai:knowledge:delete') &&
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
    kbDialogType.value = 'add'
    currentRow.value = null
    kbDialogVisible.value = true
  }
  const handleEdit = (row: KbItem) => {
    kbDialogType.value = 'edit'
    currentRow.value = { ...row }
    kbDialogVisible.value = true
  }
  const handleSuccess = () => refreshData()

  const openDocuments = (row: KbItem) => {
    currentKbId.value = row.id
    docDialogVisible.value = true
  }

  const handleDelete = (row: KbItem) => {
    ElMessageBox.confirm(
      `确认删除知识库"${row.name}"？文档和向量数据将被清理`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
      .then(async () => {
        await fetchDeleteKnowledge(row.id)
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
