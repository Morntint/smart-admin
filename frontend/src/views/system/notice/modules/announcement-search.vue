<!-- 系统公告搜索栏 -->
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
  export interface AnnouncementSearchParams {
    keyword?: string
    category?: string
    level?: string
    status?: string
    is_top?: string
    start_date?: string
    end_date?: string
    daterange?: string[]
  }

  interface Props {
    modelValue: AnnouncementSearchParams
  }

  interface Emits {
    (e: 'update:modelValue', value: AnnouncementSearchParams): void
    (e: 'search', params: AnnouncementSearchParams): void
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
      props: { placeholder: '搜索标题/备注', clearable: true }
    },
    {
      label: '分类',
      key: 'category',
      type: 'select',
      props: {
        placeholder: '请选择分类',
        clearable: true,
        options: [
          { label: '通知', value: 'notice' },
          { label: '公告', value: 'announcement' },
          { label: '活动', value: 'activity' },
          { label: '维护', value: 'maintenance' }
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
          { label: '重要', value: 'important' },
          { label: '紧急', value: 'urgent' }
        ]
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择状态',
        clearable: true,
        options: [
          { label: '草稿', value: '0' },
          { label: '已发布', value: '1' },
          { label: '已下线', value: '2' }
        ]
      }
    },
    {
      label: '置顶',
      key: 'is_top',
      type: 'select',
      props: {
        placeholder: '请选择是否置顶',
        clearable: true,
        options: [
          { label: '置顶', value: '1' },
          { label: '非置顶', value: '0' }
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

  const handleReset = (): void => emit('reset')

  const handleSearch = async (params: AnnouncementSearchParams): Promise<void> => {
    await searchBarRef.value?.validate?.()
    emit('search', params)
  }
</script>
