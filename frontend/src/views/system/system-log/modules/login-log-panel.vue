<!-- 登录日志面板 - 系统日志选项卡中的登录日志内容 -->
<template>
  <div class="log-panel">
    <LoginLogSearch
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
              type="danger"
              :disabled="selectedRows.length === 0"
              @click="batchDelete"
              v-ripple
            >
              批量删除
            </ElButton>
            <ElPopconfirm
              title="确定要清理 90 天前的登录日志吗？此操作不可恢复！"
              confirm-button-text="确定"
              cancel-button-text="取消"
              @confirm="clearLogs"
            >
              <template #reference>
                <ElButton type="warning" v-ripple>清理日志</ElButton>
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
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import {
    fetchGetLoginLogList,
    fetchDeleteLoginLog,
    fetchBatchDeleteLoginLog,
    fetchClearLoginLog
  } from '@/api/system-manage'
  import LoginLogSearch, {
    type LoginLogSearchParams
  } from '../../login-log/modules/login-log-search.vue'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  type LoginLogListItem = Api.SystemManage.LoginLogListItem

  /**
   * 登录状态配置（与后端一致：1=成功, 0=失败）
   */
  const STATUS_CONFIG = {
    0: { type: 'danger' as const, text: '失败' },
    1: { type: 'success' as const, text: '成功' }
  } as const

  /**
   * 登录类型配置（与后端 SysLoginLog 一致：1=登录, 2=登出）
   */
  const LOGIN_TYPE_CONFIG = {
    1: { type: 'primary' as const, text: '登录' },
    2: { type: 'info' as const, text: '登出' }
  } as const

  const showSearchBar = ref(true)

  // 搜索表单
  const searchForm = ref<LoginLogSearchParams>({
    keyword: '',
    status: '',
    login_type: '',
    start_date: '',
    end_date: '',
    daterange: []
  })

  const selectedRows = ref<LoginLogListItem[]>([])

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
      apiFn: fetchGetLoginLogList,
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
          prop: 'username',
          label: '用户名',
          width: 120,
          formatter: (row) => row.username || '-'
        },
        {
          prop: 'login_type',
          label: '登录类型',
          width: 100,
          formatter: (row) => {
            const config = LOGIN_TYPE_CONFIG[row.login_type as keyof typeof LOGIN_TYPE_CONFIG] || {
              type: 'info' as const,
              text: '未知'
            }
            return h(ElTag, { type: config.type, size: 'small' }, () => config.text)
          }
        },
        {
          prop: 'ip',
          label: '登录IP',
          width: 140,
          formatter: (row) => row.ip || '-'
        },
        {
          prop: 'ip_location',
          label: 'IP所在地',
          width: 150,
          formatter: (row) => row.ip_location || '-'
        },
        {
          prop: 'user_agent',
          label: 'User-Agent',
          minWidth: 220,
          showOverflowTooltip: true,
          formatter: (row) => row.user_agent || '-'
        },
        {
          prop: 'status',
          label: '登录状态',
          width: 100,
          formatter: (row) => {
            const config = STATUS_CONFIG[row.status as keyof typeof STATUS_CONFIG] || {
              type: 'info' as const,
              text: '未知'
            }
            return h(ElTag, { type: config.type, size: 'small' }, () => config.text)
          }
        },
        {
          prop: 'msg',
          label: '提示消息',
          minWidth: 180,
          showOverflowTooltip: true,
          formatter: (row) => row.msg || '-'
        },
        {
          prop: 'created_at',
          label: '登录时间',
          width: 180,
          sortable: true
        },
        {
          prop: 'operation',
          label: '操作',
          width: 90,
          fixed: 'right',
          formatter: (row) =>
            h('div', [
              h(ArtButtonTable, {
                type: 'delete',
                onClick: () => deleteLog(row)
              })
            ])
        }
      ]
    }
  })

  /**
   * 搜索处理
   */
  const handleSearch = (params: LoginLogSearchParams) => {
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
      status: '',
      login_type: '',
      start_date: '',
      end_date: '',
      daterange: []
    }
    resetSearchParams()
  }

  /**
   * 删除单条日志
   */
  const deleteLog = (row: LoginLogListItem) => {
    ElMessageBox.confirm('确定要删除这条登录日志吗？', '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteLoginLog(row.id)
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
        await fetchBatchDeleteLoginLog(ids)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  /**
   * 清理 90 天前的登录日志
   */
  const clearLogs = async () => {
    try {
      await fetchClearLoginLog(90)
      ElMessage.success('清理成功')
      refreshData()
    } catch (error) {
      console.error('清理失败:', error)
    }
  }

  const handleSelectionChange = (selection: LoginLogListItem[]) => {
    selectedRows.value = selection
  }
</script>

<style scoped lang="scss">
  .log-panel {
    display: flex;
    flex: 1 1 0;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }
</style>
