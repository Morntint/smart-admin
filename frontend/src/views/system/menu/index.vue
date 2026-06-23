<!-- 菜单管理页面 -->
<template>
  <div class="menu-page art-full-height">
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
          <ElButton v-auth="'system:menu:add'" @click="handleAddMenu" v-ripple> 添加菜单 </ElButton>
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

      <!-- 菜单弹窗 -->
      <MenuDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :edit-data="editData"
        :lock-type="lockMenuType"
        @submit="handleSubmit"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTableColumns } from '@/hooks/core/useTableColumns'
  import { useAuth } from '@/hooks/core/useAuth'
  import MenuDialog from './modules/menu-dialog.vue'
  import {
    fetchGetMenuList,
    fetchCreateMenu,
    fetchUpdateMenu,
    fetchDeleteMenu
  } from '@/api/system-manage'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  defineOptions({ name: 'Menus' })

  const { hasAuth } = useAuth()

  // 状态管理
  const loading = ref(false)
  const isExpanded = ref(false)
  const tableRef = ref()

  // 弹窗相关
  const dialogVisible = ref(false)
  const dialogType = ref<'menu' | 'button'>('menu')
  const editData = ref<any>(null)
  const lockMenuType = ref(false)

  // 搜索相关
  const initialSearchState = {
    type: ''
  }

  const formFilters = reactive({ ...initialSearchState })
  const appliedFilters = reactive({ ...initialSearchState })

  const formItems = computed(() => [
    {
      label: '菜单类型',
      key: 'type',
      type: 'select',
      props: {
        clearable: true,
        options: [
          { label: '目录', value: '1' },
          { label: '菜单', value: '2' },
          { label: '按钮', value: '3' }
        ]
      }
    }
  ])

  onMounted(() => {
    getMenuList()
  })

  // 菜单类型配置
  const MENU_TYPE_CONFIG = {
    1: { type: 'info' as const, text: '目录' },
    2: { type: 'primary' as const, text: '菜单' },
    3: { type: 'danger' as const, text: '按钮' }
  } as const

  const STATUS_CONFIG = {
    0: { type: 'warning' as const, text: '禁用' },
    1: { type: 'success' as const, text: '正常' }
  } as const

  /**
   * 获取菜单列表数据
   */
  const getMenuList = async (): Promise<void> => {
    loading.value = true

    try {
      const list = await fetchGetMenuList(appliedFilters)
      tableData.value = list
    } catch (error) {
      throw error instanceof Error ? error : new Error('获取菜单失败')
    } finally {
      loading.value = false
    }
  }

  // 表格列配置
  const { columnChecks, columns } = useTableColumns(() => [
    {
      prop: 'name',
      label: '菜单名称',
      minWidth: 150
    },
    {
      prop: 'type',
      label: '菜单类型',
      width: 100,
      formatter: (row: any) => {
        const config = MENU_TYPE_CONFIG[row.type as keyof typeof MENU_TYPE_CONFIG] || {
          type: 'info',
          text: '未知'
        }
        return h(ElTag, { type: config.type }, () => config.text)
      }
    },
    {
      prop: 'path',
      label: '路由路径',
      minWidth: 150
    },
    {
      prop: 'component',
      label: '组件路径',
      minWidth: 150
    },
    {
      prop: 'permission',
      label: '权限标识',
      minWidth: 150
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
      formatter: (row: any) => {
        const config = STATUS_CONFIG[row.status as keyof typeof STATUS_CONFIG] || {
          type: 'info',
          text: '未知'
        }
        return h(ElTag, { type: config.type }, () => config.text)
      }
    },
    {
      prop: 'created_at',
      label: '创建时间',
      width: 180
    },
    {
      prop: 'operation',
      label: '操作',
      width: 220,
      align: 'right',
      formatter: (row: any) => {
        const buttonStyle = { style: 'text-align: right' }
        const buttons = []

        // 目录(type=1)：可以添加子菜单
        if (row.type === 1 && hasAuth('system:menu:add')) {
          buttons.push(
            h(ArtButtonTable, {
              type: 'add',
              onClick: () => handleAddChild(row),
              title: '新增子菜单'
            })
          )
        }
        // 菜单(type=2)：可以添加按钮
        if (row.type === 2 && hasAuth('system:menu:add')) {
          buttons.push(
            h(ArtButtonTable, {
              type: 'add',
              onClick: () => handleAddButton(row),
              title: '新增按钮'
            })
          )
        }

        // 编辑和删除按钮
        if (hasAuth('system:menu:edit')) {
          buttons.push(
            h(ArtButtonTable, {
              type: 'edit',
              onClick: () => handleEditMenu(row)
            })
          )
        }
        if (hasAuth('system:menu:del')) {
          buttons.push(
            h(ArtButtonTable, {
              type: 'delete',
              onClick: () => handleDeleteMenu(row)
            })
          )
        }

        return h('div', buttonStyle, buttons)
      }
    }
  ])

  // 数据相关
  const tableData = ref<any[]>([])

  /**
   * 重置搜索条件
   */
  const handleReset = (): void => {
    Object.assign(formFilters, { ...initialSearchState })
    Object.assign(appliedFilters, { ...initialSearchState })
    getMenuList()
  }

  /**
   * 执行搜索
   */
  const handleSearch = (): void => {
    Object.assign(appliedFilters, { ...formFilters })
    getMenuList()
  }

  /**
   * 刷新菜单列表
   */
  const handleRefresh = (): void => {
    getMenuList()
  }

  /**
   * 搜索菜单
   * @param items 菜单项数组
   * @returns 搜索结果数组
   */
  const searchMenu = (items: any[]): any[] => {
    const results: any[] = []
    const searchType = appliedFilters.type

    for (const item of items) {
      if (item.children?.length) {
        const matchedChildren = searchMenu(item.children)
        if (matchedChildren.length > 0) {
          const clonedItem = { ...item, children: matchedChildren }
          results.push(clonedItem)
          continue
        }
      }

      if (!searchType || String(item.type) === searchType) {
        results.push({ ...item })
      }
    }

    return results
  }

  // 过滤后的表格数据
  const filteredTableData = computed(() => {
    return searchMenu(tableData.value)
  })

  /**
   * 添加菜单
   */
  const handleAddMenu = (): void => {
    // 默认新增为菜单类型，用户可切换为目录或按钮
    dialogType.value = 'menu'
    editData.value = null
    lockMenuType.value = false // 新增时允许切换类型
    dialogVisible.value = true
  }

  /**
   * 添加子菜单（目录下的操作）
   * 目录可以添加 目录 或 菜单（类型可切换）
   */
  const handleAddChild = (row: any): void => {
    dialogType.value = 'menu' // 默认菜单类型，用户可以切换为目录
    editData.value = { parent_id: row.id }
    lockMenuType.value = false // 目录下可以选择类型
    dialogVisible.value = true
  }

  /**
   * 添加按钮（菜单下的操作）
   * 菜单下只能添加按钮（类型锁定）
   */
  const handleAddButton = (row: any): void => {
    dialogType.value = 'button'
    editData.value = { parent_id: row.id }
    lockMenuType.value = true // 锁定为按钮类型，不可切换
    dialogVisible.value = true
  }

  /**
   * 编辑菜单
   * @param row 菜单行数据
   */
  const handleEditMenu = (row: any): void => {
    // 类型由 loadFormData 根据 row.type 自动设置
    dialogType.value = 'menu'
    editData.value = { ...row }
    lockMenuType.value = true // 编辑时锁定类型不可修改
    dialogVisible.value = true
  }

  /**
   * 提交表单数据
   * @param formData 表单数据
   */
  const handleSubmit = async (formData: any): Promise<void> => {
    try {
      if (formData.id) {
        await fetchUpdateMenu(formData.id, formData)
      } else {
        await fetchCreateMenu(formData)
      }
      ElMessage.success('保存成功')
      dialogVisible.value = false
      getMenuList()
    } catch (error) {
      console.error('提交失败:', error)
    }
  }

  /**
   * 删除菜单
   */
  const handleDeleteMenu = async (row: any): Promise<void> => {
    try {
      await ElMessageBox.confirm('确定要删除该菜单吗？删除后无法恢复', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      })
      await fetchDeleteMenu(row.id)
      ElMessage.success('删除成功')
      getMenuList()
    } catch (error) {
      if (error !== 'cancel') {
        ElMessage.error('删除失败')
      }
    }
  }

  /**
   * 切换展开/收起所有菜单
   */
  const toggleExpand = (): void => {
    isExpanded.value = !isExpanded.value
    nextTick(() => {
      if (tableRef.value?.elTableRef && filteredTableData.value) {
        const processRows = (rows: any[]) => {
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
