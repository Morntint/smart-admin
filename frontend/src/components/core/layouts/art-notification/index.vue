<!-- 顶栏通知面板 -->
<template>
  <div
    class="art-notification-panel art-card-sm !shadow-xl"
    :style="{
      transform: show ? 'scaleY(1)' : 'scaleY(0.9)',
      opacity: show ? 1 : 0
    }"
    v-show="visible"
    @click.stop
  >
    <div class="flex-cb px-3.5 mt-3.5">
      <span class="text-base font-medium text-g-800">{{ $t('notice.title') }}</span>
      <span
        class="text-xs text-g-800 px-1.5 py-1 c-p select-none rounded hover:bg-g-200"
        @click="handleReadAll"
      >
        {{ $t('notice.btnRead') }}
      </span>
    </div>

    <div class="w-full h-[calc(100%-60px)] mt-3.5">
      <div class="h-[calc(100%-60px)] overflow-y-scroll scrollbar-thin">
        <!-- 通知列表 -->
        <ul v-show="allItems.length > 0">
          <li
            v-for="item in allItems"
            :key="item.id"
            class="box-border flex-c px-3.5 py-3.5 c-p last:border-b-0 hover:bg-g-200/60"
            @click="handleItemClick(item)"
          >
            <div
              class="size-9 leading-9 text-center rounded-lg flex-cc"
              :class="[getNoticeStyle(item).iconClass]"
            >
              <ArtSvgIcon class="text-lg !bg-transparent" :icon="getNoticeStyle(item).icon" />
            </div>
            <div class="w-[calc(100%-45px)] ml-3.5">
              <h4 class="text-sm font-normal leading-5.5 text-g-900 line-clamp-1">
                {{ item.title }}
                <ElTag
                  v-if="item.is_read === 0"
                  type="warning"
                  size="small"
                  effect="plain"
                  class="ml-1"
                >
                  未读
                </ElTag>
              </h4>
              <p class="mt-1.5 text-xs text-g-500">{{ formatTime(item.created_at) }}</p>
            </div>
          </li>
        </ul>

        <!-- 空状态 -->
        <div
          v-show="allItems.length === 0"
          class="relative top-25 h-full text-g-500 text-center !bg-transparent"
        >
          <ArtSvgIcon icon="system-uicons:inbox" class="text-5xl" />
          <p class="mt-3.5 text-xs !bg-transparent">暂无通知</p>
        </div>
      </div>

      <div class="relative box-border w-full px-3.5">
        <ElButton class="w-full mt-3" @click="handleViewAll" v-ripple>
          {{ $t('notice.viewAll') }}
        </ElButton>
      </div>
    </div>

    <div class="h-25"></div>
  </div>

  <!-- 通知详情弹窗 -->
  <ElDialog
    v-model="detailVisible"
    title="消息详情"
    :show-close="true"
    width="560px"
    :close-on-click-modal="true"
    destroy-on-close
    class="notification-detail-dialog"
    align-center
  >
    <div v-if="currentDetail" class="notice-detail">
      <!-- 头部信息区 -->
      <div class="notice-detail-header">
        <!-- 图标 & 标题 -->
        <div class="notice-detail-title-section">
          <div class="notice-detail-icon-wrapper" :class="[getNoticeStyle(currentDetail).iconClass]">
            <ArtSvgIcon :icon="getNoticeStyle(currentDetail).icon" size="22" />
          </div>
          <div class="notice-detail-title-group">
            <h3 class="notice-detail-title">{{ currentDetail.title }}</h3>
            <div class="notice-detail-subtitle">
              <span class="subtitle-item">
                <ArtSvgIcon icon="ri:time-line" size="14" />
                {{ formatTime(currentDetail.created_at) }}
              </span>
              <span v-if="currentDetail.sender_name" class="subtitle-item">
                <ArtSvgIcon icon="ri:user-line" size="14" />
                {{ currentDetail.sender_name }}
              </span>
            </div>
          </div>
        </div>

        <!-- 标签组 -->
        <div class="notice-detail-tags">
          <ElTag
            v-if="currentDetail.type_text"
            size="small"
            :type="getLevelTagType(currentDetail.level)"
            effect="light"
            class="tag-item"
          >
            {{ currentDetail.type_text }}
          </ElTag>
          <ElTag
            v-if="currentDetail.is_read === 0"
            size="small"
            type="warning"
            effect="light"
            class="tag-item"
          >
            未读
          </ElTag>
          <ElTag
            v-if="currentDetail.expire_time"
            size="small"
            type="info"
            effect="light"
            class="tag-item"
          >
            <ArtSvgIcon icon="ri:hourglass-line" size="12" class="mr-0.5" />
            {{ currentDetail.expire_time }} 过期
          </ElTag>
        </div>
      </div>

      <!-- 分隔线 -->
      <div class="notice-detail-divider"></div>

      <!-- 内容区域 -->
      <div class="notice-detail-body">
        <div class="notice-detail-content" v-html="safeContent" />
      </div>

      <!-- 底部操作区 -->
      <div class="notice-detail-footer">
        <ElButton class="footer-btn" @click="detailVisible = false">
          <ArtSvgIcon icon="ri:close-line" size="16" class="mr-1" />
          关闭
        </ElButton>
        <ElButton
          v-if="currentDetail.link"
          type="primary"
          class="footer-btn primary"
          @click="handleGoToLink"
          v-ripple
        >
          前往查看
          <ArtSvgIcon icon="ri:arrow-right-line" size="16" class="ml-1" />
        </ElButton>
      </div>
    </div>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { ElTag, ElMessage } from 'element-plus'
  import {
    fetchGetMyNoticeList,
    fetchMarkAllNoticeRead,
    fetchMarkNoticeRead
  } from '@/api/system-manage'
  import { safePush } from '@/utils/navigation/safe-push'
  import { sanitizeHtml } from '@/utils/sanitize-html'

  defineOptions({ name: 'ArtNotification' })

  type NoticeListItem = Api.SystemManage.NoticeListItem

  const { t } = useI18n()

  const props = defineProps<{ value: boolean }>()
  const emit = defineEmits<{
    'update:value': [value: boolean]
  }>()

  const show = ref(false)
  const visible = ref(false)
  const allItems = ref<NoticeListItem[]>([])
  const detailVisible = ref(false)
  const currentDetail = ref<NoticeListItem | null>(null)

  /**
   * 通知项图标 + 配色（按 level 决定）
   */
  const styleByLevel: Record<string, { icon: string; iconClass: string; decoratorClass: string }> = {
    success: {
      icon: 'ri:checkbox-circle-fill',
      iconClass: 'bg-success/12 text-success',
      decoratorClass: 'decorator-success'
    },
    warning: {
      icon: 'ri:alarm-warning-fill',
      iconClass: 'bg-warning/12 text-warning',
      decoratorClass: 'decorator-warning'
    },
    danger: {
      icon: 'ri:error-warning-fill',
      iconClass: 'bg-danger/12 text-danger',
      decoratorClass: 'decorator-danger'
    },
    info: {
      icon: 'ri:notification-3-fill',
      iconClass: 'bg-theme/12 text-theme',
      decoratorClass: 'decorator-info'
    }
  }
  const getNoticeStyle = (item: NoticeListItem) => styleByLevel[item.level] ?? styleByLevel.info

  /**
   * 获取级别对应的 Element Plus Tag 类型
   */
  const getLevelTagType = (
    level?: string
  ): 'primary' | 'success' | 'warning' | 'danger' | 'info' => {
    switch (level) {
      case 'success':
        return 'success'
      case 'warning':
        return 'warning'
      case 'danger':
        return 'danger'
      default:
        return 'info'
    }
  }

  /**
   * 相对时间格式化（粗粒度，不引入 dayjs）
   */
  const formatTime = (raw?: string): string => {
    if (!raw) return ''
    const ts = new Date(raw.replace(' ', 'T')).getTime()
    if (Number.isNaN(ts)) return raw
    const diff = Date.now() - ts
    if (diff < 60_000) return '刚刚'
    if (diff < 3_600_000) return `${Math.floor(diff / 60_000)} 分钟前`
    if (diff < 86_400_000) return `${Math.floor(diff / 3_600_000)} 小时前`
    if (diff < 604_800_000) return `${Math.floor(diff / 86_400_000)} 天前`
    return raw
  }

  // 详情弹窗相关
  const safeContent = computed(() => sanitizeHtml(currentDetail.value?.content) || '<p>-</p>')

  /**
   * 拉取最新 20 条收件箱
   */
  const loadInbox = async (): Promise<void> => {
    try {
      const res = await fetchGetMyNoticeList({ page: 1, limit: 20 })
      allItems.value = res.list || []
    } catch {
      // 加载失败静默：避免顶栏因为一次失败弹错误
      allItems.value = []
    }
  }

  /**
   * 点击通知项：标记已读 + 打开详情弹窗
   */
  const handleItemClick = async (item: NoticeListItem): Promise<void> => {
    if (item.is_read === 0) {
      try {
        await fetchMarkNoticeRead(item.id)
        item.is_read = 1
      } catch {
        // 失败不影响查看详情
      }
    }
    // 关闭面板并打开详情弹窗
    emit('update:value', false)
    currentDetail.value = item
    detailVisible.value = true
  }

  /**
   * 前往通知链接
   */
  const handleGoToLink = (): void => {
    const link = currentDetail.value?.link
    if (!link) return
    detailVisible.value = false
    if (link.startsWith('/')) {
      safePush({ path: link })
    } else if (/^https?:/i.test(link)) {
      window.open(link, '_blank', 'noopener,noreferrer')
    }
  }

  /**
   * 全部已读
   */
  const handleReadAll = async (): Promise<void> => {
    try {
      await fetchMarkAllNoticeRead()
      ElMessage.success('已全部标记为已读')
      allItems.value.forEach((item) => (item.is_read = 1))
    } catch {
      // 错误信息由 axios 拦截器统一弹出
    }
  }

  /**
   * 查看全部 → 跳转到用户通知收件箱
   */
  const handleViewAll = (): void => {
    emit('update:value', false)
    safePush(
      { name: 'NoticeInbox' },
      { fallback: '/system/notice/inbox' }
    )
  }

  // 动画
  watch(
    () => props.value,
    (open) => {
      if (open) {
        visible.value = true
        setTimeout(() => {
          show.value = true
        }, 5)
        // 每次打开重新拉一次，保证数据新鲜
        loadInbox()
      } else {
        show.value = false
        setTimeout(() => {
          visible.value = false
        }, 350)
      }
    }
  )
</script>

<style scoped>
  @reference '@styles/core/tailwind.css';

  .art-notification-panel {
    @apply absolute
    top-14.5
    right-5
    w-90
    h-125
    overflow-hidden
    transition-all
    duration-300
    origin-top
    will-change-[top,left]
    max-[640px]:top-[65px]
    max-[640px]:right-0
    max-[640px]:w-full
    max-[640px]:h-[80vh];
  }

  .scrollbar-thin::-webkit-scrollbar {
    width: 5px !important;
  }

  .dark .scrollbar-thin::-webkit-scrollbar-track {
    background-color: var(--default-box-color);
  }

  .dark .scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: #222 !important;
  }

  /* ============ 通知详情弹窗 ============ */
  .notification-detail-dialog {
    :deep(.el-dialog) {
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 20px 60px -8px rgba(0, 0, 0, 0.16),
                  0 8px 24px -4px rgba(0, 0, 0, 0.1);
    }

    :deep(.el-dialog__header) {
      padding: 20px 28px 12px;
      margin-right: 0;
    }

    :deep(.el-dialog__title) {
      font-size: 16px;
      font-weight: 600;
      color: var(--el-text-color-primary);
    }

    :deep(.el-dialog__headerbtn) {
      top: 20px;
      right: 28px;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;

      &:hover {
        background: var(--el-fill-color-light);
      }
    }

    :deep(.el-dialog__body) {
      padding: 0;
      overflow: hidden;
    }
  }

  .notice-detail {
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, var(--el-fill-color-blank) 0%, var(--el-bg-color) 100%);
  }

  /* 头部 */
  .notice-detail-header {
    padding: 0 28px 20px;
  }

  .notice-detail-title-section {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
  }

  .notice-detail-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }

  .notice-detail-title-group {
    flex: 1;
    min-width: 0;
    padding-top: 2px;
  }

  .notice-detail-title {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 700;
    color: var(--el-text-color-primary);
    line-height: 1.4;
    word-break: break-word;
    letter-spacing: -0.2px;
  }

  .notice-detail-subtitle {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 13px;
    color: var(--el-text-color-secondary);

    .subtitle-item {
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
  }

  .notice-detail-tags {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding-left: 64px;

    .tag-item {
      border-radius: 6px;
      padding: 3px 10px;
      font-size: 12px;
      font-weight: 500;
    }
  }

  /* 分隔线 */
  .notice-detail-divider {
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
  .notice-detail-body {
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

  .notice-detail-content {
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

    :deep(table) {
      width: 100%;
      border-collapse: collapse;
      margin: 12px 0;
      font-size: 13px;

      th, td {
        padding: 8px 12px;
        border: 1px solid var(--el-border-color-lighter);
        text-align: left;
      }

      th {
        background: var(--el-fill-color-light);
        font-weight: 600;
      }

      tr:nth-child(even) {
        background: var(--el-fill-color-blank);
      }
    }
  }

  /* 底部 */
  .notice-detail-footer {
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
