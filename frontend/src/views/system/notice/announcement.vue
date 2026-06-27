<!-- 系统公告管理页（管理端） -->
<template>
  <div class="art-full-height">
    <AnnouncementSearch
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
              v-auth="'system:announcement:add'"
              type="primary"
              @click="goToPublish"
              v-ripple
            >
              <ArtSvgIcon icon="ri:megaphone-line" class="mr-1" />
              发布公告
            </ElButton>
            <ElButton
              v-auth="'system:announcement:del'"
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

    <!-- 公告 新增 / 编辑（已迁移到独立页面 AnnouncementPublish） -->

    <!-- 公告详情（管理端） -->
    <AnnouncementDetailDialog
      v-model:visible="detailVisible"
      :detail="currentDetail"
      @edit="onEditFromDetail"
    />

    <!-- 发布公告弹窗 -->
    <AnnouncementPublishDialog v-model:visible="publishDialogVisible" :edit-id="editId" @success="refreshData" />
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import {
    fetchGetAnnouncementList,
    fetchDeleteAnnouncement,
    fetchBatchDeleteAnnouncement,
    fetchPublishAnnouncement,
    fetchOfflineAnnouncement
  } from '@/api/system-manage'
  import AnnouncementSearch, {
    type AnnouncementSearchParams
  } from './modules/announcement-search.vue'
  import AnnouncementDetailDialog from './modules/announcement-detail-dialog.vue'
  import AnnouncementPublishDialog from './modules/announcement-publish-dialog.vue'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  defineOptions({ name: 'SystemAnnouncement' })

  const { hasAuth } = useAuth()

  type AnnouncementListItem = Api.SystemManage.AnnouncementListItem

  // 分类配置
  const CATEGORY_CONFIG: Record<
    string,
    { type: 'primary' | 'success' | 'warning' | 'info'; text: string }
  > = {
    notice: { type: 'primary', text: '通知' },
    announcement: { type: 'success', text: '公告' },
    activity: { type: 'warning', text: '活动' },
    maintenance: { type: 'info', text: '维护' }
  }

  // 级别配置
  const LEVEL_CONFIG: Record<
    string,
    { type: 'primary' | 'warning' | 'danger' | 'info'; text: string }
  > = {
    info: { type: 'info', text: '普通' },
    important: { type: 'warning', text: '重要' },
    urgent: { type: 'danger', text: '紧急' }
  }

  // 状态配置
  const STATUS_CONFIG: Record<
    number,
    { type: 'primary' | 'success' | 'info' | 'warning' | 'danger'; text: string }
  > = {
    0: { type: 'info', text: '草稿' },
    1: { type: 'success', text: '已发布' },
    2: { type: 'warning', text: '已下线' }
  }

  const showSearchBar = ref(false)
  const searchForm = ref<AnnouncementSearchParams>({
    keyword: '',
    category: '',
    level: '',
    status: '',
    is_top: '',
    start_date: '',
    end_date: '',
    daterange: []
  })

  // 详情
  const detailVisible = ref(false)
  const currentDetail = ref<AnnouncementListItem | null>(null)

  // 发布弹窗
  const publishDialogVisible = ref(false)
  const editId = ref<number | null>(null)

  // 选中行
  const selectedRows = ref<AnnouncementListItem[]>([])

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
      apiFn: fetchGetAnnouncementList,
      apiParams: { page: 1, limit: 20 },
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'selection' },
        { type: 'index', width: 60, label: '序号' },
        {
          prop: 'title',
          label: '公告标题',
          minWidth: 220,
          showOverflowTooltip: true,
          formatter: (row: AnnouncementListItem) =>
            h('div', { class: 'ann-title-cell' }, [
              row.is_top === 1 &&
                h(ElTag, { type: 'danger', size: 'small', class: 'mr-1' }, () => '置顶'),
              row.is_popup === 1 &&
                h(ElTag, { type: 'warning', size: 'small', class: 'mr-1' }, () => '弹窗'),
              row.title || '-'
            ])
        },
        {
          prop: 'category',
          label: '分类',
          width: 90,
          formatter: (row: AnnouncementListItem) => {
            const cfg = CATEGORY_CONFIG[row.category] || { type: 'info' as const, text: '未知' }
            return h(ElTag, { type: cfg.type, size: 'small' }, () => cfg.text)
          }
        },
        {
          prop: 'level',
          label: '级别',
          width: 90,
          formatter: (row: AnnouncementListItem) => {
            const cfg = LEVEL_CONFIG[row.level] || { type: 'info' as const, text: '普通' }
            return h(ElTag, { type: cfg.type, size: 'small' }, () => cfg.text)
          }
        },
        {
          prop: 'status',
          label: '状态',
          width: 100,
          formatter: (row: AnnouncementListItem) => {
            const cfg = STATUS_CONFIG[row.status] || { type: 'info' as const, text: '未知' }
            return h(ElTag, { type: cfg.type, size: 'small' }, () => cfg.text)
          }
        },
        {
          prop: 'view_count',
          label: '阅读量',
          width: 90,
          formatter: (row: AnnouncementListItem) => row.view_count ?? 0
        },
        {
          prop: 'effective_at',
          label: '生效时间',
          width: 170,
          formatter: (row: AnnouncementListItem) => row.effective_at || '立即生效'
        },
        {
          prop: 'expire_at',
          label: '失效时间',
          width: 170,
          formatter: (row: AnnouncementListItem) => row.expire_at || '长期有效'
        },
        {
          prop: 'publisher_name',
          label: '发布人',
          width: 100,
          formatter: (row: AnnouncementListItem) =>
            row.publisher_name || (row.publisher_id ? `#${row.publisher_id}` : '-')
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
          width: 280,
          fixed: 'right',
          formatter: (row: AnnouncementListItem) =>
            h(
              'div',
              [
                h(ArtButtonTable, {
                  type: 'view',
                  title: '查看',
                  onClick: () => viewDetail(row)
                }),
                row.status === 0 &&
                  hasAuth('system:announcement:publish') &&
                  h(ArtButtonTable, {
                    icon: 'ri:send-plane-line',
                    iconClass: 'bg-success/12 text-success',
                    title: '发布',
                    onClick: () => publishOne(row)
                  }),
                row.status === 1 &&
                  hasAuth('system:announcement:publish') &&
                  h(ArtButtonTable, {
                    icon: 'ri:prohibited-line',
                    iconClass: 'bg-warning/12 text-warning',
                    title: '下线',
                    onClick: () => offlineOne(row)
                  }),
                hasAuth('system:announcement:edit') &&
                  h(ArtButtonTable, {
                    type: 'edit',
                    onClick: () => goToEdit(row)
                  }),
                hasAuth('system:announcement:del') &&
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

  const handleSearch = (params: AnnouncementSearchParams): void => {
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
      category: '',
      level: '',
      status: '',
      is_top: '',
      start_date: '',
      end_date: '',
      daterange: []
    }
    resetSearchParams()
  }

  const goToPublish = (): void => {
    editId.value = null
    publishDialogVisible.value = true
  }

  const goToEdit = (row: AnnouncementListItem): void => {
    editId.value = row.id
    publishDialogVisible.value = true
  }

  const viewDetail = (row: AnnouncementListItem): void => {
    currentDetail.value = row
    detailVisible.value = true
  }

  const onEditFromDetail = (row: AnnouncementListItem): void => {
    detailVisible.value = false
    goToEdit(row)
  }

  const publishOne = (row: AnnouncementListItem): void => {
    ElMessageBox.confirm(`确定要发布公告「${row.title}」吗？`, '发布确认', {
      confirmButtonText: '发布',
      cancelButtonText: '取消',
      type: 'info'
    })
      .then(async () => {
        await fetchPublishAnnouncement(row.id)
        ElMessage.success('发布成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消发布')
      })
  }

  const offlineOne = (row: AnnouncementListItem): void => {
    ElMessageBox.confirm(
      `确定要下线公告「${row.title}」吗？下线后将不再对前台用户展示。`,
      '下线确认',
      {
        confirmButtonText: '下线',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
      .then(async () => {
        await fetchOfflineAnnouncement(row.id)
        ElMessage.success('下线成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消下线')
      })
  }

  const deleteOne = (row: AnnouncementListItem): void => {
    ElMessageBox.confirm(`确定要删除公告「${row.title}」吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteAnnouncement(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  const batchDelete = (): void => {
    if (selectedRows.value.length === 0) {
      ElMessage.warning('请先选择要删除的公告')
      return
    }
    ElMessageBox.confirm(
      `确定要删除选中的 ${selectedRows.value.length} 条公告吗？此操作不可恢复！`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
      .then(async () => {
        const ids = selectedRows.value.map((item) => item.id)
        await fetchBatchDeleteAnnouncement(ids)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  const handleSelectionChange = (rows: AnnouncementListItem[]): void => {
    selectedRows.value = rows
  }
</script>

<style scoped lang="scss">
  .ann-title-cell {
    display: inline-flex;
    gap: 4px;
    align-items: center;
  }
</style>
