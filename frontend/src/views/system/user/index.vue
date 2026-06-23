<!-- 用户管理页面 -->
<template>
  <div class="user-page art-full-height">
    <!-- 搜索栏 -->
    <UserSearch v-model="searchForm" @search="handleSearch" @reset="resetSearchParams"></UserSearch>

    <ElCard class="art-table-card">
      <!-- 表格头部 -->
      <ArtTableHeader v-model:columns="columnChecks" :loading="loading" @refresh="refreshData">
        <template #left>
          <ElSpace wrap>
            <ElButton v-auth="'system:user:add'" @click="showDialog('add')" v-ripple>新增用户</ElButton>
            <ElButton
              v-auth="'system:user:del'"
              type="danger"
              :disabled="!selectedRows.length"
              @click="handleBatchDelete"
              v-ripple
            >
              批量删除 {{ selectedRows.length ? `(${selectedRows.length})` : '' }}
            </ElButton>
          </ElSpace>
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
      >
      </ArtTable>

      <!-- 用户弹窗 -->
      <UserDialog
        v-model:visible="dialogVisible"
        :type="dialogType"
        :user-data="currentUserData"
        @submit="handleDialogSubmit"
      />

      <!-- 重置密码弹窗 -->
      <ResetPasswordDialog
        v-model:visible="resetDialogVisible"
        :user-data="resetUserData"
        @success="refreshData"
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import ArtButtonMore from '@/components/core/forms/art-button-more/index.vue'
  import { ButtonMoreItem } from '@/components/core/forms/art-button-more/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import {
    fetchGetUserList,
    fetchGetUser,
    fetchCreateUser,
    fetchUpdateUser,
    fetchDeleteUser,
    fetchToggleUserStatus
  } from '@/api/system-manage'
  import UserSearch from './modules/user-search.vue'
  import UserDialog from './modules/user-dialog.vue'
  import ResetPasswordDialog from './modules/reset-password-dialog.vue'
  import { ElTag, ElMessageBox, ElImage, ElMessage, ElSwitch } from 'element-plus'
  import { DialogType } from '@/types'

  defineOptions({ name: 'User' })

  type UserListItem = Api.SystemManage.UserListItem

  // 弹窗相关
  const dialogType = ref<DialogType>('add')
  const dialogVisible = ref(false)
  const currentUserData = ref<Partial<UserListItem>>({})

  // 重置密码弹窗
  const resetDialogVisible = ref(false)
  const resetUserData = ref<Partial<UserListItem>>({})

  // 选中行
  const selectedRows = ref<UserListItem[]>([])

  // 搜索表单 - 字段与后端对齐
  const searchForm = ref({
    keyword: '',
    status: '',
    dept_id: undefined as number | undefined
  })

  // 用户状态配置（与后端一致：1=正常, 0=禁用）
  const USER_STATUS_CONFIG = {
    0: { type: 'warning' as const, text: '禁用' },
    1: { type: 'success' as const, text: '正常' }
  } as const

  // 性别配置（与后端一致：0=未知, 1=男, 2=女）
  const SEX_CONFIG = {
    0: '未知',
    1: '男',
    2: '女'
  } as const

  /**
   * 获取用户状态配置
   */
  const getUserStatusConfig = (status: number) => {
    return (
      USER_STATUS_CONFIG[status as keyof typeof USER_STATUS_CONFIG] || {
        type: 'info' as const,
        text: '未知'
      }
    )
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
    // 核心配置
    core: {
      apiFn: fetchGetUserList,
      apiParams: {
        page: 1,
        limit: 20,
        ...searchForm.value
      },
      // 自定义分页字段映射（与后端一致）
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
          formatter: (row) => {
            return h('div', { class: 'user flex-c' }, [
              h(ElImage, {
                class: 'size-9.5 rounded-md',
                src: (row as UserListItem).avatar || '',
                previewSrcList: [(row as UserListItem).avatar || ''],
                previewTeleported: true
              }),
              h('div', { class: 'ml-2' }, [
                h('p', { class: 'user-name' }, (row as UserListItem).username),
              ])
            ])
          }
        },
        {
          prop: 'nickname',
          label: '昵称',
          width: 100
        },
        {
          prop: 'sex',
          label: '性别',
          width: 80,
          sortable: true,
          formatter: (row: UserListItem) => SEX_CONFIG[row.sex as keyof typeof SEX_CONFIG] || '未知'
        },
        { prop: 'mobile', label: '手机号', width: 130, formatter: (row: UserListItem) => row.mobile || '-' },
        { prop: 'email', label: '邮箱', width: 180, formatter: (row: UserListItem) => row.email || '-' },
        {
          prop: 'dept_name',
          label: '部门',
          width: 120,
          formatter: (row: UserListItem) => row.dept_name || '-'
        },
        {
          prop: 'role_names',
          label: '角色',
          width: 160,
          formatter: (row) => {
            const names = (row as UserListItem).role_names || []
            if (!names.length) return '-'
            return h(
              'div',
              { class: 'flex flex-wrap gap-1' },
              names.map((name) => h(ElTag, { size: 'small', type: 'info' }, () => name))
            )
          }
        },
        {
          prop: 'status',
          label: '状态',
          width: 120,
          formatter: (row) => {
            const statusConfig = getUserStatusConfig((row as UserListItem).status)
            return h(
              'div',
              { class: 'flex items-center gap-2' },
              [
                h(ElTag, { type: statusConfig.type }, () => statusConfig.text),
                h(ElSwitch, {
                  modelValue: (row as UserListItem).status === 1,
                  size: 'small',
                  onChange: () => handleToggleStatus(row)
                })
              ]
            )
          }
        },
        {
          prop: 'login_ip',
          label: '最后登录IP',
          width: 140,
          formatter: (row: UserListItem) => row.login_ip || '-'
        },
        {
          prop: 'login_time',
          label: '最后登录时间',
          width: 180,
          sortable: true,
          formatter: (row: UserListItem) => row.login_time || '-'
        },
        {
          prop: 'login_count',
          label: '登录次数',
          width: 120,
          sortable: true,
          formatter: (row: UserListItem) => row.login_count ?? 0
        },
        {
          prop: 'created_at',
          label: '创建时间',
          width: 180,
          sortable: true,
          formatter: (row: UserListItem) => row.created_at || '-'
        },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row) =>
            h('div', [
              h(ArtButtonMore, {
                list: [
                  {
                    key: 'edit',
                    label: '编辑',
                    icon: 'ri:edit-2-line',
                    auth: 'system:user:edit'
                  },
                  {
                    key: 'resetPwd',
                    label: '重置密码',
                    icon: 'ri:key-2-line',
                    auth: 'system:user:resetPwd'
                  },
                  {
                    key: 'delete',
                    label: '删除',
                    icon: 'ri:delete-bin-4-line',
                    color: '#f56c6c',
                    auth: 'system:user:del'
                  }
                ],
                onClick: (item: ButtonMoreItem) => handleMoreClick(item, row)
              })
            ])
        }
      ]
    }
  })

  /**
   * 搜索处理
   */
  const handleSearch = (params: any) => {
    replaceSearchParams(params)
    getData()
  }

  /**
   * 更多按钮点击
   */
  const handleMoreClick = (item: ButtonMoreItem, row: UserListItem) => {
    switch (item.key) {
      case 'edit':
        showDialog('edit', row)
        break
      case 'resetPwd':
        showResetPasswordDialog(row)
        break
      case 'delete':
        deleteUser(row)
        break
    }
  }

  /**
   * 显示用户弹窗
   * 编辑时拉取详情接口，获取 role_ids 用于角色回填
   */
  const showDialog = async (type: DialogType, row?: UserListItem): Promise<void> => {
    dialogType.value = type
    if (type === 'edit' && row) {
      try {
        const detail = await fetchGetUser(row.id)
        currentUserData.value = { ...row, ...detail }
      } catch (error) {
        currentUserData.value = { ...row }
        console.error('获取用户详情失败:', error)
      }
    } else {
      currentUserData.value = {}
    }
    nextTick(() => {
      dialogVisible.value = true
    })
  }

  /**
   * 显示重置密码弹窗
   */
  const showResetPasswordDialog = (row: UserListItem) => {
    resetUserData.value = row
    nextTick(() => {
      resetDialogVisible.value = true
    })
  }

  /**
   * 切换用户状态
   */
  const handleToggleStatus = async (row: UserListItem) => {
    try {
      await fetchToggleUserStatus(row.id)
      ElMessage.success('状态更新成功')
      refreshData()
    } catch (error) {
      console.error('状态更新失败:', error)
    }
  }

  /**
   * 删除用户
   */
  const deleteUser = (row: UserListItem): void => {
    ElMessageBox.confirm(`确定要删除用户"${row.username}"吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteUser(row.id)
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
  const handleBatchDelete = (): void => {
    if (!selectedRows.value.length) return

    const names = selectedRows.value.map((r) => r.username).join('、')
    ElMessageBox.confirm(
      `确定要删除选中的 ${selectedRows.value.length} 个用户吗？\n${names}`,
      '批量删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
      .then(async () => {
        const promises = selectedRows.value.map((row) => fetchDeleteUser(row.id))
        await Promise.all(promises)
        ElMessage.success('批量删除成功')
        selectedRows.value = []
        refreshData()
      })
      .catch(() => {
        ElMessage.info('已取消删除')
      })
  }

  /**
   * 处理弹窗提交事件
   */
  const handleDialogSubmit = async (formData: Api.SystemManage.UserSubmitParams) => {
    try {
      if (dialogType.value === 'add') {
        await fetchCreateUser(formData)
      } else {
        await fetchUpdateUser(currentUserData.value.id!, formData)
      }
      dialogVisible.value = false
      currentUserData.value = {}
      refreshData()
    } catch (error) {
      console.error('提交失败:', error)
    }
  }

  /**
   * 处理表格行选择变化
   */
  const handleSelectionChange = (selection: UserListItem[]): void => {
    selectedRows.value = selection
  }
</script>
