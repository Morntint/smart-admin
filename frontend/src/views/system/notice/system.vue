<!-- 系统通知管理页（管理端） -->
<template>
  <div class="art-full-height">
    <NoticeSearch
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
            <ElButton v-auth="'system:notice:add'" type="primary" @click="goToSend" v-ripple>
              <ArtSvgIcon icon="ri:edit-2-line" class="mr-1" />
              发送通知
            </ElButton>
            <ElButton v-auth="'system:notice:add'" type="success" @click="showBatchDialog" v-ripple>
              <ArtSvgIcon icon="ri:mail-send-line" class="mr-1" />
              批量发送
            </ElButton>
            <ElButton
              v-auth="'system:notice:del'"
              type="danger"
              :disabled="selectedRows.length === 0"
              @click="batchDelete"
              v-ripple
            >
              批量删除
            </ElButton>
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

    <!-- 批量发送 -->
    <NoticeBatchDialog v-model:visible="batchDialogVisible" @success="handleSuccess" />

    <!-- 通知详情 -->
    <NoticeDetailDialog v-model:visible="detailVisible" :detail="currentDetail" />

    <!-- 发送通知弹窗 -->
    <NoticeSendDialog v-model:visible="sendDialogVisible" :edit-id="editId" @success="refreshData" />
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import {
    fetchGetNoticeList,
    fetchDeleteNotice,
    fetchBatchDeleteNotice,
    fetchMarkNoticeRead
  } from '@/api/system-manage'
  import NoticeSearch, { type NoticeSearchParams } from './modules/notice-search.vue'
  import NoticeBatchDialog from './modules/notice-batch-dialog.vue'
  import NoticeDetailDialog from './modules/notice-detail-dialog.vue'
  import NoticeSendDialog from './modules/notice-send-dialog.vue'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  defineOptions({ name: 'SystemNotice' })

  const { hasAuth } = useAuth()

  type NoticeListItem = Api.SystemManage.NoticeListItem

  // 类型配置
  const TYPE_CONFIG: Record<
    number,
    { type: 'primary' | 'success' | 'warning' | 'info' | 'danger'; text: string }
  > = {
    1: { type: 'primary', text: '系统通知' },
    2: { type: 'warning', text: '待办' },
    3: { type: 'danger', text: '预警' },
    4: { type: 'info', text: '个人消息' }
  }

  // 级别配置
  const LEVEL_CONFIG: Record<
    string,
    { type: 'primary' | 'success' | 'warning' | 'danger' | 'info'; text: string }
  > = {
    info: { type: 'info', text: '普通' },
    success: { type: 'success', text: '成功' },
    warning: { type: 'warning', text: '警告' },
    danger: { type: 'danger', text: '严重' }
  }

  const showSearchBar = ref(false)
  const searchForm = ref<NoticeSearchParams>({
    keyword: '',
    type: '',
    level: '',
    is_read: '',
    user_id: undefined,
    start_date: '',
    end_date: '',
    daterange: []
  })

  // 批量发送弹窗
  const batchDialogVisible = ref(false)

  // 详情
  const detailVisible = ref(false)
  const currentDetail = ref<NoticeListItem | null>(null)

  // 发送弹窗
  const sendDialogVisible = ref(false)
  const editId = ref<number | null>(null)

  // 选中行
  const selectedRows = ref<NoticeListItem[]>([])

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
      apiFn: fetchGetNoticeList,
      apiParams: {
        page: 1,
        limit: 20
      },
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'selection' },
        { type: 'index', width: 60, label: '序号' },
        {
          prop: 'title',
          label: '通知标题',
          minWidth: 200,
          showOverflowTooltip: true,
          formatter: (row: NoticeListItem) => row.title || '-'
        },
        {
          prop: 'type',
          label: '类型',
          width: 100,
          formatter: (row: NoticeListItem) => {
            const cfg = TYPE_CONFIG[row.type] || { type: 'info' as const, text: '未知' }
            return h(ElTag, { type: cfg.type, size: 'small' }, () => cfg.text)
          }
        },
        {
          prop: 'level',
          label: '级别',
          width: 90,
          formatter: (row: NoticeListItem) => {
            const cfg = LEVEL_CONFIG[row.level] || { type: 'info' as const, text: '普通' }
            return h(ElTag, { type: cfg.type, size: 'small' }, () => cfg.text)
          }
        },
        {
          prop: 'username',
          label: '接收人',
          minWidth: 120,
          formatter: (row: NoticeListItem) =>
            row.user_nickname || row.username || (row.user_id ? `#${row.user_id}` : '-')
        },
        {
          prop: 'sender_name',
          label: '发送人',
          width: 100,
          formatter: (row: NoticeListItem) =>
            row.sender_name || (row.sender_id ? `#${row.sender_id}` : '系统')
        },
        {
          prop: 'is_read',
          label: '已读',
          width: 80,
          formatter: (row: NoticeListItem) =>
            h(
              ElTag,
              {
                type: row.is_read === 1 ? 'success' : 'warning',
                size: 'small'
              },
              () => (row.is_read === 1 ? '已读' : '未读')
            )
        },
        {
          prop: 'expire_time',
          label: '过期时间',
          width: 180,
          formatter: (row: NoticeListItem) => {
            if (!row.expire_time) {
              return '永不过期'
            }
            // 后端 myInbox 已下发 is_expired 派生字段；这里再用前端时间兜底
            const expired =
              row.is_expired === 1 ||
              new Date(row.expire_time.replace(' ', 'T')).getTime() <= Date.now()
            if (expired) {
              return h('span', { class: 'inline-flex items-center gap-1' }, [
                h(ElTag, { type: 'danger', size: 'small' }, () => '已过期'),
                row.expire_time
              ])
            }
            return row.expire_time
          }
        },
        {
          prop: 'created_at',
          label: '创建时间',
          width: 180,
          sortable: true
        },
        {
          prop: 'operation',
          label: '操作',
          width: 200,
          fixed: 'right',
          formatter: (row: NoticeListItem) =>
            h(
              'div',
              [
                h(ArtButtonTable, {
                  type: 'view',
                  onClick: () => viewDetail(row)
                }),
                row.is_read === 0 &&
                  hasAuth('system:notice:read') &&
                  h(ArtButtonTable, {
                    icon: 'ri:check-line',
                    iconClass: 'bg-success/12 text-success',
                    title: '标记已读',
                    onClick: () => markRead(row)
                  }),
                hasAuth('system:notice:edit') &&
                  h(ArtButtonTable, {
                    type: 'edit',
                    onClick: () => goToEdit(row)
                  }),
                hasAuth('system:notice:del') &&
                  h(ArtButtonTable, {
                    type: 'delete',
                    onClick: () => deleteOne(row)
                  })
              ].filter(Boolean)
            )
        }
      ]
    }
  })

  const handleSearch = (params: NoticeSearchParams): void => {
    const { daterange, ...rest } = params
    void daterange
    Object.assign(searchForm.value, params)
    if (Array.isArray(params.daterange) && params.daterange.length === 2) {
      rest.start_date = params.daterange[0]
      rest.end_date = params.daterange[1]
    } else {
      rest.start_date = ''
      rest.end_date = ''
    }
    replaceSearchParams(rest)
    getData()
  }

  const handleReset = (): void => {
    searchForm.value = {
      keyword: '',
      type: '',
      level: '',
      is_read: '',
      user_id: undefined,
      start_date: '',
      end_date: '',
      daterange: []
    }
    resetSearchParams()
  }

  const goToSend = (): void => {
    editId.value = null
    sendDialogVisible.value = true
  }

  const goToEdit = (row: NoticeListItem): void => {
    editId.value = row.id
    sendDialogVisible.value = true
  }

  const showBatchDialog = (): void => {
    batchDialogVisible.value = true
  }

  const viewDetail = async (row: NoticeListItem): Promise<void> => {
    detailVisible.value = true
    currentDetail.value = row
  }

  const markRead = async (row: NoticeListItem): Promise<void> => {
    try {
      await fetchMarkNoticeRead(row.id)
      ElMessage.success('已标记为已读')
      refreshData()
    } catch (error) {
      console.error('标记已读失败', error)
    }
  }

  const deleteOne = (row: NoticeListItem): void => {
    ElMessageBox.confirm(`确定要删除通知「${row.title}」吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteNotice(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  const batchDelete = (): void => {
    if (selectedRows.value.length === 0) {
      ElMessage.warning('请先选择要删除的通知')
      return
    }
    ElMessageBox.confirm(
      `确定要删除选中的 ${selectedRows.value.length} 条通知吗？此操作不可恢复！`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
      .then(async () => {
        const ids = selectedRows.value.map((item) => item.id)
        await fetchBatchDeleteNotice(ids)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  const handleSelectionChange = (rows: NoticeListItem[]): void => {
    selectedRows.value = rows
  }

  const handleSuccess = (): void => {
    refreshData()
  }
</script>
