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
  export interface LoginLogSearchParams {
    keyword?: string
    status?: string
    login_type?: string
    start_date?: string
    end_date?: string
    daterange?: string[]
  }

  interface Props {
    modelValue: LoginLogSearchParams
  }

  interface Emits {
    (e: 'update:modelValue', value: LoginLogSearchParams): void
    (e: 'search', params: LoginLogSearchParams): void
    (e: 'reset'): void
  }

  const props = defineProps<Props>()
  const emit = defineEmits<Emits>()

  const searchBarRef = ref()

  const formData = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

  const rules = {}

  /**
   * 登录状态（与后端 SysLoginLog 一致：1=成功, 0=失败）
   */
  const statusOptions = [
    { label: '成功', value: '1' },
    { label: '失败', value: '0' }
  ]

  /**
   * 登录类型（与后端 SysLoginLog 一致：1=登录, 2=登出）
   */
  const loginTypeOptions = [
    { label: '登录', value: '1' },
    { label: '登出', value: '2' }
  ]

  const formItems = computed(() => [
    {
      label: '用户名',
      key: 'keyword',
      type: 'input',
      placeholder: '请输入用户名',
      clearable: true
    },
    {
      label: '登录类型',
      key: 'login_type',
      type: 'select',
      props: {
        placeholder: '请选择登录类型',
        options: loginTypeOptions,
        clearable: true
      }
    },
    {
      label: '登录状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择登录状态',
        options: statusOptions,
        clearable: true
      }
    },
    {
      label: '登录日期',
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

  const handleReset = () => {
    emit('reset')
  }

  const handleSearch = async (params: LoginLogSearchParams) => {
    await searchBarRef.value?.validate?.()
    const { daterange, ...rest } = params
    const payload: LoginLogSearchParams = { ...rest }
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
