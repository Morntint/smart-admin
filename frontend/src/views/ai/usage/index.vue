<!-- AI 用量统计页面 -->
<template>
  <div class="ai-usage-page art-full-height">
    <!-- 汇总卡片 -->
    <ElRow :gutter="16" class="summary-row">
      <ElCol :span="6">
        <ElCard shadow="hover" class="stat-card">
          <ElStatistic title="总调用次数" :value="summary.total_calls">
            <template #suffix>次</template>
          </ElStatistic>
        </ElCard>
      </ElCol>
      <ElCol :span="6">
        <ElCard shadow="hover" class="stat-card">
          <ElStatistic title="总 Token" :value="summary.total_tokens">
            <template #suffix>tokens</template>
          </ElStatistic>
        </ElCard>
      </ElCol>
      <ElCol :span="6">
        <ElCard shadow="hover" class="stat-card">
          <ElStatistic title="总费用" :value="summary.total_cost" :precision="4">
            <template #suffix>$</template>
          </ElStatistic>
        </ElCard>
      </ElCol>
      <ElCol :span="6">
        <ElCard shadow="hover" class="stat-card">
          <ElStatistic title="平均响应">
            <template #value>
              {{ formatDuration(summary.avg_duration) }}
            </template>
          </ElStatistic>
        </ElCard>
      </ElCol>
    </ElRow>

    <ArtSearchBar
      v-model="formFilters"
      :items="formItems"
      :show-expand="false"
      @reset="handleReset"
      @search="handleSearch"
    />

    <ElCard class="art-table-card">
      <ArtTableHeader
        :loading="loading"
        v-model:columns="columnChecks"
        @refresh="refreshData"
      >
        <template #left>
          <span class="dict-data-title">详细调用记录 · {{ pagination.total }}</span>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { ElTag } from 'element-plus'
  import { useTable } from '@/hooks/core/useTable'
  import { fetchGetUsageList, fetchGetUsageSummary } from '@/api/ai-manage'

  defineOptions({ name: 'AiUsage' })

  type UsageItem = {
    id: number
    user_id: number
    model_name: string
    endpoint: string
    total_tokens: number
    cost: number
    duration: number
    status: number
    created_at: string
  }

  const summary = ref<{
    total_calls: number
    total_tokens: number
    total_cost: number
    avg_duration: number
    by_model: any[]
  }>({
    total_calls: 0,
    total_tokens: 0,
    total_cost: 0,
    avg_duration: 0,
    by_model: []
  })

  const formFilters = reactive({
    keyword: '',
    model_name: ''
  })

  const formItems = computed(() => [
    {
      label: '模型',
      key: 'model_name',
      type: 'input',
      props: { clearable: true, placeholder: '模型名' }
    },
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      props: { clearable: true, placeholder: '接口' }
    }
  ])

  const {
    columns,
    columnChecks,
    data,
    loading,
    pagination,
    getData,
    replaceSearchParams,
    refreshData,
    handleSizeChange,
    handleCurrentChange
  } = useTable<typeof fetchGetUsageList>({
    core: {
      apiFn: fetchGetUsageList,
      apiParams: { page: 1, limit: 15, keyword: '', model_name: '' },
      immediate: false,
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'globalIndex', label: '序号', width: 70 },
        { prop: 'user_id', label: '用户', width: 80, align: 'center' as const },
        { prop: 'model_name', label: '模型', minWidth: 160 },
        { prop: 'endpoint', label: '接口', width: 100, align: 'center' as const },
        {
          prop: 'total_tokens',
          label: 'Token',
          width: 100,
          align: 'center' as const,
          formatter: (row: UsageItem) => formatNumber(row.total_tokens)
        },
        {
          prop: 'cost',
          label: '费用',
          width: 120,
          align: 'center' as const,
          formatter: (row: UsageItem) => `$${(row.cost || 0).toFixed(6)}`
        },
        {
          prop: 'duration',
          label: '耗时',
          width: 100,
          align: 'center' as const,
          formatter: (row: UsageItem) => formatDuration(row.duration || 0)
        },
        {
          prop: 'status',
          label: '状态',
          width: 80,
          align: 'center' as const,
          formatter: (row: UsageItem) =>
            h(
              ElTag,
              { type: row.status === 1 ? 'success' : 'danger', size: 'small' },
              () => (row.status === 1 ? '成功' : '失败')
            )
        },
        { prop: 'created_at', label: '时间', width: 180 }
      ]
    }
  })

  const loadSummary = async () => {
    try {
      const res: any = await fetchGetUsageSummary({})
      summary.value = res
    } catch {
      // handled
    }
  }

  const handleSearch = (params: Record<string, any>) => {
    replaceSearchParams(params)
    getData()
  }

  const handleReset = () => {
    Object.assign(formFilters, { keyword: '', model_name: '' })
    replaceSearchParams({ keyword: '', model_name: '' })
    getData()
  }

  const formatNumber = (n: number) => {
    if (!n) return '0'
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
    return String(n)
  }

  /**
   * 格式化耗时，智能转换为合适的单位
   * @param ms 毫秒数
   * @returns 格式化后的时长字符串
   */
  const formatDuration = (ms: number): string => {
    if (!ms || ms <= 0) return '0ms'

    // 小于 1 秒，显示毫秒
    if (ms < 1000) {
      return `${ms}ms`
    }

    // 小于 1 分钟，显示秒（保留1位小数）
    if (ms < 60 * 1000) {
      const seconds = ms / 1000
      return `${seconds.toFixed(1)}s`
    }

    // 小于 1 小时，显示分钟和秒
    if (ms < 60 * 60 * 1000) {
      const minutes = Math.floor(ms / 60000)
      const seconds = Math.floor((ms % 60000) / 1000)
      return seconds > 0 ? `${minutes}m ${seconds}s` : `${minutes}m`
    }

    // 大于等于 1 小时，显示小时和分钟
    const hours = Math.floor(ms / 3600000)
    const minutes = Math.floor((ms % 3600000) / 60000)
    return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`
  }

  onMounted(() => {
    replaceSearchParams({ keyword: '', model_name: '' })
    getData()
    loadSummary()
  })
</script>

<style scoped lang="scss">
  .summary-row {
    margin-bottom: 16px;
  }
  .stat-card {
    :deep(.el-statistic__content) {
      font-size: 22px;
    }
  }
</style>
