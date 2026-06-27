<!-- 公告强提示弹窗 -->
<template>
  <ElDialog
    v-model="innerVisible"
    :title="null"
    width="520px"
    :close-on-click-modal="false"
    :close-on-press-escape="false"
    destroy-on-close
    class="announcement-popup-dialog"
    @close="handleClose"
  >
    <template #header>
      <div class="announcement-header">
        <div class="announcement-icon-wrapper" :class="iconClass">
          <ArtSvgIcon :icon="iconName" class="announcement-icon" />
        </div>
        <div class="announcement-header-content">
          <h3 class="announcement-title">{{ currentAnnouncement?.title || '系统公告' }}</h3>
          <div class="announcement-subtitle">
            <ElTag :type="getLevelTagType(currentAnnouncement?.level)" size="small">
              {{ getLevelText(currentAnnouncement?.level) }}
            </ElTag>
            <span class="meta-item">
              <ArtSvgIcon icon="ri:time-line" class="meta-icon" />
              {{ formatTime(currentAnnouncement?.published_at) }}
            </span>
            <span v-if="currentAnnouncement?.publisher_name" class="meta-item">
              <ArtSvgIcon icon="ri:user-line" class="meta-icon" />
              {{ currentAnnouncement.publisher_name }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <div class="announcement-body" v-html="currentAnnouncement?.content"></div>

    <template #footer>
      <div class="announcement-footer">
        <ElButton type="primary" @click="handleClose" v-ripple class="confirm-btn">
          我已知晓
        </ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { fetchGetActiveAnnouncementList } from '@/api/system-manage'
  import { useLocalStorage } from '@vueuse/core'
  import { ElTag, ElButton } from 'element-plus'

  defineOptions({ name: 'ArtAnnouncementPopup' })

  type AnnouncementListItem = Api.SystemManage.AnnouncementListItem

  const innerVisible = ref(false)
  const currentAnnouncement = ref<AnnouncementListItem | null>(null)

  // 已阅读的公告ID列表（持久化存储，避免每次登录都弹）
  const readAnnouncementIds = useLocalStorage<number[]>('read_announcement_ids', [])

  /**
   * 获取级别对应的图标和颜色
   */
  const iconName = computed(() => {
    const level = currentAnnouncement.value?.level
    switch (level) {
      case 'urgent':
        return 'ri:alarm-warning-fill'
      case 'important':
        return 'ri:notification-3-fill'
      default:
        return 'ri:megaphone-fill'
    }
  })

  const iconClass = computed(() => {
    const level = currentAnnouncement.value?.level
    switch (level) {
      case 'urgent':
        return 'icon-danger'
      case 'important':
        return 'icon-warning'
      default:
        return 'icon-info'
    }
  })

  /**
   * 获取级别对应的标签颜色
   */
  const getLevelTagType = (level?: string): 'info' | 'warning' | 'danger' => {
    switch (level) {
      case 'important':
        return 'warning'
      case 'urgent':
        return 'danger'
      default:
        return 'info'
    }
  }

  /**
   * 获取级别文本
   */
  const getLevelText = (level?: string): string => {
    switch (level) {
      case 'important':
        return '重要公告'
      case 'urgent':
        return '紧急通知'
      default:
        return '系统公告'
    }
  }

  /**
   * 格式化时间
   */
  const formatTime = (time?: string): string => {
    if (!time) return ''
    return time.split(' ')[0]
  }

  /**
   * 检查并显示需要弹窗的公告
   */
  const checkAndShowPopup = async (): Promise<void> => {
    try {
      // 获取所有有效公告（限制20条足够）
      const list = await fetchGetActiveAnnouncementList({ limit: 20 })

      // 筛选 is_popup = 1 且未阅读的公告
      const popupList = list.filter(
        (item) => item.is_popup === 1 && !readAnnouncementIds.value.includes(item.id)
      )

      if (popupList.length > 0) {
        // 按置顶优先、排序号、发布时间排序
        popupList.sort((a, b) => {
          if (a.is_top !== b.is_top) {
            return b.is_top - a.is_top
          }
          if (a.sort !== b.sort) {
            return a.sort - b.sort
          }
          return new Date(b.published_at || 0).getTime() - new Date(a.published_at || 0).getTime()
        })

        // 显示优先级最高的公告
        currentAnnouncement.value = popupList[0]
        innerVisible.value = true
      }
    } catch {
      // 静默失败，不影响正常使用
    }
  }

  /**
   * 关闭弹窗
   */
  const handleClose = (): void => {
    if (currentAnnouncement.value) {
      // 标记为已阅读
      if (!readAnnouncementIds.value.includes(currentAnnouncement.value.id)) {
        readAnnouncementIds.value.push(currentAnnouncement.value.id)
      }
      currentAnnouncement.value = null
    }
    innerVisible.value = false
  }

  // 暴露方法给外部调用
  defineExpose({
    checkAndShowPopup
  })
</script>

<style scoped lang="scss">
  .announcement-popup-dialog {
    :deep(.el-dialog) {
      border-radius: 12px;
      box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.08),
        0 2px 8px rgba(0, 0, 0, 0.04);
      overflow: hidden;
    }

    :deep(.el-dialog__header) {
      padding: 0;
      margin: 0;
    }

    :deep(.el-dialog__body) {
      padding: 0 24px 16px;
    }

    :deep(.el-dialog__footer) {
      padding: 12px 24px 24px;
    }

    :deep(.el-dialog__close) {
      top: 20px;
      right: 20px;
      color: var(--el-text-color-secondary);
      transition: all 0.2s ease;
      border-radius: 6px;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;

      &:hover {
        color: var(--el-text-color-primary);
        background: var(--el-fill-color-light);
      }
    }
  }

  .announcement-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 24px 20px;
    background: linear-gradient(180deg, var(--el-fill-color-lighter) 0%, transparent 100%);
    border-bottom: 1px solid var(--el-border-color-lighter);
  }

  .announcement-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &.icon-info {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    &.icon-warning {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    &.icon-danger {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    }
  }

  .announcement-icon {
    font-size: 22px;
    color: #fff;
  }

  .announcement-header-content {
    flex: 1;
    min-width: 0;
  }

  .announcement-title {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 600;
    line-height: 1.4;
    color: var(--el-text-color-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    letter-spacing: -0.01em;
  }

  .announcement-subtitle {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 13px;
    color: var(--el-text-color-secondary);
  }

  .meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .meta-icon {
    font-size: 14px;
  }

  .announcement-body {
    max-height: 320px;
    padding: 16px 0;
    overflow-y: auto;
    font-size: 14px;
    line-height: 1.8;
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
      border-radius: 8px;
      margin: 12px 0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    :deep(a) {
      color: var(--el-color-primary);
      text-decoration: none;
      font-weight: 500;
      transition: opacity 0.2s;

      &:hover {
        opacity: 0.8;
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
      margin: 20px 0 10px;
      color: var(--el-text-color-primary);
      font-weight: 600;
    }

    :deep(h1) {
      font-size: 20px;
    }

    :deep(h2) {
      font-size: 18px;
    }

    :deep(h3) {
      font-size: 16px;
    }

    :deep(blockquote) {
      margin: 16px 0;
      padding: 12px 16px;
      background: var(--el-fill-color-lighter);
      border-left: 4px solid var(--el-color-primary);
      border-radius: 0 8px 8px 0;
      color: var(--el-text-color-secondary);
    }

    :deep(code) {
      padding: 2px 6px;
      background: var(--el-fill-color-lighter);
      border-radius: 4px;
      font-size: 13px;
      color: var(--el-color-danger);
    }

    :deep(pre) {
      margin: 16px 0;
      padding: 16px;
      background: var(--el-fill-color-lighter);
      border-radius: 8px;
      overflow-x: auto;

      code {
        padding: 0;
        background: transparent;
        color: inherit;
      }
    }

    // 自定义滚动条
    &::-webkit-scrollbar {
      width: 6px;
    }

    &::-webkit-scrollbar-track {
      background: transparent;
    }

    &::-webkit-scrollbar-thumb {
      background: var(--el-border-color);
      border-radius: 3px;

      &:hover {
        background: var(--el-border-color-dark);
      }
    }
  }

  .announcement-footer {
    display: flex;
    justify-content: center;
    padding-top: 16px;
    border-top: 1px solid var(--el-border-color-lighter);

    .confirm-btn {
      width: 160px;
      height: 40px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
    }
  }
</style>
