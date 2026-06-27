<!-- 微信模板管理页面 -->
<template>
  <div class="wechat-template-page art-full-height">
    <!-- 搜索栏 -->
    <ArtSearchBar
      v-model="formFilters"
      :items="formItems"
      :showExpand="false"
      @reset="handleReset"
      @search="handleSearch"
    />

    <ElCard class="art-table-card">
      <!-- 表格头部 -->
      <ArtTableHeader :loading="loading" v-model:columns="columnChecks" @refresh="refreshData">
        <template #left>
          <ElSpace>
            <ElButton
              type="primary"
              @click="syncTemplates"
              v-ripple
              v-auth="'wechat:template:sync'"
            >
              <el-icon class="mr-1"><Refresh /></el-icon>同步模板
            </ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <!-- 表格 -->
      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @selection-change="handleSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <!-- 模板详情对话框 -->
    <ElDialog v-model="detailDialogVisible" title="模板详情" width="800px" align-center>
      <ElDescriptions :column="2" border>
        <ElDescriptionsItem label="模板ID" :span="2">{{
          currentTemplate?.template_id
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="模板标题" :span="2">{{
          currentTemplate?.title
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="所属行业" :span="2">{{
          industryText(currentTemplate)
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="创建时间" :span="2">{{
          currentTemplate?.created_at
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="模板内容" :span="2">
          <div class="template-content">
            <pre>{{ currentTemplate?.content }}</pre>
          </div>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="模板示例" :span="2">
          <div v-if="currentTemplate?.example" class="template-example">
            <pre>{{ currentTemplate.example }}</pre>
          </div>
          <el-empty v-else description="暂无示例" :image-size="100" />
        </ElDescriptionsItem>
      </ElDescriptions>
      <template #footer>
        <ElButton @click="detailDialogVisible = false">关闭</ElButton>
        <ElButton type="primary" @click="goSendMessage()">去发送</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, h } from 'vue'
  import { useRouter } from 'vue-router'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { fetchWeChatTemplateList, syncWeChatTemplates } from '@/api/wechat'
  import {
    ElMessageBox,
    ElMessage,
    ElDescriptions,
    ElDescriptionsItem,
    ElEmpty
  } from 'element-plus'
  import { Refresh } from '@element-plus/icons-vue'

  defineOptions({ name: 'WeChatTemplate' })

  const router = useRouter()

  type WeChatTemplateListItem = {
    id: number
    template_id: string
    title: string
    primary_industry?: string
    deputy_industry?: string
    content: string
    example?: string
    app_type?: string
    created_at?: string
    updated_at?: string
  }

  // 详情对话框
  const detailDialogVisible = ref(false)
  const currentTemplate = ref<WeChatTemplateListItem | null>(null)

  // 选中行
  const selectedRows = ref<WeChatTemplateListItem[]>([])

  // 搜索表单
  const searchForm = ref({
    keyword: '',
    app_type: ''
  })

  const formFilters = reactive({ ...searchForm.value })
  const appliedFilters = reactive({ ...searchForm.value })

  const formItems = computed(() => [
    {
      label: '模板名称',
      key: 'keyword',
      type: 'input',
      props: {
        clearable: true,
        placeholder: '请输入模板名称或ID'
      }
    },
    {
      label: '应用类型',
      key: 'app_type',
      type: 'select',
      props: {
        clearable: true,
        options: [
          { label: '公众号', value: 'official_account' },
          { label: '小程序', value: 'mini_program' }
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
    resetSearchParams,
    handleSizeChange,
    handleCurrentChange,
    refreshData
  } = useTable({
    core: {
      apiFn: fetchWeChatTemplateList,
      apiParams: {
        app_type: appliedFilters.app_type
      },
      columnsFactory: () => [
        { type: 'selection' },
        { prop: 'template_id', label: '模板ID', width: 200, showOverflowTooltip: true },
        { prop: 'title', label: '模板标题', width: 200, showOverflowTooltip: true },
        {
          prop: 'content',
          label: '模板内容',
          minWidth: 300,
          showOverflowTooltip: true,
          formatter: (row: WeChatTemplateListItem) => {
            const content = row.content || ''
            return content.length > 80 ? content.slice(0, 80) + '...' : content
          }
        },
        {
          prop: 'industry',
          label: '行业',
          width: 220,
          formatter: (row: WeChatTemplateListItem) => industryText(row)
        },
        { prop: 'created_at', label: '创建时间', width: 180, sortable: true },
        { prop: 'updated_at', label: '更新时间', width: 180, sortable: true },
        {
          prop: 'operation',
          label: '操作',
          width: 180,
          fixed: 'right',
          formatter: (row: WeChatTemplateListItem) =>
            h('div', [
              h(ArtButtonTable, {
                type: 'view',
                onClick: () => viewTemplateDetail(row),
                title: '详情'
              }),
              h(ArtButtonTable, {
                icon: 'ri:send-plane-line',
                onClick: () => goSendMessage(row),
                title: '去发送'
              })
            ])
        }
      ]
    }
  })

  /**
   * 搜索处理
   */
  const handleSearch = (): void => {
    Object.assign(appliedFilters, { ...formFilters })
    replaceSearchParams(appliedFilters)
    getData()
  }

  /**
   * 重置搜索
   */
  const handleReset = (): void => {
    Object.assign(formFilters, { ...searchForm.value })
    Object.assign(appliedFilters, { ...searchForm.value })
    resetSearchParams()
    getData()
  }

  /**
   * 同步模板
   */
  const syncTemplates = async (): Promise<void> => {
    ElMessageBox.confirm(
      '确定要从微信服务器同步模板数据吗？同步后将更新本地模板列表。',
      '同步确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
      .then(async () => {
        try {
          await syncWeChatTemplates({ app_type: appliedFilters.app_type })
          ElMessage.success('同步成功')
          refreshData()
        } catch (error) {
          console.error('同步失败:', error)
          ElMessage.error('同步失败，请稍后重试')
        }
      })
      .catch(() => {
        ElMessage.info('已取消同步')
      })
  }

  /**
   * 查看模板详情
   */
  const viewTemplateDetail = (row: WeChatTemplateListItem): void => {
    currentTemplate.value = row
    detailDialogVisible.value = true
  }

  /**
   * 跳转到消息发送页面并预填模板 ID
   */
  const goSendMessage = (row?: WeChatTemplateListItem): void => {
    const target = row ?? currentTemplate.value
    detailDialogVisible.value = false
    router.push({
      path: '/wechat/message',
      query: {
        template_id: target?.template_id,
        app_type: target?.app_type ?? appliedFilters.app_type ?? 'official_account'
      }
    })
  }

  /**
   * 行业字段展示：兼容 primary_industry 字符串、对象、缺失三种情形
   */
  const industryText = (row?: WeChatTemplateListItem | null): string => {
    if (!row) return '-'
    const parts = [row.primary_industry, row.deputy_industry].filter(
      (v) => v !== null && v !== undefined && v !== ''
    )
    return parts.length > 0 ? parts.join(' / ') : '-'
  }

  /**
   * 处理表格行选择变化
   */
  const handleSelectionChange = (selection: WeChatTemplateListItem[]): void => {
    selectedRows.value = selection
  }
</script>

<style scoped lang="scss">
  .wechat-template-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .template-content,
  .template-example {
    background-color: var(--el-fill-color-light);
    border-radius: 4px;
    padding: 12px;
    max-height: 300px;
    overflow-y: auto;
    width: 100%;

    pre {
      margin: 0;
      white-space: pre-wrap;
      word-break: break-all;
      font-size: 13px;
      line-height: 1.6;
      color: var(--el-text-color-primary);
    }
  }
</style>
