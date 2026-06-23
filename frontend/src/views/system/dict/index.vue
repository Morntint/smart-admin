<!-- 字典管理页面 -->
<template>
  <div class="art-full-height">
    <div class="box-border flex gap-4 h-full max-md:block max-md:gap-0 max-md:h-auto">
      <!-- 左侧字典类型 -->
      <div class="flex-shrink-0 w-[460px] h-full max-md:w-full max-md:h-auto max-md:mb-5">
        <div class="flex flex-col h-full">
          <!-- 搜索栏 -->
          <div class="dict-type-search art-card-xs">
            <ElInput
              v-model="typeKeyword"
              placeholder="请输入字典名称或字典编码"
              clearable
              :prefix-icon="Search"
              class="flex-1 min-w-0"
              @keyup.enter="handleTypeSearch"
              @clear="handleTypeSearch"
            />
            <ElButton type="primary" @click="handleTypeSearch" v-ripple>查询</ElButton>
          </div>

          <!-- 类型表格卡片 -->
          <ElCard class="dict-left-card art-table-card flex flex-col flex-1 min-h-0 mt-3">
            <div class="dict-type-toolbar">
              <span class="dict-type-title">字典类型</span>
              <ElButton
                v-auth="'system:dict:add'"
                type="primary"
                plain
                size="small"
                @click="showTypeDialog('add')"
                v-ripple
              >
                新增字典
              </ElButton>
            </div>

            <div class="dict-table-wrap">
              <ArtTable
                ref="typeTableRef"
                row-key="id"
                highlight-current-row
                :show-table-header="false"
                :loading="typeLoading"
                :data="dictTypeList"
                :columns="typeColumns"
                :pagination="typePagination"
                @row-click="selectDictType"
                @pagination:size-change="handleTypeSizeChange"
                @pagination:current-change="handleTypeCurrentChange"
              />
            </div>
          </ElCard>
        </div>
      </div>

      <!-- 右侧字典数据 -->
      <div class="flex flex-col flex-grow min-w-0">
        <!-- 搜索栏 -->
        <DictDataSearch
          v-model="dataSearchForm"
          @search="handleDataSearch"
          @reset="handleDataReset"
        />

        <ElCard class="flex flex-col flex-1 min-h-0 art-table-card mt-3">
          <!-- 表格头部 -->
          <ArtTableHeader :loading="loading" v-model:columns="columnChecks" @refresh="refreshData">
            <template #left>
              <div class="flex items-center gap-3">
                <span class="dict-data-title">字典数据 · {{ pagination.total }}</span>
                <ElButton
                  v-auth="'system:dict:add'"
                  type="primary"
                  :disabled="!currentDictId"
                  @click="showDataDialog('add')"
                  v-ripple
                >
                  新增数据
                </ElButton>
              </div>
            </template>
          </ArtTableHeader>

          <!-- 表格 -->
          <ArtTable
            :loading="loading"
            :data="tableData"
            :columns="columns"
            :pagination="pagination"
            @pagination:size-change="handleSizeChange"
            @pagination:current-change="handleCurrentChange"
          />
        </ElCard>
      </div>
    </div>

    <!-- 字典类型对话框 -->
    <DictTypeDialog
      v-model:visible="typeDialogVisible"
      :type="typeDialogType"
      :edit-data="currentTypeData"
      @success="handleTypeSuccess"
    />

    <!-- 字典数据对话框 -->
    <DictDataDialog
      v-model:visible="dataDialogVisible"
      :type="dataDialogType"
      :edit-data="currentDataData"
      :dict-id="currentDictId"
      @success="handleDataSuccess"
    />
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import DictTypeDialog from './modules/dict-type-dialog.vue'
  import DictDataDialog from './modules/dict-data-dialog.vue'
  import DictDataSearch from './modules/dict-data-search.vue'
  import {
    fetchGetDictList,
    fetchDeleteDict,
    fetchGetDictDataList,
    fetchDeleteDictData
  } from '@/api/system-manage'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'
  import { Search } from '@element-plus/icons-vue'

  defineOptions({ name: 'Dict' })

  const { hasAuth } = useAuth()

  type DictListItem = Api.SystemManage.DictListItem
  type DictDataListItem = Api.SystemManage.DictDataListItem

  // 当前选中的字典类型
  const currentDictId = ref<number>(0)
  const currentDictCode = ref<string>('')
  // 字典类型表格 ref（用于高亮当前行）
  const typeTableRef = ref()

  // 字典类型对话框
  const typeDialogVisible = ref(false)
  const typeDialogType = ref<'add' | 'edit'>('add')
  const currentTypeData = ref<DictListItem | null>(null)

  // 字典数据对话框
  const dataDialogVisible = ref(false)
  const dataDialogType = ref<'add' | 'edit'>('add')
  const currentDataData = ref<DictDataListItem | null>(null)

  // 状态配置
  const STATUS_CONFIG = {
    0: { type: 'warning' as const, text: '禁用' },
    1: { type: 'success' as const, text: '启用' }
  } as const

  const renderStatusTag = (status: number) => {
    const config = STATUS_CONFIG[status as keyof typeof STATUS_CONFIG] || {
      type: 'info' as const,
      text: '未知'
    }
    return h(ElTag, { type: config.type }, () => config.text)
  }

  // ========== 左侧：字典类型表格 ==========
  const typeKeyword = ref('')

  const {
    columns: typeColumns,
    data: dictTypeList,
    loading: typeLoading,
    pagination: typePagination,
    getData: getDictTypeList,
    replaceSearchParams: replaceTypeSearchParams,
    refreshData: refreshTypeData,
    handleSizeChange: handleTypeSizeChange,
    handleCurrentChange: handleTypeCurrentChange
  } = useTable({
    core: {
      apiFn: fetchGetDictList,
      apiParams: {
        page: 1,
        limit: 10,
        keyword: ''
      },
      paginationKey: {
        current: 'page',
        size: 'limit'
      },
      columnsFactory: () => [
        {
          type: 'globalIndex',
          label: '序号',
          width: 70
        },
        {
          prop: 'name',
          label: '字典名称',
          minWidth: 120,
          showOverflowTooltip: true
        },
        {
          prop: 'code',
          label: '字典编码',
          minWidth: 120,
          showOverflowTooltip: true
        },
        {
          prop: 'status',
          label: '状态',
          width: 80,
          formatter: (row: DictListItem) => renderStatusTag(row.status)
        },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row: DictListItem) =>
            h(
              'div',
              { onClick: (e: Event) => e.stopPropagation() },
              [
                hasAuth('system:dict:edit') &&
                  h(ArtButtonTable, {
                    type: 'edit',
                    onClick: () => showTypeDialog('edit', row)
                  }),
                hasAuth('system:dict:del') &&
                  h(ArtButtonTable, {
                    type: 'delete',
                    onClick: () => deleteDictType(row)
                  })
              ].filter(Boolean)
            )
        }
      ]
    },
    hooks: {
      // 列表加载成功后，自动选中以联动右侧数据
      onSuccess: (list) => {
        syncCurrentSelection(list as DictListItem[])
      }
    }
  })

  // ========== 右侧：字典数据表格 ==========
  const dataSearchForm = ref<{ label?: string; value?: string }>({
    label: '',
    value: ''
  })

  // 标签类型映射（与字典类型对话框保持一致）
  const DICT_TYPE_MAP: Record<number, string> = {
    1: '字符串',
    2: '数字',
    3: '布尔值'
  }

  const {
    columns,
    columnChecks,
    data: tableData,
    loading,
    pagination,
    getData,
    replaceSearchParams,
    refreshData,
    handleSizeChange,
    handleCurrentChange
  } = useTable({
    core: {
      apiFn: fetchGetDictDataList,
      apiParams: {
        dict_id: 0,
        page: 1,
        limit: 10,
        label: '',
        value: ''
      },
      paginationKey: {
        current: 'page',
        size: 'limit'
      },
      immediate: false,
      columnsFactory: () => [
        {
          type: 'globalIndex',
          label: '序号',
          width: 70
        },
        {
          prop: 'label',
          label: '数据标签',
          minWidth: 140
        },
        {
          prop: 'value',
          label: '数据键值',
          minWidth: 140
        },
        {
          prop: 'type',
          label: '标签类型',
          width: 110,
          formatter: () => {
            const dict = (dictTypeList.value as DictListItem[]).find(
              (d) => d.id === currentDictId.value
            )
            return dict ? DICT_TYPE_MAP[dict.type] || '-' : '-'
          }
        },
        {
          prop: 'sort',
          label: '排序',
          width: 80
        },
        {
          prop: 'color',
          label: '颜色',
          width: 120,
          formatter: (row: DictDataListItem) => {
            if (row.color) {
              return h(
                'div',
                {
                  class: 'inline-flex items-center gap-2'
                },
                [
                  h('span', {
                    style: {
                      display: 'inline-block',
                      width: '16px',
                      height: '16px',
                      borderRadius: '4px',
                      backgroundColor: row.color
                    }
                  }),
                  h('span', row.color)
                ]
              )
            }
            return '-'
          }
        },
        {
          prop: 'status',
          label: '状态',
          width: 90,
          formatter: (row: DictDataListItem) => renderStatusTag(row.status)
        },
        {
          prop: 'remark',
          label: '备注',
          minWidth: 160
        },
        {
          prop: 'create_time',
          label: '更新时间',
          width: 180
        },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row: DictDataListItem) =>
            h(
              'div',
              [
                hasAuth('system:dict:edit') &&
                  h(ArtButtonTable, {
                    type: 'edit',
                    onClick: () => showDataDialog('edit', row)
                  }),
                hasAuth('system:dict:del') &&
                  h(ArtButtonTable, {
                    type: 'delete',
                    onClick: () => deleteDictData(row)
                  })
              ].filter(Boolean)
            )
        }
      ]
    }
  })

  /**
   * 根据最新的字典类型列表，同步当前选中项并联动右侧数据
   */
  const syncCurrentSelection = (list: DictListItem[]): void => {
    if (list.length === 0) {
      currentDictId.value = 0
      currentDictCode.value = ''
      return
    }
    // 当前选中项仍在列表中则保持，否则默认选中第一项
    const exists = list.find((item) => item.id === currentDictId.value)
    selectDictType(exists ?? list[0])
  }

  /**
   * 选择字典类型，联动右侧字典数据
   */
  const selectDictType = (item: DictListItem): void => {
    if (!item) return
    currentDictId.value = item.id
    currentDictCode.value = item.code
    // 高亮当前行
    nextTick(() => {
      const target = (dictTypeList.value as DictListItem[]).find((d) => d.id === item.id)
      typeTableRef.value?.elTableRef?.setCurrentRow(target)
    })
    // 切换类型时重置数据搜索条件
    dataSearchForm.value = { label: '', value: '' }
    replaceSearchParams({ dict_id: item.id, label: '', value: '' })
    getData()
  }

  /**
   * 字典类型搜索
   */
  const handleTypeSearch = (): void => {
    replaceTypeSearchParams({ keyword: typeKeyword.value })
    getDictTypeList()
  }

  /**
   * 字典数据搜索
   */
  const handleDataSearch = (params: { label?: string; value?: string }): void => {
    if (!currentDictId.value) return
    replaceSearchParams({ dict_id: currentDictId.value, ...params })
    getData()
  }

  /**
   * 字典数据搜索重置
   */
  const handleDataReset = (): void => {
    dataSearchForm.value = { label: '', value: '' }
    if (!currentDictId.value) return
    replaceSearchParams({ dict_id: currentDictId.value, label: '', value: '' })
    getData()
  }

  /**
   * 显示字典类型对话框
   */
  const showTypeDialog = (type: 'add' | 'edit', row?: DictListItem): void => {
    typeDialogType.value = type
    currentTypeData.value = row ? { ...row } : null
    typeDialogVisible.value = true
  }

  /**
   * 显示字典数据对话框
   */
  const showDataDialog = (type: 'add' | 'edit', row?: DictDataListItem): void => {
    dataDialogType.value = type
    currentDataData.value = row ? { ...row } : null
    dataDialogVisible.value = true
  }

  /**
   * 字典类型操作成功回调
   */
  const handleTypeSuccess = (): void => {
    refreshTypeData()
  }

  /**
   * 字典数据操作成功回调
   */
  const handleDataSuccess = (): void => {
    refreshData()
  }

  /**
   * 删除字典数据
   */
  const deleteDictData = (row: DictDataListItem): void => {
    ElMessageBox.confirm(`确定要删除字典数据"${row.label}"吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteDictData(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  /**
   * 删除字典类型
   */
  const deleteDictType = (row: DictListItem): void => {
    ElMessageBox.confirm(
      `确定要删除字典类型"${row.name}"吗？此操作会同时删除该字典下的所有数据！`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
      .then(async () => {
        await fetchDeleteDict(row.id)
        ElMessage.success('删除成功')
        if (currentDictId.value === row.id) {
          currentDictId.value = 0
          currentDictCode.value = ''
        }
        refreshTypeData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }
</script>

<style scoped lang="scss">
  .dict-type-search {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--default-box-color);
    border-radius: calc(var(--custom-radius) / 2 + 4px);
  }

  .dict-left-card {
    :deep(.el-card__body) {
      display: flex;
      flex-direction: column;
      flex: 1;
      min-height: 0;
      overflow: hidden;
    }

    .dict-type-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;

      .dict-type-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--art-gray-900);
      }
    }

    // 表格区域填充工具栏以外的剩余高度，保证分页可见
    .dict-table-wrap {
      flex: 1;
      min-height: 0;
    }

    // 行可点击
    :deep(.el-table__row) {
      cursor: pointer;
    }
  }

  .dict-data-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--art-gray-900);
    white-space: nowrap;
  }
</style>
