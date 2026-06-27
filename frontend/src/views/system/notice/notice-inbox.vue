<!-- 用户通知收件箱 -->
<template>
  <div class="notice-inbox art-full-height">
    <ElCard class="art-table-card">
      <!-- 操作栏 -->
      <div class="table-toolbar">
        <ElSpace wrap>
          <ElButton type="primary" :disabled="unreadCount === 0" @click="handleMarkAllRead" v-ripple>
            <ArtSvgIcon icon="ri:check-double-line" class="mr-1" />
            全部标记已读
          </ElButton>
        </ElSpace>
        <ElTabs v-model="activeTab" class="inbox-tabs" @tab-change="handleTabChange">
          <ElTabPane label="全部" name="all" />
          <ElTabPane label="未读" name="unread">
            <ElBadge v-if="unreadCount > 0" :value="unreadCount" type="warning" class="ml-1" size="small" />
          </ElTabPane>
          <ElTabPane label="已读" name="read" />
        </ElTabs>
      </div>

      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @row-click="handleRowClick"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <!-- 通知详情弹窗 -->
    <ElDialog
      v-model="detailVisible"
      :title="null"
      width="600px"
      :close-on-click-modal="false"
      destroy-on-close
      class="inbox-detail-dialog"
    >
      <div v-if="currentDetail" class="inbox-detail">
        <!-- 头部 -->
        <div class="inbox-detail-header">
          <div class="inbox-detail-title-row">
            <div class="inbox-detail-icon" :class="getIconClass(currentDetail.level)">
              <ArtSvgIcon :icon="getIcon(currentDetail.level)" size="20" />
            </div>
            <div class="inbox-detail-title-group">
              <h3 class="inbox-detail-title">{{ currentDetail.title }}</h3>
              <div class="inbox-detail-meta">
                <span class="meta-item">
                  <ArtSvgIcon icon="ri:time-line" size="14" />
                  {{ currentDetail.created_at }}
                </span>
                <span class="meta-item">
                  <ArtSvgIcon icon="ri:user-line" size="14" />
                  {{ currentDetail.sender_name || '系统' }}
                </span>
              </div>
            </div>
            <button class="inbox-detail-close" @click="detailVisible = false">
              <ArtSvgIcon icon="ri:close-line" size="18" />
            </button>
          </div>

          <!-- 标签组 -->
          <div class="inbox-detail-tags">
            <ElTag :type="TYPE_CONFIG[currentDetail.type]?.type || 'info'" size="small" effect="light">
              {{ TYPE_CONFIG[currentDetail.type]?.text || '未知' }}
            </ElTag>
            <ElTag :type="currentDetail.is_read === 1 ? 'success' : 'warning'" size="small" effect="light">
              {{ currentDetail.is_read === 1 ? '已读' : '未读' }}
            </ElTag>
            <ElTag v-if="currentDetail.expire_time" type="info" size="small" effect="light">
              <ArtSvgIcon icon="ri:hourglass-line" size="12" class="mr-0.5" />
              {{ currentDetail.expire_time }} 过期
            </ElTag>
          </div>
        </div>

        <!-- 分隔线 -->
        <div class="inbox-detail-divider"></div>

        <!-- 内容区域 -->
        <div class="inbox-detail-body">
          <div class="detail-content" v-html="safeContent"></div>
        </div>

        <!-- 底部操作区 -->
        <div class="inbox-detail-footer">
          <ElButton class="footer-btn" @click="detailVisible = false">
            <ArtSvgIcon icon="ri:close-line" size="16" class="mr-1" />
            关闭
          </ElButton>
          <ElButton v-if="currentDetail?.link" type="primary" class="footer-btn primary" @click="handleGoToLink">
            前往查看
            <ArtSvgIcon icon="ri:arrow-right-line" size="16" class="ml-1" />
          </ElButton>
        </div>
      </div>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, reactive, onMounted, h } from 'vue'
  import { ElMessage, ElTag, ElDescriptions, ElDescriptionsItem } from 'element-plus'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import {
    fetchGetMyNoticeList,
    fetchMarkAllNoticeRead,
    fetchMarkNoticeRead
  } from '@/api/system-manage'
  import { sanitizeHtml } from '@/utils/sanitize-html'
  import { useRouter } from 'vue-router'

  defineOptions({ name: 'NoticeInbox' })

  type NoticeListItem = Api.SystemManage.NoticeListItem

  const router = useRouter()

  const activeTab = ref('all')
  const loading = ref(false)
  const data = ref<NoticeListItem[]>([])
  const unreadCount = ref(0)

  // ArtTable pagination 需要 current, size, total
  const pagination = reactive({
    current: 1,
    size: 20,
    total: 0
  })

  // 详情弹窗
  const detailVisible = ref(false)
  const currentDetail = ref<NoticeListItem | null>(null)

  const safeContent = computed(() => sanitizeHtml(currentDetail.value?.content) || '<p>-</p>')

  /**
   * 根据级别获取图标
   */
  const getIcon = (level?: string): string => {
    const iconMap: Record<string, string> = {
      success: 'ri:checkbox-circle-fill',
      warning: 'ri:alarm-warning-fill',
      danger: 'ri:error-warning-fill',
      info: 'ri:notification-3-fill'
    }
    return iconMap[level || 'info'] || iconMap.info
  }

  /**
   * 根据级别获取图标样式类
   */
  const getIconClass = (level?: string): string => {
    const classMap: Record<string, string> = {
      success: 'bg-success/12 text-success',
      warning: 'bg-warning/12 text-warning',
      danger: 'bg-danger/12 text-danger',
      info: 'bg-primary/12 text-primary'
    }
    return classMap[level || 'info'] || classMap.info
  }

  /**
   * 类型配置
   */
  const TYPE_CONFIG: Record<
    number,
    { type: 'primary' | 'success' | 'warning' | 'info' | 'danger'; text: string }
  > = {
    1: { type: 'primary', text: '系统通知' },
    2: { type: 'warning', text: '待办' },
    3: { type: 'danger', text: '预警' },
    4: { type: 'info', text: '个人消息' }
  }

  // 表格列配置
  const columns = [
    { type: 'index', width: 60, label: '序号', align: 'center' },
    {
      prop: 'is_read',
      label: '状态',
      width: 80,
      align: 'center',
      formatter: (row: NoticeListItem) =>
        h(ElTag, { type: row.is_read === 1 ? 'success' : 'warning', size: 'small' }, () =>
          row.is_read === 1 ? '已读' : '未读'
        )
    },
    {
      prop: 'title',
      label: '通知标题',
      minWidth: 280,
      showOverflowTooltip: true,
      formatter: (row: NoticeListItem) =>
        h('span', { class: { 'font-semibold': row.is_read === 0 } }, row.title || '-')
    },
    {
      prop: 'type',
      label: '类型',
      width: 100,
      align: 'center',
      formatter: (row: NoticeListItem) => {
        const cfg = TYPE_CONFIG[row.type] || { type: 'info' as const, text: '未知' }
        return h(ElTag, { type: cfg.type, size: 'small' }, () => cfg.text)
      }
    },
    {
      prop: 'sender_name',
      label: '发送人',
      width: 100,
      align: 'center',
      formatter: (row: NoticeListItem) => row.sender_name || '系统'
    },
    {
      prop: 'created_at',
      label: '接收时间',
      width: 180,
      sortable: 'custom'
    },
    {
      prop: 'operation',
      label: '操作',
      width: 180,
      fixed: 'right',
      align: 'center',
      formatter: (row: NoticeListItem) =>
        h('div', { class: 'flex justify-center gap-2' }, [
          h(ArtButtonTable, {
            type: 'view',
            onClick: () => handleViewDetail(row)
          }),
          row.is_read === 0 &&
            h(ArtButtonTable, {
              icon: 'ri:check-line',
              iconClass: 'bg-success/12 text-success',
              title: '标记已读',
              onClick: () => handleMarkOneRead(row)
            }),
          row.link &&
            h(ArtButtonTable, {
              icon: 'ri:arrow-right-up-line',
              iconClass: 'bg-primary/12 text-primary',
              title: '前往',
              onClick: () => handleGoToLinkFromRow(row)
            })
        ])
    }
  ]

  /**
   * 加载数据
   */
  const loadData = async (): Promise<void> => {
    loading.value = true
    try {
      const params: Record<string, any> = {
        page: pagination.current,
        limit: pagination.size
      }
      if (activeTab.value === 'unread') params.is_read = 0
      if (activeTab.value === 'read') params.is_read = 1

      const res = await fetchGetMyNoticeList(params)
      data.value = res.list || []
      pagination.total = res.total ?? 0

      // 加载未读数
      if (activeTab.value === 'all') {
        loadUnreadCount()
      }
    } catch (error) {
      console.error('加载通知列表失败', error)
    } finally {
      loading.value = false
    }
  }

  /**
   * 加载未读数
   */
  const loadUnreadCount = async (): Promise<void> => {
    try {
      const res = await fetchGetMyNoticeList({ page: 1, limit: 1, is_read: 0 })
      unreadCount.value = res.total ?? 0
    } catch (error) {
      console.error('加载未读数失败', error)
    }
  }

  /**
   * 切换标签
   */
  const handleTabChange = (): void => {
    pagination.current = 1
    loadData()
  }

  /**
   * 行点击 - 查看详情
   */
  const handleRowClick = (row: NoticeListItem): void => {
    handleViewDetail(row)
  }

  /**
   * 查看详情
   */
  const handleViewDetail = async (row: NoticeListItem): Promise<void> => {
    if (row.is_read === 0) {
      try {
        await fetchMarkNoticeRead(row.id)
        row.is_read = 1
        if (unreadCount.value > 0) unreadCount.value--
      } catch {
        // 失败不影响查看详情
      }
    }
    currentDetail.value = row
    detailVisible.value = true
  }

  /**
   * 全部标记已读
   */
  const handleMarkAllRead = async (): Promise<void> => {
    try {
      await fetchMarkAllNoticeRead()
      ElMessage.success('已全部标记为已读')
      data.value.forEach((item) => (item.is_read = 1))
      unreadCount.value = 0
    } catch (error) {
      console.error('标记全部已读失败', error)
    }
  }

  /**
   * 单条标记已读
   */
  const handleMarkOneRead = async (row: NoticeListItem): Promise<void> => {
    try {
      await fetchMarkNoticeRead(row.id)
      row.is_read = 1
      if (unreadCount.value > 0) unreadCount.value--
      ElMessage.success('已标记为已读')
    } catch (error) {
      console.error('标记已读失败', error)
    }
  }

  /**
   * 从列表行前往链接
   */
  const handleGoToLinkFromRow = (row: NoticeListItem): void => {
    if (!row.link) return
    if (row.is_read === 0) {
      handleMarkOneRead(row)
    }
    if (row.link.startsWith('/')) {
      router.push(row.link)
    } else if (/^https?:/i.test(row.link)) {
      window.open(row.link, '_blank', 'noopener,noreferrer')
    }
  }

  /**
   * 前往链接（详情弹窗）
   */
  const handleGoToLink = (): void => {
    const link = currentDetail.value?.link
    if (!link) return
    detailVisible.value = false
    if (link.startsWith('/')) {
      router.push(link)
    } else if (/^https?:/i.test(link)) {
      window.open(link, '_blank', 'noopener,noreferrer')
    }
  }

  const handleSizeChange = (size: number): void => {
    pagination.size = size
    pagination.current = 1
    loadData()
  }

  const handleCurrentChange = (page: number): void => {
    pagination.current = page
    loadData()
  }

  onMounted(() => {
    loadData()
  })
</script>

<style scoped lang="scss">
  .notice-inbox {
    padding: 16px;
  }

  .table-toolbar {
    padding: 16px 20px 0;

    :deep(.el-button) {
      margin-bottom: 12px;
    }
  }

  .inbox-tabs {
    margin: 0 -20px;
    padding-left: 20px;
    border-bottom: 1px solid var(--el-border-color-lighter);

    :deep(.el-tabs__nav-wrap::after) {
      height: 0;
    }

    :deep(.el-tabs__item) {
      padding: 0 16px;
      height: 36px;
      line-height: 36px;
    }
  }

  .font-semibold {
    font-weight: 600;
  }

  /* ============ 收件箱详情弹窗 ============ */
  .inbox-detail-dialog {
    :deep(.el-dialog) {
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 20px 60px -8px rgba(0, 0, 0, 0.16),
                  0 8px 24px -4px rgba(0, 0, 0, 0.1);
    }

    :deep(.el-dialog__header) {
      display: none;
    }

    :deep(.el-dialog__body) {
      padding: 0;
      overflow: hidden;
    }
  }

  .inbox-detail {
    display: flex;
    flex-direction: column;
  }

  /* 头部 */
  .inbox-detail-header {
    padding: 24px 28px 16px;
  }

  .inbox-detail-title-row {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 14px;
  }

  .inbox-detail-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
  }

  .inbox-detail-title-group {
    flex: 1;
    min-width: 0;
    padding-top: 2px;
  }

  .inbox-detail-title {
    margin: 0 0 8px;
    font-size: 17px;
    font-weight: 700;
    color: var(--el-text-color-primary);
    line-height: 1.4;
    word-break: break-word;
    letter-spacing: -0.2px;
  }

  .inbox-detail-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    font-size: 13px;
    color: var(--el-text-color-secondary);

    .meta-item {
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
  }

  .inbox-detail-close {
    width: 32px;
    height: 32px;
    border: none;
    background: var(--el-fill-color-light);
    color: var(--el-text-color-secondary);
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0;

    &:hover {
      background: var(--el-fill-color);
      color: var(--el-text-color-primary);
      transform: scale(1.05);
    }

    &:active {
      transform: scale(0.95);
    }
  }

  .inbox-detail-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding-left: 58px;

    :deep(.el-tag) {
      border-radius: 6px;
      padding: 3px 10px;
      font-size: 12px;
      font-weight: 500;
    }
  }

  /* 分隔线 */
  .inbox-detail-divider {
    height: 1px;
    margin: 0 28px;
    background: linear-gradient(90deg,
      transparent 0%,
      var(--el-border-color-lighter) 10%,
      var(--el-border-color-lighter) 90%,
      transparent 100%
    );
  }

  /* 内容 */
  .inbox-detail-body {
    padding: 24px 28px;
    max-height: 380px;
    overflow-y: auto;

    &::-webkit-scrollbar {
      width: 6px;
    }

    &::-webkit-scrollbar-track {
      background: transparent;
    }

    &::-webkit-scrollbar-thumb {
      background: var(--el-border-color);
      border-radius: 3px;
      transition: background 0.2s;

      &:hover {
        background: var(--el-border-color-dark);
      }
    }
  }

  .detail-content {
    font-size: 14.5px;
    line-height: 1.85;
    color: var(--el-text-color-regular);

    :deep(p) {
      margin: 0 0 12px;
      &:last-child {
        margin-bottom: 0;
      }
    }
    :deep(img) {
      max-width: 100%;
      height: auto;
      border-radius: 10px;
      margin: 12px 0;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }
    :deep(a) {
      color: var(--el-color-primary);
      text-decoration: none;
      font-weight: 500;
      padding: 0 2px;
      border-radius: 4px;
      transition: background 0.15s;

      &:hover {
        background: var(--el-color-primary-light-9);
        text-decoration: none;
      }
    }
    :deep(ul),
    :deep(ol) {
      margin: 12px 0;
      padding-left: 24px;

      li {
        margin: 6px 0;
      }
    }

    :deep(h1),
    :deep(h2),
    :deep(h3),
    :deep(h4) {
      margin: 18px 0 10px;
      font-weight: 600;
      color: var(--el-text-color-primary);
      letter-spacing: -0.2px;
    }

    :deep(h1) { font-size: 18px; }
    :deep(h2) { font-size: 16px; }
    :deep(h3) { font-size: 15px; }

    :deep(blockquote) {
      margin: 14px 0;
      padding: 12px 16px;
      background: var(--el-fill-color-light);
      border-left: 4px solid var(--el-color-primary);
      border-radius: 0 8px 8px 0;
      color: var(--el-text-color-secondary);
      font-style: italic;
    }

    :deep(code) {
      padding: 2px 8px;
      background: var(--el-fill-color);
      border-radius: 6px;
      font-size: 13px;
      color: var(--el-color-danger);
      font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
    }

    :deep(pre) {
      margin: 14px 0;
      padding: 16px;
      background: var(--el-fill-color);
      border-radius: 10px;
      overflow-x: auto;

      code {
        padding: 0;
        background: transparent;
        color: inherit;
      }
    }
  }

  /* 底部 */
  .inbox-detail-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 18px 28px 22px;
    background: var(--el-bg-color);
    border-top: 1px solid var(--el-border-color-lighter);

    .footer-btn {
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);

      &:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      }

      &:active {
        transform: translateY(0);
      }

      &.primary {
        background: linear-gradient(135deg, var(--el-color-primary) 0%, var(--el-color-primary-dark-2) 100%);
        box-shadow: 0 4px 12px rgba(var(--el-color-primary-rgb), 0.3);

        &:hover {
          box-shadow: 0 6px 16px rgba(var(--el-color-primary-rgb), 0.4);
        }
      }
    }
  }
</style>
