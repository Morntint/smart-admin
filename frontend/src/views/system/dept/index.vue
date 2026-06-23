<!-- 部门管理页面 -->
<template>
  <div class="dept-page art-full-height">
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
      <ArtTableHeader
        :showZebra="false"
        :loading="loading"
        v-model:columns="columnChecks"
        @refresh="handleRefresh"
      >
        <template #left>
          <ElButton v-auth="'system:dept:add'" @click="handleAddDept" v-ripple>新增部门</ElButton>
          <ElButton @click="toggleExpand" v-ripple>
            {{ isExpanded ? '收起' : '展开' }}
          </ElButton>
        </template>
      </ArtTableHeader>

      <ArtTable
        ref="tableRef"
        rowKey="id"
        :loading="loading"
        :columns="columns"
        :data="filteredTableData"
        :stripe="false"
        :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
        :default-expand-all="false"
      />

      <!-- 部门对话框 -->
      <DeptDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :edit-data="editData"
        @submit="handleSubmit"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import { useAuth } from '@/hooks/core/useAuth'
  import DeptDialog from './modules/dept-dialog.vue'
  import {
    fetchGetDeptList,
    fetchCreateDept,
    fetchUpdateDept,
    fetchDeleteDept
  } from '@/api/system-manage'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  defineOptions({ name: 'Dept' })

  const { hasAuth } = useAuth()

  type DeptListItem = Api.SystemManage.DeptListItem

  // 状态管理
  const loading = ref(false)
  const isExpanded = ref(false)
  const tableRef = ref()

  // 弹窗相关
  const dialogVisible = ref(false)
  const dialogType = ref<'add' | 'edit'>('add')
  const editData = ref<DeptListItem | null>(null)

  // 搜索相关
  const initialSearchState = {
    keyword: '',
    status: ''
  }

  const formFilters = reactive({ ...initialSearchState })
  const appliedFilters = reactive({ ...initialSearchState })

  const formItems = computed(() => [
    {
      label: '部门名称',
      key: 'keyword',
      type: 'input',
      props: {
        clearable: true,
        placeholder: '请输入部门名称'
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        clearable: true,
        options: [
          { label: '正常', value: '1' },
          { label: '禁用', value: '0' }
        ]
      }
    }
  ])

  // 状态配置
  const STATUS_CONFIG = {
    0: { type: 'warning' as const, text: '禁用' },
    1: { type: 'success' as const, text: '正常' }
  } as const

  // 表格列配置
  const { columnChecks, columns } = useTableColumns(() => [
    {
      prop: 'name',
      label: '部门名称',
      minWidth: 200
    },
    {
      prop: 'leader',
      label: '负责人',
      width: 100
    },
    {
      prop: 'phone',
      label: '联系电话',
      width: 130
    },
    {
      prop: 'email',
      label: '邮箱',
      width: 180
    },
    {
      prop: 'sort',
      label: '排序',
      width: 80
    },
    {
      prop: 'status',
      label: '状态',
      width: 100,
      formatter: (row: DeptListItem) => {
        const config = STATUS_CONFIG[row.status as keyof typeof STATUS_CONFIG] || {
          type: 'info',
          text: '未知'
        }
        return h(ElTag, { type: config.type }, () => config.text)
      }
    },
    {
      prop: 'create_time',
      label: '创建时间',
      width: 180
    },
    {
      prop: 'operation',
      label: '操作',
      width: 180,
      align: 'right',
      formatter: (row: DeptListItem) => {
        const buttons = []

        // 添加子部门
        if (hasAuth('system:dept:add')) {
          buttons.push(
            h(ArtButtonTable, {
              type: 'add',
              onClick: () => handleAddChild(row),
              title: '新增子部门'
            })
          )
        }

        // 编辑和删除按钮
        if (hasAuth('system:dept:edit')) {
          buttons.push(
            h(ArtButtonTable, {
              type: 'edit',
              onClick: () => handleEditDept(row)
            })
          )
        }
        if (hasAuth('system:dept:del')) {
          buttons.push(
            h(ArtButtonTable, {
              type: 'delete',
              onClick: () => handleDeleteDept(row)
            })
          )
        }

        return h('div', { style: 'text-align: right' }, buttons)
      }
    }
  ])

  // 数据相关
  const tableData = ref<DeptListItem[]>([])

  onMounted(() => {
    getDeptList()
  })

  /**
   * 获取部门列表数据
   */
  const getDeptList = async (): Promise<void> => {
    loading.value = true
    try {
      const list = await fetchGetDeptList(appliedFilters)
      tableData.value = list
    } catch (error) {
      console.error('获取部门列表失败:', error)
    } finally {
      loading.value = false
    }
  }

  /**
   * 重置搜索条件
   */
  const handleReset = (): void => {
    Object.assign(formFilters, { ...initialSearchState })
    Object.assign(appliedFilters, { ...initialSearchState })
    getDeptList()
  }

  /**
   * 执行搜索
   */
  const handleSearch = (): void => {
    Object.assign(appliedFilters, { ...formFilters })
    getDeptList()
  }

  /**
   * 刷新部门列表
   */
  const handleRefresh = (): void => {
    getDeptList()
  }

  /**
   * 搜索部门（递归过滤树形结构）
   */
  const searchDept = (items: DeptListItem[]): DeptListItem[] => {
    const results: DeptListItem[] = []
    const { keyword, status } = appliedFilters

    for (const item of items) {
      // 检查子节点是否有匹配
      let matchedChildren: DeptListItem[] = []
      if (item.children?.length) {
        matchedChildren = searchDept(item.children)
      }

      // 检查当前节点是否匹配
      let isMatch = true

      if (keyword && !item.name.includes(keyword)) {
        isMatch = false
      }

      if (status && String(item.status) !== status) {
        isMatch = false
      }

      // 如果当前节点匹配，或者有子节点匹配，则保留当前节点
      if (isMatch || matchedChildren.length > 0) {
        results.push({
          ...item,
          children: matchedChildren.length > 0 ? matchedChildren : item.children
        })
      }
    }

    return results
  }

  // 过滤后的表格数据
  const filteredTableData = computed(() => {
    return searchDept(tableData.value)
  })

  /**
   * 添加部门
   */
  const handleAddDept = (): void => {
    dialogType.value = 'add'
    editData.value = null
    dialogVisible.value = true
  }

  /**
   * 添加子部门
   */
  const handleAddChild = (row: DeptListItem): void => {
    dialogType.value = 'add'
    editData.value = { parent_id: row.id } as DeptListItem
    dialogVisible.value = true
  }

  /**
   * 编辑部门
   */
  const handleEditDept = (row: DeptListItem): void => {
    dialogType.value = 'edit'
    editData.value = { ...row }
    dialogVisible.value = true
  }

  /**
   * 提交表单数据
   */
  const handleSubmit = async (formData: Partial<DeptListItem>): Promise<void> => {
    try {
      if (dialogType.value === 'edit' && editData.value?.id) {
        await fetchUpdateDept(editData.value.id, formData)
      } else {
        await fetchCreateDept(formData)
      }
      ElMessage.success('保存成功')
      dialogVisible.value = false
      getDeptList()
    } catch (error) {
      console.error('提交失败:', error)
    }
  }

  /**
   * 删除部门
   */
  const handleDeleteDept = async (row: DeptListItem): Promise<void> => {
    try {
      await ElMessageBox.confirm(`确定要删除部门"${row.name}"吗？删除后无法恢复`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
      await fetchDeleteDept(row.id)
      ElMessage.success('删除成功')
      getDeptList()
    } catch (error) {
      if (error !== 'cancel') {
        ElMessage.error('删除失败')
      }
    }
  }

  /**
   * 切换展开/收起所有部门
   */
  const toggleExpand = (): void => {
    isExpanded.value = !isExpanded.value
    nextTick(() => {
      if (tableRef.value?.elTableRef && filteredTableData.value) {
        const processRows = (rows: DeptListItem[]) => {
          rows.forEach((row) => {
            if (row.children?.length) {
              tableRef.value.elTableRef.toggleRowExpansion(row, isExpanded.value)
              processRows(row.children)
            }
          })
        }
        processRows(filteredTableData.value)
      }
    })
  }
</script>
