<!-- 字典数据搜索栏 -->
<template>
  <ArtSearchBar
    ref="searchBarRef"
    v-model="formData"
    :items="formItems"
    :show-expand="false"
    @reset="handleReset"
    @search="handleSearch"
  >
  </ArtSearchBar>
</template>

<script setup lang="ts">
  interface DictDataSearchParams {
    label?: string
    value?: string
  }

  interface Props {
    modelValue: DictDataSearchParams
  }

  interface Emits {
    (e: 'update:modelValue', value: DictDataSearchParams): void
    (e: 'search', params: DictDataSearchParams): void
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
   * 搜索表单配置项
   */
  const formItems = computed(() => [
    {
      label: '数据标签',
      key: 'label',
      type: 'input',
      placeholder: '请输入数据标签',
      clearable: true
    },
    {
      label: '数据键值',
      key: 'value',
      type: 'input',
      placeholder: '请输入数据键值',
      clearable: true
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
   */
  const handleSearch = (params: DictDataSearchParams) => {
    emit('search', params)
  }
</script>
