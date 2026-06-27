<!-- 微信用户管理页面 -->
<template>
  <div class="wechat-user-page art-full-height">
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
          <ElSpace>
            <ElButton type="primary" @click="syncUsers" v-ripple v-auth="'wechat:user:sync'">
              <el-icon class="mr-1"><Refresh /></el-icon>同步用户
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
      />
    </ElCard>

    <!-- 用户详情对话框 -->
    <ElDialog v-model="detailDialogVisible" title="用户详情" width="600px" align-center>
      <ElDescriptions :column="2" border>
        <ElDescriptionsItem label="头像">
          <el-avatar
            :size="64"
            :src="resolveAvatarUrl(currentUser?.avatar ?? currentUser?.headimgurl)"
          />
        </ElDescriptionsItem>
        <ElDescriptionsItem label="OpenID">{{ currentUser?.openid }}</ElDescriptionsItem>
        <ElDescriptionsItem label="昵称">{{ currentUser?.nickname || '-' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="性别">{{
          getGenderText(currentUser?.gender ?? currentUser?.sex)
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="国家">{{ currentUser?.country || '-' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="省份">{{ currentUser?.province || '-' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="城市">{{ currentUser?.city || '-' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="语言">{{ currentUser?.language || '-' }}</ElDescriptionsItem>
        <ElDescriptionsItem label="应用类型">{{
          getAppTypeText(currentUser?.app_type)
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="是否关注">
          <el-tag :type="currentUser?.subscribe ? 'success' : 'info'">
            {{ currentUser?.subscribe ? '已关注' : '未关注' }}
          </el-tag>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="关注时间">{{
          currentUser?.subscribe_time || '-'
        }}</ElDescriptionsItem>
        <ElDescriptionsItem label="更新时间">{{
          currentUser?.updated_at || '-'
        }}</ElDescriptionsItem>
      </ElDescriptions>
      <template #footer>
        <ElButton @click="detailDialogVisible = false">关闭</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, h } from 'vue'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { fetchWeChatUserList, fetchWeChatUserDetail, syncWeChatUsers } from '@/api/wechat'
  import {
    ElTag,
    ElMessageBox,
    ElMessage,
    ElAvatar,
    ElDescriptions,
    ElDescriptionsItem
  } from 'element-plus'
  import { Refresh } from '@element-plus/icons-vue'

  defineOptions({ name: 'WeChatUser' })

  type WeChatUserListItem = {
    id: number
    openid: string
    unionid?: string
    nickname?: string
    /** 后端字段为 gender（0未知/1男/2女），保留旧名 sex 做兼容 */
    gender?: number
    sex?: number
    /** 后端字段为 avatar，保留旧名 headimgurl 做兼容 */
    avatar?: string
    headimgurl?: string
    country?: string
    province?: string
    city?: string
    language?: string
    subscribe?: boolean | number
    subscribe_time?: string
    subscribe_scene?: string
    app_type?: string
    remark?: string
    group_id?: number
    tagid_list?: number[]
    created_at?: string
    updated_at?: string
  }

  // 详情对话框
  const detailDialogVisible = ref(false)
  const currentUser = ref<WeChatUserListItem | null>(null)

  // 选中行
  const selectedRows = ref<WeChatUserListItem[]>([])

  // 搜索表单
  const searchForm = ref({
    keyword: '',
    app_type: ''
  })

  const formFilters = reactive({ ...searchForm.value })
  const appliedFilters = reactive({ ...searchForm.value })

  const formItems = computed(() => [
    {
      label: '昵称',
      key: 'keyword',
      type: 'input',
      props: {
        clearable: true,
        placeholder: '请输入昵称或OpenID'
      }
    },
    {
      label: '应用类型',
      key: 'app_type',
      type: 'select',
      props: {
        clearable: true,
        options: [
          { label: '公众号', value: 'official_account' },
          { label: '小程序', value: 'mini_program' },
          { label: '企业微信', value: 'work_wechat' }
        ]
      }
    }
  ])

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
      apiFn: fetchWeChatUserList,
      apiParams: {
        page: 1,
        limit: 20,
        ...appliedFilters
      },
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => [
        { type: 'selection' },
        {
          prop: 'avatar',
          label: '头像',
          width: 80,
          formatter: (row: WeChatUserListItem) => {
            return h(ElAvatar, {
              size: 40,
              src: resolveAvatarUrl(row.avatar ?? row.headimgurl)
            })
          }
        },
        {
          prop: 'nickname',
          label: '昵称',
          width: 150,
          formatter: (row: WeChatUserListItem) => row.nickname || '-'
        },
        { prop: 'openid', label: 'OpenID', width: 200, showOverflowTooltip: true },
        {
          prop: 'gender',
          label: '性别',
          width: 80,
          formatter: (row: WeChatUserListItem) => getGenderText(row.gender ?? row.sex)
        },
        {
          prop: 'country',
          label: '国家',
          width: 100,
          formatter: (row: WeChatUserListItem) => row.country || '-'
        },
        {
          prop: 'city',
          label: '城市',
          width: 100,
          formatter: (row: WeChatUserListItem) => row.city || '-'
        },
        {
          prop: 'subscribe',
          label: '关注状态',
          width: 100,
          formatter: (row: WeChatUserListItem) => {
            const subscribed = Boolean(row.subscribe)
            return h(ElTag, { type: subscribed ? 'success' : 'info' }, () =>
              subscribed ? '已关注' : '未关注'
            )
          }
        },
        {
          prop: 'app_type',
          label: '应用类型',
          width: 100,
          formatter: (row: WeChatUserListItem) => getAppTypeText(row.app_type)
        },
        { prop: 'subscribe_time', label: '关注时间', width: 180, sortable: true },
        { prop: 'updated_at', label: '更新时间', width: 180, sortable: true },
        {
          prop: 'operation',
          label: '操作',
          width: 120,
          fixed: 'right',
          formatter: (row: WeChatUserListItem) =>
            h('div', [
              h(ArtButtonTable, {
                type: 'view',
                onClick: () => viewUserDetail(row),
                title: '详情'
              })
            ])
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
   * 同步用户
   */
  const syncUsers = async (): Promise<void> => {
    ElMessageBox.confirm('确定要从微信服务器同步用户数据吗？此操作可能需要一些时间。', '同步确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        try {
          await syncWeChatUsers({ app_type: appliedFilters.app_type })
          ElMessage.success('同步任务已启动，请稍后刷新查看结果')
          setTimeout(() => refreshData(), 2000)
        } catch (error) {
          console.error('同步失败:', error)
          ElMessage.error('同步失败，请稍后重试')
        }
      })
      .catch(() => {
        ElMessage.info('已取消同步')
      })
  }

  /**
   * 查看用户详情
   */
  const viewUserDetail = async (row: WeChatUserListItem): Promise<void> => {
    try {
      const detail = await fetchWeChatUserDetail({ openid: row.openid, app_type: row.app_type })
      currentUser.value = (detail as WeChatUserListItem | null) || row
      detailDialogVisible.value = true
    } catch (error) {
      console.error('获取用户详情失败:', error)
      ElMessage.error('获取用户详情失败')
    }
  }

  /**
   * 处理表格行选择变化
   */
  const handleSelectionChange = (selection: WeChatUserListItem[]): void => {
    selectedRows.value = selection
  }

  /**
   * 获取性别文本
   */
  const getGenderText = (sex?: number): string => {
    const genderMap: Record<number, string> = {
      0: '未知',
      1: '男',
      2: '女'
    }
    return genderMap[sex || 0] || '未知'
  }

  /**
   * 获取应用类型文本
   */
  const getAppTypeText = (appType?: string): string => {
    const typeMap: Record<string, string> = {
      official_account: '公众号',
      mini_program: '小程序',
      work_wechat: '企业微信'
    }
    return typeMap[appType || ''] || appType || '-'
  }

  /**
   * 解析头像URL（微信头像URL可能是相对路径或绝对路径）
   */
  const resolveAvatarUrl = (url?: string): string => {
    if (!url) return ''
    if (/^https?:\/\//.test(url)) return url
    return (import.meta.env.VITE_API_URL || '') + url
  }
</script>

<style scoped lang="scss">
  .wechat-user-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
</style>
