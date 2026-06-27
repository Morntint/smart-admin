<!-- 系统通知搜索栏 -->
<template>
  <ArtSearchBar
    ref="searchBarRef"
    v-model="formData"
    :items="formItems"
    :rules="{}"
    @reset="handleReset"
    @search="handleSearch"
  />
</template>

<script setup lang="ts">
  export interface NoticeSearchParams {
    keyword?: string
    type?: string
    level?: string
    is_read?: string
    user_id?: number
    start_date?: string
    end_date?: string
    daterange?: string[]
  }

  interface Props {
    modelValue: NoticeSearchParams
  }

  interface Emits {
    (e: 'update:modelValue', value: NoticeSearchParams): void
    (e: 'search', params: NoticeSearchParams): void
    (e: 'reset'): void
  }

  const props = defineProps<Props>()
  const emit = defineEmits<Emits>()

  const searchBarRef = ref()

  const formData = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: { placeholder: '搜索标题/内容', clearable: true }
    },
    {
      label: '类型',
      key: 'type',
      type: 'select',
      props: {
        placeholder: '请选择通知类型',
        clearable: true,
        options: [
          { label: '系统通知', value: '1' },
          { label: '待办', value: '2' },
          { label: '预警', value: '3' },
          { label: '个人消息', value: '4' }
        ]
      }
    },
    {
      label: '级别',
      key: 'level',
      type: 'select',
      props: {
        placeholder: '请选择级别',
        clearable: true,
        options: [
          { label: '普通', value: 'info' },
          { label: '成功', value: 'success' },
          { label: '警告', value: 'warning' },
          { label: '严重', value: 'danger' }
        ]
      }
    },
    {
      label: '已读',
      key: 'is_read',
      type: 'select',
      props: {
        placeholder: '请选择已读状态',
        clearable: true,
        options: [
          { label: '已读', value: '1' },
          { label: '未读', value: '0' }
        ]
      }
    },
    {
      label: '日期范围',
      key: 'daterange',
      type: 'datetime',
      props: {
        style: { width: '100%' },
        type: 'daterange',
        rangeSeparator: '至',
        startPlaceholder: '开始日期',
        endPlaceholder: '结束日期',
        valueFormat: 'YYYY-MM-DD',
        shortcuts: [
          { text: '今日', value: [new Date(), new Date()] },
          { text: '最近一周', value: [new Date(Date.now() - 604800000), new Date()] },
          { text: '最近一个月', value: [new Date(Date.now() - 2592000000), new Date()] }
        ]
      }
    }
  ])

  const handleReset = (): void => {
    emit('reset')
  }

  const handleSearch = async (params: NoticeSearchParams): Promise<void> => {
    await searchBarRef.value?.validate?.()
    emit('search', params)
  }
</script>
