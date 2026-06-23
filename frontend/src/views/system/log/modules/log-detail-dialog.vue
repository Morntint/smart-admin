<!-- 操作日志详情对话框 -->
<template>
  <ElDialog
    :model-value="visible"
    :title="`操作日志详情 #${detail?.id ?? ''}`"
    width="780px"
    align-center
    destroy-on-close
    @update:model-value="handleClose"
  >
    <ElDescriptions v-if="detail" :column="2" border size="small">
      <ElDescriptionsItem label="操作模块">
        {{ detail.module || '-' }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="操作行为">
        {{ detail.action || '-' }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="请求方法">
        <ElTag :type="methodTagType(detail.method)" size="small">
          {{ detail.method }}
        </ElTag>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="耗时"> {{ detail.duration ?? 0 }} ms </ElDescriptionsItem>
      <ElDescriptionsItem label="请求URL" :span="2">
        {{ detail.url || '-' }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="操作用户">
        {{ detail.username || '-' }}
        <span v-if="detail.user_id" class="user-id">(ID: {{ detail.user_id }})</span>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="IP地址">
        {{ detail.ip || '-' }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="状态">
        <ElTag :type="detail.status === 1 ? 'success' : 'danger'" size="small">
          {{ detail.status === 1 ? '正常' : '异常' }}
        </ElTag>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="操作时间">
        {{ detail.created_at || '-' }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="User-Agent" :span="2">
        <span class="user-agent">{{ detail.user_agent || '-' }}</span>
      </ElDescriptionsItem>
      <ElDescriptionsItem v-if="detail.error_msg" label="错误信息" :span="2">
        <span class="error-msg">{{ detail.error_msg }}</span>
      </ElDescriptionsItem>
    </ElDescriptions>

    <div v-if="detail" class="log-section">
      <div class="log-section-title">请求参数</div>
      <pre class="log-block">{{ formatted(paramObject) }}</pre>
    </div>

    <div v-if="detail" class="log-section">
      <div class="log-section-title">返回结果</div>
      <pre class="log-block">{{ formatted(resultObject) }}</pre>
    </div>

    <template #footer>
      <ElButton @click="handleClose">关闭</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElTag } from 'element-plus'

  type OperationLogListItem = Api.SystemManage.OperationLogListItem

  interface Props {
    visible: boolean
    detail: OperationLogListItem | null
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
  }

  const props = withDefaults(defineProps<Props>(), {
    visible: false,
    detail: null
  })

  const emit = defineEmits<Emits>()

  const handleClose = () => {
    emit('update:visible', false)
  }

  /**
   * 安全解析 JSON 字符串
   */
  const tryParse = (raw?: string | Record<string, unknown> | null) => {
    if (raw == null || raw === '') return null
    if (typeof raw === 'object') return raw
    try {
      return JSON.parse(raw)
    } catch {
      return raw
    }
  }

  const paramObject = computed(() => {
    return props.detail?.formatted_param ?? tryParse(props.detail?.param) ?? '-'
  })

  const resultObject = computed(() => {
    return tryParse(props.detail?.result) ?? '-'
  })

  const formatted = (value: unknown) => {
    if (value === null || value === undefined || value === '') return '-'
    if (typeof value === 'string') return value
    try {
      return JSON.stringify(value, null, 2)
    } catch {
      return String(value)
    }
  }

  /**
   * 不同请求方法对应的 Tag 颜色
   */
  const methodTagType = (
    method?: string
  ): 'success' | 'primary' | 'warning' | 'danger' | 'info' => {
    switch (method) {
      case 'GET':
        return 'success'
      case 'POST':
        return 'primary'
      case 'PUT':
        return 'warning'
      case 'DELETE':
        return 'danger'
      default:
        return 'info'
    }
  }
</script>

<style scoped lang="scss">
  .log-section {
    margin-top: 18px;
  }

  .log-section-title {
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--el-text-color-primary);
  }

  .log-block {
    max-height: 240px;
    padding: 12px 14px;
    margin: 0;
    overflow: auto;
    font-size: 12px;
    line-height: 1.7;
    background-color: var(--el-bg-color-page);
    border-radius: 6px;
    white-space: pre-wrap;
    word-wrap: break-word;
  }

  .user-id {
    margin-left: 4px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
  }

  .user-agent {
    display: inline-block;
    word-break: break-all;
  }

  .error-msg {
    color: var(--el-color-danger);
  }
</style>
