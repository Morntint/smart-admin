<!-- 公告滚动通知条 -->
<template>
  <Transition name="fade-slide">
    <div v-show="visible && announcementList.length > 0" class="announcement-bar">
      <ArtTextScroll
        :text="scrollText"
        :type="scrollType"
        :speed="60"
        :height="'36px'"
        :show-close="true"
        class="announcement-scroll"
        @close="handleClose"
      />
    </div>
  </Transition>
</template>

<script setup lang="ts">
  import { fetchGetActiveAnnouncementList } from '@/api/system-manage'
  import { useLocalStorage } from '@vueuse/core'

  defineOptions({ name: 'ArtAnnouncementBar' })

  type AnnouncementListItem = Api.SystemManage.AnnouncementListItem

  const announcementList = ref<AnnouncementListItem[]>([])
  const visible = ref(false)

  // 已关闭的公告ID（当天有效）
  const closedAnnouncementIds = useLocalStorage<{ ids: number[]; date: string }>(
    'closed_announcement_ids',
    { ids: [], date: '' }
  )

  // 滚动显示的文本
  const scrollText = computed(() => {
    return announcementList.value.map((item) => {
      const levelText = getLevelText(item.level)
      return `【${levelText}】${item.title}`
    }).join('   ')
  })

  // 根据最高级别确定滚动条类型
  const scrollType = computed(() => {
    if (announcementList.value.length === 0) return 'theme'
    const levels = announcementList.value.map((item) => item.level)
    if (levels.includes('urgent')) return 'danger'
    if (levels.includes('important')) return 'warning'
    return 'theme'
  })

  /**
   * 获取级别文本
   */
  const getLevelText = (level: string): string => {
    switch (level) {
      case 'important':
        return '重要'
      case 'urgent':
        return '紧急'
      default:
        return '公告'
    }
  }

  /**
   * 加载置顶公告
   */
  const loadTopAnnouncements = async (): Promise<void> => {
    try {
      const list = await fetchGetActiveAnnouncementList({ limit: 50 })

      // 筛选 is_top = 1 的公告
      announcementList.value = list
        .filter((item) => item.is_top === 1)
        .sort((a, b) => {
          // 按级别优先级：紧急 > 重要 > 普通
          const levelPriority = { urgent: 0, important: 1, info: 2 }
          const priorityA = levelPriority[a.level as keyof typeof levelPriority] ?? 2
          const priorityB = levelPriority[b.level as keyof typeof levelPriority] ?? 2

          if (priorityA !== priorityB) return priorityA - priorityB
          if (a.sort !== b.sort) return a.sort - b.sort
          return new Date(b.published_at || 0).getTime() - new Date(a.published_at || 0).getTime()
        })

      checkVisibility()
    } catch {
      // 静默失败
    }
  }

  /**
   * 检查是否应该显示滚动条
   */
  const checkVisibility = (): void => {
    const today = new Date().toDateString()
    const isSameDay = closedAnnouncementIds.value.date === today

    if (!isSameDay) {
      // 新的一天，重置已关闭列表
      closedAnnouncementIds.value = { ids: [], date: today }
    }

    const unclosedList = announcementList.value.filter(
      (item) => !closedAnnouncementIds.value.ids.includes(item.id)
    )

    visible.value = unclosedList.length > 0
  }

  /**
   * 关闭滚动通知（当天不再显示）
   */
  const handleClose = (): void => {
    const today = new Date().toDateString()

    if (closedAnnouncementIds.value.date !== today) {
      closedAnnouncementIds.value = { ids: [], date: today }
    }

    announcementList.value.forEach((item) => {
      if (!closedAnnouncementIds.value.ids.includes(item.id)) {
        closedAnnouncementIds.value.ids.push(item.id)
      }
    })

    visible.value = false
  }

  // 暴露方法给外部调用
  defineExpose({
    loadTopAnnouncements
  })
</script>

<style scoped lang="scss">
  .fade-slide-enter-active,
  .fade-slide-leave-active {
    transition: all 0.2s ease-out;
  }

  .fade-slide-enter-from {
    opacity: 0;
    transform: translateY(-10px);
  }

  .fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-5px);
  }

  .announcement-bar {
    width: 100%;
    padding: 8px 16px;
    box-sizing: border-box;
    overflow: hidden;
  }

  .announcement-bar :deep(.rounded-custom-sm) {
    border-radius: 8px;
  }
</style>
