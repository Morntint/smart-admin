<!-- 系统配置管理页面 -->
<template>
  <div class="config-page art-full-height">
    <!-- 搜索栏 -->
    <ArtSearchBar
      v-model="formFilters"
      :items="formItems"
      :showExpand="false"
      @reset="handleReset"
      @search="handleSearch"
    />

    <ElCard class="art-table-card">
      <!-- 表格头部 -->
      <ArtTableHeader :loading="loading" v-model:columns="columnChecks" @refresh="refreshData">
        <template #left>
          <ElButton v-auth="'system:config:add'" @click="showDialog('add')" v-ripple>新增配置</ElButton>
        </template>
      </ArtTableHeader>

      <!-- 表格 -->
      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @selection-change="handleSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />

      <!-- 配置对话框 -->
      <ConfigDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :edit-data="currentConfigData"
        @success="handleSuccess"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { useAuth } from '@/hooks/core/useAuth'
  import ConfigDialog from './modules/config-dialog.vue'
  import { fetchGetConfigList, fetchDeleteConfig } from '@/api/system-manage'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  defineOptions({ name: 'Config' })

  const { hasAuth } = useAuth()

  type ConfigListItem = Api.SystemManage.ConfigListItem

  // 弹窗相关
  const dialogType = ref<'add' | 'edit'>('add')
  const dialogVisible = ref(false)
  const currentConfigData = ref<ConfigListItem | null>(null)

  // 选中行
  const selectedRows = ref<ConfigListItem[]>([])

  // 搜索表单
  const searchForm = ref({
    group: '',
    keyword: ''
  })

  // 搜索表单配置
  const formFilters = reactive({ ...searchForm.value })
  const appliedFilters = reactive({ ...searchForm.value })

  const formItems = computed(() => [
    {
      label: '配置分组',
      key: 'group',
      type: 'input',
      props: {
        clearable: true,
        placeholder: '请输入配置分组'
      }
    },
    {
      label: '配置名称',
      key: 'keyword',
      type: 'input',
      props: {
        clearable: true,
        placeholder: '请输入配置名称'
      }
    }
  ])

  // 配置类型映射：值 -> { ElTag type, 显示文本 }
  const TYPE_CONFIG: Record<
    string,
    { type: 'primary' | 'success' | 'info' | 'warning' | 'danger'; text: string }
  > = {
    string: { type: 'primary', text: '字符串' },
    number: { type: 'success', text: '数字' },
    boolean: { type: 'warning', text: '布尔值' },
    json: { type: 'info', text: 'JSON' }
  }

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
      apiFn: fetchGetConfigList,
      apiParams: {
        page: 1,
        limit: 20,
        ...appliedFilters
      },
      columnsFactory: () => [
        { type: 'selection' },
        {
          prop: 'name',
          label: '配置名称',
          minWidth: 150
        },
        {
          prop: 'key',
          label: '配置键名',
          minWidth: 180
        },
        {
          prop: 'value',
          label: '配置值',
          minWidth: 200,
          showOverflowTooltip: true
        },
        {
          prop: 'group',
          label: '配置分组',
          width: 120
        },
        {
          prop: 'type',
          label: '值类型',
          width: 100,
          formatter: (row: ConfigListItem) => {
            const config = TYPE_CONFIG[String(row.type)]
            return h(ElTag, { type: config?.type || 'info' }, () => config?.text || '未知')
          }
        },
        {
          prop: 'options',
          label: '可选值(JSON格式)',
          minWidth: 200,
          showOverflowTooltip: true
        },
        {
          prop: 'sort',
          label: '排序',
          width: 80
        },
        {
          prop: 'remark',
          label: '备注',
          minWidth: 200,
          showOverflowTooltip: true
        },
        {
          prop: 'created_at',
          label: '创建时间',
          width: 180
        },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row: ConfigListItem) =>
            h(
              'div',
              [
                hasAuth('system:config:edit') &&
                  h(ArtButtonTable, {
                    type: 'edit',
                    onClick: () => showDialog('edit', row)
                  }),
                hasAuth('system:config:del') &&
                  h(ArtButtonTable, {
                    type: 'delete',
                    onClick: () => deleteConfig(row)
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
  const handleSearch = (): void => {
    Object.assign(appliedFilters, { ...formFilters })
    replaceSearchParams(appliedFilters)
    getData()
  }

  /**
   * 重置搜索
   */
  const handleReset = (): void => {
    Object.assign(formFilters, { ...searchForm.value })
    Object.assign(appliedFilters, { ...searchForm.value })
    resetSearchParams()
    getData()
  }

  /**
   * 显示配置对话框
   */
  const showDialog = (type: 'add' | 'edit', row?: ConfigListItem): void => {
    dialogType.value = type
    currentConfigData.value = row ? { ...row } : null
    dialogVisible.value = true
  }

  /**
   * 操作成功回调
   */
  const handleSuccess = (): void => {
    refreshData()
  }

  /**
   * 删除配置
   */
  const deleteConfig = (row: ConfigListItem): void => {
    ElMessageBox.confirm(`确定要删除配置"${row.name}"吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteConfig(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  /**
   * 处理表格行选择变化
   */
  const handleSelectionChange = (selection: ConfigListItem[]): void => {
    selectedRows.value = selection
  }
</script>
