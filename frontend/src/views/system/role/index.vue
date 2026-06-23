<!-- 角色管理页面 -->
<template>
  <div class="art-full-height">
    <RoleSearch
      v-show="showSearchBar"
      v-model="searchForm"
      @search="handleSearch"
      @reset="resetSearchParams"
    ></RoleSearch>

    <ElCard class="art-table-card" :style="{ 'margin-top': showSearchBar ? '12px' : '0' }">
      <ArtTableHeader
        v-model:columns="columnChecks"
        v-model:showSearchBar="showSearchBar"
        :loading="loading"
        @refresh="refreshData"
      >
        <template #left>
          <ElSpace wrap>
            <ElButton v-auth="'system:role:add'" @click="showDialog('add')" v-ripple>新增角色</ElButton>
          </ElSpace>
        </template>
      </ArtTableHeader>

      <!-- 表格 -->
      <ArtTable
        :loading="loading"
        :data="data"
        :columns="columns"
        :pagination="pagination"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      >
      </ArtTable>
    </ElCard>

    <!-- 角色编辑弹窗 -->
    <RoleEditDialog
      v-model="dialogVisible"
      :dialog-type="dialogType"
      :role-data="currentRoleData"
      @success="refreshData"
    />

    <!-- 菜单权限弹窗 -->
    <RolePermissionDialog
      v-model="permissionDialog"
      :role-data="currentRoleData"
      @success="refreshData"
    />

    <!-- 数据范围弹窗 -->
    <RoleDataScopeDialog
      v-model="dataScopeDialog"
      :role-data="currentRoleData"
      @success="refreshData"
    />
  </div>
</template>

<script setup lang="ts">
  import { ButtonMoreItem } from '@/components/core/forms/art-button-more/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import {
    fetchGetRoleList,
    fetchDeleteRole,
    fetchCreateRole,
    fetchUpdateRole
  } from '@/api/system-manage'
  import ArtButtonMore from '@/components/core/forms/art-button-more/index.vue'
  import RoleSearch from './modules/role-search.vue'
  import RoleEditDialog from './modules/role-edit-dialog.vue'
  import RolePermissionDialog from './modules/role-permission-dialog.vue'
  import RoleDataScopeDialog from './modules/role-data-scope-dialog.vue'
  import { ElTag, ElMessageBox, ElMessage } from 'element-plus'

  defineOptions({ name: 'Role' })

  type RoleListItem = Api.SystemManage.RoleListItem

  // 搜索表单 - 字段与后端对齐
  const searchForm = ref({
    keyword: '',
    status: ''
  })

  const showSearchBar = ref(false)

  const dialogVisible = ref(false)
  const permissionDialog = ref(false)
  const dataScopeDialog = ref(false)
  const currentRoleData = ref<RoleListItem | undefined>(undefined)

  // 状态配置（与后端一致：1=正常, 0=禁用）
  const STATUS_CONFIG = {
    0: { type: 'warning' as const, text: '禁用' },
    1: { type: 'success' as const, text: '正常' }
  } as const

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
    // 核心配置
    core: {
      apiFn: fetchGetRoleList,
      apiParams: {
        page: 1,
        limit: 20,
        ...searchForm.value
      },
      // 自定义分页字段映射
      paginationKey: {
        current: 'page',
        size: 'limit'
      },
      columnsFactory: () => [
        {
          prop: 'id',
          label: '角色ID',
          width: 100
        },
        {
          prop: 'name',
          label: '角色名称',
          minWidth: 120
        },
        {
          prop: 'code',
          label: '角色编码',
          minWidth: 120
        },
        {
          prop: 'data_scope',
          label: '数据范围',
          width: 100,
          formatter: (row) => {
            const scopeMap: Record<number, string> = {
              1: '全部',
              2: '本部门',
              3: '本部门及下级',
              4: '仅本人',
              5: '自定义'
            }
            return scopeMap[row.data_scope] || '未知'
          }
        },
        {
          prop: 'status',
          label: '角色状态',
          width: 100,
          formatter: (row) => {
            const statusConfig = STATUS_CONFIG[row.status as keyof typeof STATUS_CONFIG] || {
              type: 'info',
              text: '未知'
            }
            return h(
              ElTag,
              { type: statusConfig.type as 'success' | 'warning' },
              () => statusConfig.text
            )
          }
        },
        {
          prop: 'created_at',
          label: '创建日期',
          width: 180,
          sortable: true
        },
        {
          prop: 'operation',
          label: '操作',
          width: 80,
          fixed: 'right',
          formatter: (row) =>
            h('div', [
              h(ArtButtonMore, {
                list: [
                  {
                    key: 'permission',
                    label: '菜单权限',
                    icon: 'ri:user-3-line',
                    auth: 'system:role:assign'
                  },
                  {
                    key: 'dataScope',
                    label: '数据范围',
                    icon: 'ri:shield-keyhole-line',
                    auth: 'system:role:assign'
                  },
                  {
                    key: 'edit',
                    label: '编辑角色',
                    icon: 'ri:edit-2-line',
                    auth: 'system:role:edit'
                  },
                  {
                    key: 'delete',
                    label: '删除角色',
                    icon: 'ri:delete-bin-4-line',
                    color: '#f56c6c',
                    auth: 'system:role:del'
                  }
                ],
                onClick: (item: ButtonMoreItem) => buttonMoreClick(item, row)
              })
            ])
        }
      ]
    }
  })

  const dialogType = ref<'add' | 'edit'>('add')

  const showDialog = (type: 'add' | 'edit', row?: RoleListItem) => {
    dialogVisible.value = true
    dialogType.value = type
    currentRoleData.value = row
  }

  /**
   * 搜索处理
   * @param params 搜索参数
   */
  const handleSearch = (params: any) => {
    replaceSearchParams(params)
    getData()
  }

  const buttonMoreClick = (item: ButtonMoreItem, row: RoleListItem) => {
    switch (item.key) {
      case 'permission':
        showPermissionDialog(row)
        break
      case 'dataScope':
        showDataScopeDialog(row)
        break
      case 'edit':
        showDialog('edit', row)
        break
      case 'delete':
        deleteRole(row)
        break
    }
  }

  const showPermissionDialog = (row?: RoleListItem) => {
    permissionDialog.value = true
    currentRoleData.value = row
  }

  const showDataScopeDialog = (row?: RoleListItem) => {
    dataScopeDialog.value = true
    currentRoleData.value = row
  }

  const deleteRole = (row: RoleListItem) => {
    ElMessageBox.confirm(`确定删除角色"${row.name}"吗？此操作不可恢复！`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteRole(row.id)
        ElMessage.success('删除成功')
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }
</script>
