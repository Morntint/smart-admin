<!-- 系统通知详情对话框 -->
<template>
  <ElDialog
    :model-value="visible"
    @update:model-value="handleClose"
    :title="`通知详情 #${detail?.id ?? ''}`"
    width="640px"
    align-center
    destroy-on-close
  >
    <ElDescriptions v-if="detail" :column="2" border size="small">
      <ElDescriptionsItem label="通知标题" :span="2">
        <div class="detail-title">
          <ElTag :type="levelTagType" size="small">{{ levelText }}</ElTag>
          <span>{{ detail.title || '-' }}</span>
        </div>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="类型">{{ detail.type_text || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="级别">{{ detail.level_text || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="接收人">
        {{
          detail.user_nickname || detail.username || (detail.user_id ? `#${detail.user_id}` : '-')
        }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="发送人">
        {{ detail.sender_name || (detail.sender_id ? `#${detail.sender_id}` : '系统') }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="已读状态">
        <ElTag :type="detail.is_read === 1 ? 'success' : 'warning'" size="small">
          {{ detail.is_read === 1 ? '已读' : '未读' }}
        </ElTag>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="阅读时间">{{ detail.read_time || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="业务类型" :span="2">{{
        detail.biz_type || '-'
      }}</ElDescriptionsItem>
      <ElDescriptionsItem label="业务ID" :span="2">{{ detail.biz_id || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="跳转链接" :span="2">
        <span class="detail-link">{{ detail.link || '-' }}</span>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="过期时间">{{
        detail.expire_time || '永不过期'
      }}</ElDescriptionsItem>
      <ElDescriptionsItem label="创建时间">{{ detail.created_at || '-' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="通知内容" :span="2">
        <!-- 通知 content 是富文本（后端已 sanitize，前端再 sanitize 一道兜底防御） -->
        <div class="detail-content" v-html="safeContent" />
      </ElDescriptionsItem>
    </ElDescriptions>

    <template #footer>
      <ElButton @click="handleClose">关闭</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElTag } from 'element-plus'
  import { sanitizeHtml } from '@/utils/sanitize-html'

  type NoticeListItem = Api.SystemManage.NoticeListItem

  interface Props {
    visible: boolean
    detail: NoticeListItem | null
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
  }

  const props = withDefaults(defineProps<Props>(), {
    visible: false,
    detail: null
  })

  const emit = defineEmits<Emits>()

  const safeContent = computed(() => sanitizeHtml(props.detail?.content) || '<p>-</p>')

  const levelTagType = computed<'primary' | 'success' | 'warning' | 'danger' | 'info'>(() => {
    switch (props.detail?.level) {
      case 'success':
        return 'success'
      case 'warning':
        return 'warning'
      case 'danger':
        return 'danger'
      default:
        return 'info'
    }
  })

  const levelText = computed(() => {
    switch (props.detail?.level) {
      case 'success':
        return '成功'
      case 'warning':
        return '警告'
      case 'danger':
        return '严重'
      default:
        return '普通'
    }
  })

  const handleClose = (): void => {
    emit('update:visible', false)
  }
</script>

<style scoped lang="scss">
  .detail-title {
    display: inline-flex;
    gap: 8px;
    align-items: center;
  }

  .detail-link {
    word-break: break-all;
  }

  .detail-content {
    max-height: 320px;
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
