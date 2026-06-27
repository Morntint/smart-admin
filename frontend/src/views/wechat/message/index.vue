<!-- 微信消息管理页面 -->
<template>
  <div class="wechat-message-page art-full-height">
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
              @click="showSendDialog"
              v-ripple
              v-auth="'wechat:message:send'"
            >
              <el-icon class="mr-1"><Promotion /></el-icon>发送模板消息
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

    <!-- 发送模板消息对话框 -->
    <ElDialog
      v-model="sendDialogVisible"
      title="发送模板消息"
      width="700px"
      align-center
      @open="onSendDialogOpen"
    >
      <ElForm ref="sendFormRef" :model="sendForm" :rules="sendRules" label-width="100px">
        <ElFormItem label="应用类型" prop="app_type">
          <ElSelect
            v-model="sendForm.app_type"
            placeholder="请选择应用类型"
            @change="onAppTypeChange"
          >
            <ElOption label="公众号" value="official_account" />
            <ElOption label="小程序" value="mini_program" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="接收用户" prop="openid">
          <ElSelect
            v-model="sendForm.openid"
            filterable
            placeholder="请选择用户"
            style="width: 100%"
            :loading="userLoading"
          >
            <ElOption
              v-for="user in userList"
              :key="user.openid"
              :label="user.nickname || user.openid"
              :value="user.openid"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="模板" prop="template_id">
          <ElSelect
            v-model="sendForm.template_id"
            filterable
            placeholder="请选择模板"
            style="width: 100%"
            :loading="templateLoading"
          >
            <ElOption
              v-for="template in templateList"
              :key="template.template_id"
              :label="template.title"
              :value="template.template_id"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="跳转URL">
          <ElInput v-model="sendForm.url" placeholder="填写后用户点击消息将跳转到该URL" />
        </ElFormItem>
        <ElFormItem label="模板数据">
          <ElInput
            v-model="sendForm.data"
            type="textarea"
            :rows="6"
            placeholder='请输入 JSON 格式数据，如：{"first":{"value":"您好"},"keyword1":{"value":"测试"}}'
          />
          <div class="form-tip">模板数据需符合该模板字段约定；留空时仅发送标题。</div>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="sendDialogVisible = false">取消</ElButton>
        <ElButton type="primary" @click="handleSendMessage" :loading="sending">发送</ElButton>
      </template>
    </ElDialog>

    <!-- 消息详情对话框 -->
    <ElDialog v-model="detailDialogVisible" title="消息详情" width="600px" align-center>
      <ElDescriptions :column="1" border>
        <ElDescriptionsItem label="消息ID">{{ currentMessage?.msg_id }}</ElDescriptionsItem>
        <ElDescriptionsItem label="消息类型">{{
          getMsgTypeText(currentMessage?.msg_type)
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="发送者">{{ currentMessage?.from_user }}</ElDescriptionsItem>
        <ElDescriptionsItem label="接收者">{{ currentMessage?.to_user }}</ElDescriptionsItem>
        <ElDescriptionsItem label="消息内容">
          <div style="white-space: pre-wrap; max-height: 300px; overflow-y: auto">
            {{ currentMessage?.content }}
          </div>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="创建时间">{{ currentMessage?.created_at }}</ElDescriptionsItem>
      </ElDescriptions>
      <template #footer>
        <ElButton @click="detailDialogVisible = false">关闭</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, h, onMounted } from 'vue'
  import { useRoute } from 'vue-router'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import {
    fetchWeChatMessageList,
    sendWeChatTemplateMessage,
    fetchWeChatUserList,
    fetchWeChatTemplateList
  } from '@/api/wechat'
  import { ElTag, ElMessageBox, ElMessage, ElDescriptions, ElDescriptionsItem } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import { Promotion } from '@element-plus/icons-vue'

  defineOptions({ name: 'WeChatMessage' })

  const route = useRoute()

  type WeChatMessageListItem = {
    id: number
    msg_id?: string
    msg_type: string
    from_user: string
    to_user: string
    content?: string
    media_id?: string
    title?: string
    description?: string
    pic_url?: string
    url?: string
    status?: string
    error_code?: string
    error_msg?: string
    created_at?: string
    updated_at?: string
  }

  type WeChatUser = {
    openid: string
    nickname?: string
  }

  type WeChatTemplate = {
    template_id: string
    title: string
    content?: string
  }

  // 发送对话框
  const sendDialogVisible = ref(false)
  const sending = ref(false)
  const sendFormRef = ref<FormInstance>()
  const sendForm = reactive({
    openid: '',
    template_id: '',
    app_type: 'official_account' as 'official_account' | 'mini_program',
    url: '',
    data: ''
  })

  const sendRules: FormRules = {
    app_type: [{ required: true, message: '请选择应用类型', trigger: 'change' }],
    openid: [{ required: true, message: '请选择接收用户', trigger: 'change' }],
    template_id: [{ required: true, message: '请选择模板', trigger: 'change' }]
  }

  // 用户和模板列表（按 app_type 动态加载）
  const userList = ref<WeChatUser[]>([])
  const templateList = ref<WeChatTemplate[]>([])
  const userLoading = ref(false)
  const templateLoading = ref(false)

  // 详情对话框
  const detailDialogVisible = ref(false)
  const currentMessage = ref<WeChatMessageListItem | null>(null)

  // 选中行
  const selectedRows = ref<WeChatMessageListItem[]>([])

  // 搜索表单
  const searchForm = ref({
    keyword: '',
    msg_type: '',
    app_type: ''
  })

  const formFilters = reactive({ ...searchForm.value })
  const appliedFilters = reactive({ ...searchForm.value })

  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: {
        clearable: true,
        placeholder: '请输入消息内容关键词'
      }
    },
    {
      label: '消息类型',
      key: 'msg_type',
      type: 'select',
      props: {
        clearable: true,
        options: [
          { label: '文本消息', value: 'text' },
          { label: '图片消息', value: 'image' },
          { label: '语音消息', value: 'voice' },
          { label: '视频消息', value: 'video' },
          { label: '图文消息', value: 'news' },
          { label: '模板消息', value: 'template' },
          { label: '事件推送', value: 'event' }
        ]
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
      apiFn: fetchWeChatMessageList,
      apiParams: {
        page: 1,
        limit: 20,
        ...appliedFilters
      },
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'selection' },
        {
          prop: 'msg_type',
          label: '消息类型',
          width: 120,
          formatter: (row: WeChatMessageListItem) => {
            return h(ElTag, { type: getMsgTypeTagType(row.msg_type) }, () =>
              getMsgTypeText(row.msg_type)
            )
          }
        },
        { prop: 'from_user', label: '发送者', width: 200, showOverflowTooltip: true },
        { prop: 'to_user', label: '接收者', width: 200, showOverflowTooltip: true },
        {
          prop: 'content',
          label: '消息内容',
          minWidth: 250,
          showOverflowTooltip: true,
          formatter: (row: WeChatMessageListItem) => {
            const content = row.content || row.title || '-'
            return content.length > 50 ? content.slice(0, 50) + '...' : content
          }
        },
        {
          prop: 'status',
          label: '状态',
          width: 100,
          formatter: (row: WeChatMessageListItem) => {
            return h(
              ElTag,
              {
                type:
                  row.status === 'success' ? 'success' : row.status === 'failed' ? 'danger' : 'info'
              },
              () => {
                const statusMap: Record<string, string> = {
                  success: '成功',
                  failed: '失败',
                  pending: '发送中'
                }
                return statusMap[row.status || ''] || '-'
              }
            )
          }
        },
        { prop: 'created_at', label: '创建时间', width: 180, sortable: true },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row: WeChatMessageListItem) =>
            h('div', [
              h(ArtButtonTable, {
                type: 'view',
                onClick: () => viewMessageDetail(row),
                title: '详情'
              })
            ])
        }
      ]
    }
  })

  onMounted(() => {
    // 路由 query 预填模板（从模板页"去发送"跳转）
    const templateId = route.query.template_id as string | undefined
    const appType = route.query.app_type as 'official_account' | 'mini_program' | undefined
    if (templateId) {
      sendForm.template_id = templateId
      if (appType) sendForm.app_type = appType
      sendDialogVisible.value = true
    }
  })

  /** 加载用户下拉选项 */
  const loadUserOptions = async (): Promise<void> => {
    userLoading.value = true
    try {
      const resp: any = await fetchWeChatUserList({
        page: 1,
        limit: 200,
        app_type: sendForm.app_type
      })
      userList.value = (resp?.list ?? []).map((u: any) => ({
        openid: u.openid,
        nickname: u.nickname
      }))
    } catch (error) {
      console.error('加载用户失败:', error)
      ElMessage.error('加载用户列表失败')
    } finally {
      userLoading.value = false
    }
  }

  /** 加载模板下拉选项 */
  const loadTemplateOptions = async (): Promise<void> => {
    templateLoading.value = true
    try {
      const resp: any = await fetchWeChatTemplateList({ app_type: sendForm.app_type })
      const items = resp?.list ?? resp ?? []
      templateList.value = (items as any[]).map((t) => ({
        template_id: t.template_id,
        title: t.title
      }))
    } catch (error) {
      console.error('加载模板失败:', error)
      ElMessage.error('加载模板列表失败')
    } finally {
      templateLoading.value = false
    }
  }

  /** 应用类型切换 → 清空已选并重新加载下拉 */
  const onAppTypeChange = (): void => {
    sendForm.openid = ''
    sendForm.template_id = ''
    loadUserOptions()
    loadTemplateOptions()
  }

  /** 对话框打开钩子：加载下拉列表 */
  const onSendDialogOpen = (): void => {
    loadUserOptions()
    loadTemplateOptions()
  }

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
   * 显示发送对话框
   */
  const showSendDialog = (): void => {
    sendForm.openid = ''
    sendForm.template_id = ''
    sendForm.app_type = 'official_account'
    sendForm.url = ''
    sendForm.data = ''
    sendDialogVisible.value = true
  }

  /**
   * 发送模板消息
   */
  const handleSendMessage = async (): Promise<void> => {
    try {
      await sendFormRef.value?.validate()
    } catch {
      return
    }

    let dataObj: Record<string, unknown> = {}
    if (sendForm.data) {
      try {
        dataObj = JSON.parse(sendForm.data)
      } catch {
        ElMessage.error('模板数据格式不正确，请输入有效的 JSON')
        return
      }
    }

    try {
      await ElMessageBox.confirm(`确认向用户 ${sendForm.openid} 发送模板消息？`, '发送确认', {
        confirmButtonText: '确定发送',
        cancelButtonText: '取消',
        type: 'warning'
      })
    } catch {
      return
    }

    sending.value = true
    try {
      const payload: any = {
        openid: sendForm.openid,
        template_id: sendForm.template_id,
        app_type: sendForm.app_type,
        data: dataObj
      }
      if (sendForm.url) payload.url = sendForm.url
      await sendWeChatTemplateMessage(payload)
      ElMessage.success('消息发送成功')
      sendDialogVisible.value = false
      refreshData()
    } catch (error: any) {
      console.error('发送失败:', error)
      ElMessage.error('发送失败：' + (error?.message ?? '请稍后重试'))
    } finally {
      sending.value = false
    }
  }

  /**
   * 查看消息详情
   */
  const viewMessageDetail = (row: WeChatMessageListItem): void => {
    currentMessage.value = row
    detailDialogVisible.value = true
  }

  /**
   * 处理表格行选择变化
   */
  const handleSelectionChange = (selection: WeChatMessageListItem[]): void => {
    selectedRows.value = selection
  }

  /**
   * 获取消息类型文本
   */
  const getMsgTypeText = (msgType?: string): string => {
    const typeMap: Record<string, string> = {
      text: '文本消息',
      image: '图片消息',
      voice: '语音消息',
      video: '视频消息',
      news: '图文消息',
      template: '模板消息',
      event: '事件推送',
      location: '位置消息',
      link: '链接消息',
      miniprogrampage: '小程序卡片'
    }
    return typeMap[msgType || ''] || msgType || '-'
  }

  /**
   * 获取消息类型标签颜色
   */
  const getMsgTypeTagType = (msgType?: string): any => {
    const colorMap: Record<string, string> = {
      text: 'primary',
      image: 'success',
      voice: 'warning',
      video: 'danger',
      news: 'info',
      template: 'primary',
      event: 'warning'
    }
    return colorMap[msgType || ''] || 'info'
  }
</script>

<style scoped lang="scss">
  .wechat-message-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
</style>
