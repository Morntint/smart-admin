<!-- 操作日志页面 -->
<template>
  <div class="art-full-height">
    <LogSearch
      v-show="showSearchBar"
      v-model="searchForm"
      @search="handleSearch"
      @reset="handleReset"
    />

    <ElCard class="art-table-card" :style="{ 'margin-top': showSearchBar ? '12px' : '0' }">
      <ArtTableHeader
        v-model:columns="columnChecks"
        v-model:showSearchBar="showSearchBar"
        :loading="loading"
        @refresh="refreshData"
      >
        <template #left>
          <ElSpace wrap>
            <ElButton
              v-auth="'system:log:operationDel'"
              type="danger"
              :disabled="selectedRows.length === 0"
              @click="batchDelete"
              v-ripple
            >
              批量删除
            </ElButton>
            <ElPopconfirm
              title="确定要清理 30 天前的操作日志吗？此操作不可恢复！"
              confirm-button-text="确定"
              cancel-button-text="取消"
              @confirm="clearLogs"
            >
              <template #reference>
                <ElButton type="warning" v-ripple v-auth="'system:log:operationDel'">清理日志</ElButton>
              </template>
            </ElPopconfirm>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @selection-change="handleSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <LogDetailDialog v-model:visible="detailVisible" :detail="currentDetail" />
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import {
    fetchGetOperationLogList,
    fetchGetOperationLog,
    fetchDeleteOperationLog,
    fetchBatchDeleteOperationLog,
    fetchClearOperationLog
  } from '@/api/system-manage'
  import LogSearch, { type OperationLogSearchParams } from './modules/log-search.vue'
  import LogDetailDialog from './modules/log-detail-dialog.vue'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  defineOptions({ name: 'Log' })

  const { hasAuth } = useAuth()

  type OperationLogListItem = Api.SystemManage.OperationLogListItem

  /**
   * 状态配置（与后端一致：1=正常, 0=异常）
   */
  const STATUS_CONFIG = {
    0: { type: 'danger' as const, text: '异常' },
    1: { type: 'success' as const, text: '正常' }
  } as const

  /**
   * 请求方法对应的 Tag 颜色（与后端 SysOperationLog::METHOD_COLOR_MAP 一致）
   */
  const METHOD_TAG_TYPE: Record<string, 'success' | 'primary' | 'warning' | 'danger' | 'info'> = {
    GET: 'success',
    POST: 'primary',
    PUT: 'warning',
    DELETE: 'danger',
    PATCH: 'info'
  }

  const showSearchBar = ref(false)

  // 搜索表单 - 字段与后端对齐
  const searchForm = ref<OperationLogSearchParams>({
    keyword: '',
    module: '',
    method: '',
    status: '',
    start_date: '',
    end_date: '',
    daterange: []
  })

  // 选中行
  const selectedRows = ref<OperationLogListItem[]>([])

  // 详情弹窗
  const detailVisible = ref(false)
  const currentDetail = ref<OperationLogListItem | null>(null)

  const {
    columns,
    columnChecks,
    data,
    loading,
    pagination,
    getData,
    replaceSearchParams,
    resetSearchParams,
    handleSizeChange,
    handleCurrentChange,
    refreshData
  } = useTable({
    core: {
      apiFn: fetchGetOperationLogList,
      apiParams: {
        page: 1,
        limit: 20
      },
      paginationKey: {
        current: 'page',
        size: 'limit'
      },
      columnsFactory: () => [
        { type: 'selection' },
        { type: 'index', width: 60, label: '序号' },
        {
          prop: 'module',
          label: '操作模块',
          width: 120,
          formatter: (row) => row.module || '-'
        },
        {
          prop: 'method',
          label: '请求方法',
          width: 100,
          formatter: (row) =>
            h(
              ElTag,
              { type: METHOD_TAG_TYPE[row.method] ?? 'info', size: 'small' },
              () => row.method || '-'
            )
        },
        {
          prop: 'url',
          label: '请求URL',
          minWidth: 220,
          showOverflowTooltip: true
        },
        {
          prop: 'username',
          label: '操作用户',
          width: 120,
          formatter: (row) => row.username || '-'
        },
        {
          prop: 'ip',
          label: 'IP地址',
          width: 140,
          formatter: (row) => row.ip || '-'
        },
        {
          prop: 'duration',
          label: '耗时(ms)',
          width: 120,
          sortable: true,
          formatter: (row) => row.duration ?? 0
        },
        {
          prop: 'status',
          label: '状态',
          width: 90,
          formatter: (row) => {
            const config = STATUS_CONFIG[row.status as keyof typeof STATUS_CONFIG] || {
              type: 'info' as const,
              text: '未知'
            }
            return h(ElTag, { type: config.type, size: 'small' }, () => config.text)
          }
        },
        {
          prop: 'created_at',
          label: '操作时间',
          width: 180,
          sortable: true
        },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row) =>
            h(
              'div',
              [
                h(ArtButtonTable, {
                  type: 'view',
                  onClick: () => viewDetail(row)
                }),
                hasAuth('system:log:operationDel') &&
                  h(ArtButtonTable, {
                    type: 'delete',
                    onClick: () => deleteLog(row)
                  })
              ].filter(Boolean)
            )
        }
      ]
    }
  })

  /**
   * 搜索处理
   */
  const handleSearch = (params: OperationLogSearchParams) => {
    Object.assign(searchForm.value, params)
    const { daterange: _ignored, ...payload } = params
    void _ignored
    replaceSearchParams(payload)
    getData()
  }

  /**
   * 重置搜索
   */
  const handleReset = () => {
    searchForm.value = {
      keyword: '',
      module: '',
      method: '',
      status: '',
      start_date: '',
      end_date: '',
      daterange: []
    }
    resetSearchParams()
  }

  /**
   * 查看详情：拉取后端详情接口，获取 formatted_param
   */
  const viewDetail = async (row: OperationLogListItem) => {
    try {
      const detail = await fetchGetOperationLog(row.id)
      currentDetail.value = detail ?? row
    } catch (error) {
      currentDetail.value = row
      console.error('获取日志详情失败:', error)
    }
    detailVisible.value = true
  }

  /**
   * 删除单条日志
   */
  const deleteLog = (row: OperationLogListItem) => {
    ElMessageBox.confirm('确定要删除这条操作日志吗？', '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteOperationLog(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  /**
   * 批量删除
   */
  const batchDelete = () => {
    if (selectedRows.value.length === 0) {
      ElMessage.warning('请选择要删除的日志')
      return
    }

    ElMessageBox.confirm(`确定要删除选中的 ${selectedRows.value.length} 条日志吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        const ids = selectedRows.value.map((item) => item.id)
        await fetchBatchDeleteOperationLog(ids)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  /**
   * 清理 30 天前的操作日志
   */
  const clearLogs = async () => {
    try {
      await fetchClearOperationLog(30)
      ElMessage.success('清理成功')
      refreshData()
    } catch (error) {
      console.error('清理失败:', error)
    }
  }

  /**
   * 表格行选择变化
   */
  const handleSelectionChange = (selection: OperationLogListItem[]) => {
    selectedRows.value = selection
  }
</script>
