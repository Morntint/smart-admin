<!--
  简易表格组件 - 快速开发场景

  适用场景：仅需展示列表 + 搜索 + 分页，不需要复杂的工具栏或自定义操作
  特性：
    1. 集成搜索栏（ArtSearchBar）+ 表格（ArtTable）+ 表格头（ArtTableHeader）
    2. 内部使用 useTable 管理数据/分页/缓存
    3. 通过 columns + searchItems 配置即可使用
    4. 支持工具栏插槽、自定义列插槽
    5. 内置 ElCard 容器，自动撑满父级高度

  用法示例：
    <ArtSimpleTable
      :api-fn="fetchGetList"
      :columns="columns"
      :search-items="searchItems"
      @selection-change="onSelect"
    >
      <template #toolbar>
        <ElButton>新增</ElButton>
      </template>
    </ArtSimpleTable>
-->
<template>
  <div class="art-simple-table art-full-height">
    <!-- 搜索栏 -->
    <ArtSearchBar
      v-if="searchItems.length"
      v-model="searchForm"
      :items="searchItems"
      @search="handleSearch"
      @reset="handleReset"
    />

    <!-- 表格卡片 -->
    <ElCard class="art-table-card" :style="{ 'margin-top': searchItems.length ? '12px' : '0' }">
      <ArtTableHeader
        v-model:columns="columnsModel"
        v-model:showSearchBar="showSearchBar"
        :loading="loading"
        @refresh="refreshData"
      >
        <template #left>
          <ElSpace wrap>
            <slot name="toolbar" />
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="data"
        :columns="tableColumns"
        :pagination="pagination"
        @selection-change="handleSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      >
        <!-- 透传自定义列插槽 -->
        <template v-for="(_, name) in $slots" :key="name" #[name]="slotScope">
          <slot :name="name" v-bind="slotScope" />
        </template>
      </ArtTable>
    </ElCard>
  </div>
</template>

<script setup lang="ts" generic="T extends Record<string, any>">
  import { useTable } from '@/hooks/core/useTable'
  import type { ColumnOption } from '@/types'
  import type { SearchFormItem } from '@/components/core/forms/art-search-bar/index.vue'

  defineOptions({ name: 'ArtSimpleTable' })

  /**
   * 搜索表单项配置
   */
  export interface SimpleTableSearchItem extends Omit<SearchFormItem, 'key'> {
    /** 表单项 key，同时作为提交到后端时的字段名 */
    key: string
  }

  /**
   * 简易表格 Props
   * @template T 单行数据类型
   */
  interface Props<T extends Record<string, any>> {
    /** API 请求函数，必须返回分页数据 { list, total, page, limit } */
    apiFn: (params: any) => Promise<{ list: T[]; total: number; page: number; limit: number }>
    /** 表格列配置（与 useTable 的 columnsFactory 一致） */
    columns: ColumnOption<T>[]
    /** 搜索表单项配置（不传则不显示搜索栏） */
    searchItems?: SimpleTableSearchItem[]
    /** 默认请求参数（与 useTable 的 apiParams 一致） */
    defaultParams?: Record<string, any>
    /** 自定义分页字段映射（与后端约定 page/limit 或 current/size） */
    paginationKey?: { current?: string; size?: string }
    /** 默认每页条数 */
    pageSize?: number
    /** 是否在挂载时立即加载 */
    immediate?: boolean
  }

  const props = withDefaults(defineProps<Props<T>>(), {
    searchItems: () => [],
    defaultParams: () => ({}),
    paginationKey: () => ({ current: 'page', size: 'limit' }),
    pageSize: 20,
    immediate: true
  })

  const emit = defineEmits<{
    (e: 'selection-change', rows: T[]): void
    (e: 'row-click', row: T): void
    (e: 'refresh'): void
  }>()

  /** 搜索表单数据 - 通过 v-model 双向绑定 ArtSearchBar */
  const searchForm = ref<Record<string, any>>({})

  /** 是否显示搜索栏 - 由 ArtTableHeader 控制 */
  const showSearchBar = ref(true)

  /** 搜索项默认值快照 - 用于重置时恢复 */
  const defaultSearchForm = computed(() => {
    const init: Record<string, any> = {}
    for (const item of props.searchItems) {
      if (item.props?.defaultValue !== undefined) {
        init[item.key] = item.props.defaultValue
      } else if (item.type === 'daterange' || item.type === 'datetimerange') {
        init[item.key] = []
      } else {
        init[item.key] = ''
      }
    }
    return init
  })

  // 初始化 searchForm
  searchForm.value = { ...defaultSearchForm.value }

  // 合并列配置：自动加上序号列和选择列
  const mergedColumns = computed<ColumnOption<T>[]>(() => props.columns)

  /**
   * useTable - 接管数据请求、分页、刷新
   */
  const {
    columns: tableColumns,
    columnChecks,
    data,
    loading,
    pagination,
    handleSizeChange,
    handleCurrentChange,
    refreshData,
    getData,
    replaceSearchParams,
    resetSearchParams: _resetSearchParams
  } = useTable({
    core: {
      apiFn: props.apiFn as any,
      apiParams: {
        ...props.defaultParams,
        page: 1,
        limit: props.pageSize
      },
      immediate: props.immediate,
      paginationKey: props.paginationKey,
      columnsFactory: () => mergedColumns.value
    }
  })

  // 把 columnChecks 暴露为 v-model:columns 绑定的可写 columns
  // ArtTableHeader 通过 v-model:columns 修改列配置
  const columnsModel = columnChecks

  /**
   * 搜索：替换参数后回到第一页拉取数据
   * 简易实现：直接传表单值，daterange 类字段也透传
   * 业务侧可在 searchItems 的 props.valueFormat 中约束日期格式
   */
  const handleSearch = (form: Record<string, any>) => {
    Object.assign(searchForm.value, form)
    replaceSearchParams(form)
    getData()
  }

  /**
   * 重置：恢复搜索表单默认值，重新拉取第一页
   */
  const handleReset = () => {
    searchForm.value = { ...defaultSearchForm.value }
    _resetSearchParams()
  }

  /** 选中行变化 */
  const handleSelectionChange = (rows: T[]) => {
    emit('selection-change', rows)
  }

  // 暴露给父组件的方法/状态
  defineExpose({
    refresh: refreshData,
    data,
    searchForm,
    pagination
  })
</script>

<style scoped lang="scss">
  .art-simple-table {
    /* 撑满父级（art-full-height） */
  }
</style>
