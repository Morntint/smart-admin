<!-- 系统公告详情对话框（管理端） -->
<template>
  <ElDialog
    :model-value="visible"
    @update:model-value="handleClose"
    :title="`公告详情 #${detail?.id ?? ''}`"
    width="720px"
    align-center
    destroy-on-close
  >
    <ElDescriptions v-if="detail" :column="2" border size="small">
      <ElDescriptionsItem label="公告标题" :span="2">
        <div class="detail-title">
          <ElTag v-if="detail.is_top === 1" type="danger" size="small">置顶</ElTag>
          <ElTag v-if="detail.is_popup === 1" type="warning" size="small">弹窗</ElTag>
          <ElTag :type="levelTagType" size="small">{{ levelText }}</ElTag>
          <span>{{ detail.title || '-' }}</span>
        </div>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="分类">{{ detail.category_text || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="级别">{{ detail.level_text || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="状态">
        <ElTag :type="statusTagType" size="small">{{ statusText }}</ElTag>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="阅读量">{{ detail.view_count ?? 0 }}</ElDescriptionsItem>
      <ElDescriptionsItem label="生效时间">{{
        detail.effective_at || '立即生效'
      }}</ElDescriptionsItem>
      <ElDescriptionsItem label="失效时间">{{ detail.expire_at || '长期有效' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="发布人">
        {{ detail.publisher_name || (detail.publisher_id ? `#${detail.publisher_id}` : '-') }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="发布时间">{{ detail.published_at || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="排序">{{ detail.sort ?? 0 }}</ElDescriptionsItem>
      <ElDescriptionsItem label="创建时间">{{ detail.created_at || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="备注" :span="2">{{ detail.remark || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="公告内容" :span="2">
        <!-- 公告 content 富文本：后端已 sanitize，前端再走 sanitize 兜底 -->
        <div class="detail-content" v-html="safeContent" />
      </ElDescriptionsItem>
    </ElDescriptions>

    <template #footer>
      <ElButton
        v-if="detail && hasAuth('system:announcement:edit')"
        type="primary"
        @click="handleEdit"
      >
        编 辑
      </ElButton>
      <ElButton @click="handleClose">关闭</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElTag } from 'element-plus'
  import { useAuth } from '@/hooks/core/useAuth'
  import { sanitizeHtml } from '@/utils/sanitize-html'

  type AnnouncementListItem = Api.SystemManage.AnnouncementListItem

  interface Props {
    visible: boolean
    detail: AnnouncementListItem | null
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'edit', row: AnnouncementListItem): void
  }

  const props = withDefaults(defineProps<Props>(), { visible: false, detail: null })
  const emit = defineEmits<Emits>()
  const { hasAuth } = useAuth()

  const safeContent = computed(() => sanitizeHtml(props.detail?.content) || '<p>-</p>')

  const levelTagType = computed<'primary' | 'warning' | 'danger' | 'info'>(() => {
    switch (props.detail?.level) {
      case 'important':
        return 'warning'
      case 'urgent':
        return 'danger'
      default:
        return 'info'
    }
  })

  const levelText = computed(() => {
    switch (props.detail?.level) {
      case 'important':
        return '重要'
      case 'urgent':
        return '紧急'
      default:
        return '普通'
    }
  })

  const statusTagType = computed<'success' | 'info' | 'warning'>(() => {
    switch (props.detail?.status) {
      case 1:
        return 'success'
      case 2:
        return 'warning'
      default:
        return 'info'
    }
  })

  const statusText = computed(() => {
    switch (props.detail?.status) {
      case 1:
        return '已发布'
      case 2:
        return '已下线'
      default:
        return '草稿'
    }
  })

  const handleClose = (): void => emit('update:visible', false)

  const handleEdit = (): void => {
    if (props.detail) {
      emit('edit', props.detail)
    }
  }
</script>

<style scoped lang="scss">
  .detail-title {
    display: inline-flex;
    gap: 6px;
    align-items: center;
  }

  .detail-content {
    max-height: 360px;
    padding: 12px 14px;
    margin: 0;
    overflow: auto;
    font-size: 13px;
    line-height: 1.7;
    background-color: var(--el-bg-color-page);
    border-radius: 6px;

    :deep(p) {
      margin: 0 0 8px;
      &:last-child {
        margin-bottom: 0;
      }
    }
    :deep(img) {
      max-width: 100%;
      height: auto;
    }
  }
</style>
