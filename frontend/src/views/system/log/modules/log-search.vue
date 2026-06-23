<template>
  <ArtSearchBar
    ref="searchBarRef"
    v-model="formData"
    :items="formItems"
    :rules="rules"
    @reset="handleReset"
    @search="handleSearch"
  >
  </ArtSearchBar>
</template>

<script setup lang="ts">
  export interface OperationLogSearchParams {
    keyword?: string
    module?: string
    method?: string
    status?: string
    start_date?: string
    end_date?: string
    daterange?: string[]
  }

  interface Props {
    modelValue: OperationLogSearchParams
  }

  interface Emits {
    (e: 'update:modelValue', value: OperationLogSearchParams): void
    (e: 'search', params: OperationLogSearchParams): void
    (e: 'reset'): void
  }

  const props = defineProps<Props>()
  const emit = defineEmits<Emits>()

  const searchBarRef = ref()

  /**
   * 表单数据双向绑定
   */
  const formData = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

  /**
   * 表单校验规则
   */
  const rules = {}

  /**
   * 状态选项（与后端一致：1=正常, 0=异常）
   */
  const statusOptions = [
    { label: '正常', value: '1' },
    { label: '异常', value: '0' }
  ]

  /**
   * 请求方法选项（与后端 OperationLog 中间件捕获范围一致）
   */
  const methodOptions = [
    { label: 'POST', value: 'POST' },
    { label: 'PUT', value: 'PUT' },
    { label: 'PATCH', value: 'PATCH' },
    { label: 'DELETE', value: 'DELETE' }
  ]

  /**
   * 搜索表单配置项
   */
  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      placeholder: '搜索用户名/请求URL',
      clearable: true
    },
    {
      label: '操作模块',
      key: 'module',
      type: 'input',
      placeholder: '请输入操作模块',
      clearable: true
    },
    {
      label: '请求方法',
      key: 'method',
      type: 'select',
      props: {
        placeholder: '请选择请求方法',
        options: methodOptions,
        clearable: true
      }
    },
    {
      label: '操作状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择操作状态',
        options: statusOptions,
        clearable: true
      }
    },
    {
      label: '操作日期',
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

  /**
   * 处理重置事件
   */
  const handleReset = () => {
    emit('reset')
  }

  /**
   * 处理搜索事件
   * 拆分 daterange 为 start_date/end_date，符合后端接口约定
   */
  const handleSearch = async (params: OperationLogSearchParams) => {
    await searchBarRef.value?.validate?.()
    const { daterange, ...rest } = params
    const payload: OperationLogSearchParams = { ...rest }
    if (Array.isArray(daterange) && daterange.length === 2) {
      payload.start_date = daterange[0]
      payload.end_date = daterange[1]
    } else {
      payload.start_date = undefined
      payload.end_date = undefined
    }
    emit('search', payload)
  }
</script>
