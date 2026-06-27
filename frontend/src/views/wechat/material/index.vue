<!-- 微信素材管理页面 -->
<template>
  <div class="wechat-material-page art-full-height">
    <!-- 搜索栏 -->
    <ArtSearchBar
      v-model="formFilters"
      :items="formItems"
      :showExpand="false"
      @reset="handleReset"
      @search="handleSearch"
    />

    <ElCard class="art-table-card">
      <!-- 表格头部：左侧操作按钮 / 右侧素材类型 tab -->
      <ArtTableHeader :loading="loading" v-model:columns="columnChecks" @refresh="refreshData">
        <template #left>
          <ElSpace>
            <ElButton
              type="primary"
              @click="syncMaterials"
              v-ripple
              v-auth="'wechat:material:sync'"
            >
              <el-icon class="mr-1"><Refresh /></el-icon>同步素材
            </ElButton>
            <ElButton @click="showUploadDialog" v-ripple>
              <el-icon class="mr-1"><Upload /></el-icon>上传素材
            </ElButton>
          </ElSpace>
        </template>
        <template #right>
          <ElRadioGroup v-model="materialType" size="small" @change="handleTypeChange">
            <ElRadioButton value="image">图片</ElRadioButton>
            <ElRadioButton value="voice">语音</ElRadioButton>
            <ElRadioButton value="video">视频</ElRadioButton>
            <ElRadioButton value="news">图文</ElRadioButton>
          </ElRadioGroup>
        </template>
      </ArtTableHeader>

      <!-- 表格 -->
      <ArtTable
        :loading="loading"
        :data="data"
        :columns="tableColumns"
        :pagination="pagination"
        @selection-change="handleSelectionChange"
        @pagination:size-change="handleSizeChange"
        @pagination:current-change="handleCurrentChange"
      />
    </ElCard>

    <!-- 上传素材对话框 -->
    <ElDialog v-model="uploadDialogVisible" title="上传素材" width="600px" align-center>
      <ElForm :model="uploadForm" label-width="100px">
        <ElFormItem label="素材类型" required>
          <ElSelect v-model="uploadForm.type">
            <ElOption label="图片（image）" value="image" />
            <ElOption label="语音（voice）" value="voice" />
            <ElOption label="视频（video）" value="video" />
            <ElOption label="缩略图（thumb）" value="thumb" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="素材文件" required>
          <ElUpload
            ref="uploadRef"
            :action="uploadUrl"
            :headers="uploadHeaders"
            :data="{ type: uploadForm.type }"
            :on-success="handleUploadSuccess"
            :on-error="handleUploadError"
            :before-upload="beforeUpload"
            :limit="1"
            :auto-upload="false"
            drag
          >
            <el-icon class="el-icon--upload"><upload-filled /></el-icon>
            <div class="el-upload__text">将文件拖到此处，或<em>点击上传</em></div>
            <template #tip>
              <div class="el-upload__tip">
                <template v-if="uploadForm.type === 'image'"
                  >支持 JPG、PNG 格式，大小不超过 2MB</template
                >
                <template v-else-if="uploadForm.type === 'voice'"
                  >支持 MP3、WMA、WAV、AMR 格式，大小不超过 2MB，时长不超过 60 秒</template
                >
                <template v-else-if="uploadForm.type === 'video'"
                  >支持 MP4 格式，大小不超过 10MB</template
                >
                <template v-else-if="uploadForm.type === 'thumb'"
                  >支持 JPG 格式，大小不超过 64KB，建议 640x320</template
                >
              </div>
            </template>
          </ElUpload>
        </ElFormItem>
        <ElFormItem v-if="uploadForm.type === 'video'" label="视频标题">
          <ElInput v-model="uploadForm.title" placeholder="请输入视频标题" />
        </ElFormItem>
        <ElFormItem v-if="uploadForm.type === 'video'" label="视频描述">
          <ElInput
            v-model="uploadForm.introduction"
            type="textarea"
            :rows="3"
            placeholder="请输入视频描述"
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="uploadDialogVisible = false">取消</ElButton>
        <ElButton type="primary" @click="submitUpload" :loading="uploading">上传</ElButton>
      </template>
    </ElDialog>

    <!-- 预览对话框 -->
    <ElDialog v-model="previewDialogVisible" title="素材预览" width="800px" align-center>
      <div class="preview-container">
        <img
          v-if="currentMaterial?.type === 'image'"
          :src="resolveMediaUrl(currentMaterial?.url)"
          class="preview-image"
          alt="预览图片"
        />
        <video
          v-else-if="currentMaterial?.type === 'video'"
          :src="resolveMediaUrl(currentMaterial?.url)"
          class="preview-video"
          controls
        />
        <audio
          v-else-if="currentMaterial?.type === 'voice'"
          :src="resolveMediaUrl(currentMaterial?.url)"
          class="preview-audio"
          controls
        />
        <div v-else-if="currentMaterial?.type === 'news'" class="preview-news">
          <el-empty description="图文素材预览功能开发中" />
        </div>
        <el-empty v-else description="该素材类型暂不支持预览" />
      </div>
      <div class="preview-info">
        <ElDescriptions :column="2" border size="small">
          <ElDescriptionsItem label="素材ID">{{ currentMaterial?.media_id }}</ElDescriptionsItem>
          <ElDescriptionsItem label="素材名称">{{
            currentMaterial?.title || currentMaterial?.name || '-'
          }}</ElDescriptionsItem>
          <ElDescriptionsItem label="文件大小">{{
            formatSize(currentMaterial?.size)
          }}</ElDescriptionsItem>
          <ElDescriptionsItem label="更新时间">{{
            currentMaterial?.updated_at || currentMaterial?.created_at || '-'
          }}</ElDescriptionsItem>
        </ElDescriptions>
      </div>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, h } from 'vue'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { fetchWeChatMaterialList, syncWeChatMaterials } from '@/api/wechat'
  import { useUserStore } from '@/store/modules/user'
  import {
    ElTag,
    ElMessageBox,
    ElMessage,
    ElUpload,
    ElDescriptions,
    ElDescriptionsItem
  } from 'element-plus'
  import { Refresh, Upload, UploadFilled, VideoPlay, Microphone } from '@element-plus/icons-vue'
  import type { UploadInstance, UploadProps, UploadRawFile } from 'element-plus'

  defineOptions({ name: 'WeChatMaterial' })

  type WeChatMaterialListItem = {
    id: number
    media_id: string
    name?: string
    title?: string
    type: string
    url?: string
    size?: number
    created_at?: string
    updated_at?: string
    app_type?: string
  }

  const userStore = useUserStore()

  // 素材类型
  const materialType = ref<'image' | 'voice' | 'video' | 'news'>('image')

  // 上传相关
  const uploadRef = ref<UploadInstance>()
  const uploadDialogVisible = ref(false)
  const uploading = ref(false)
  const uploadForm = reactive({
    type: 'image' as string,
    title: '',
    introduction: ''
  })

  // 预览相关
  const previewDialogVisible = ref(false)
  const currentMaterial = ref<WeChatMaterialListItem | null>(null)

  // 选中行
  const selectedRows = ref<WeChatMaterialListItem[]>([])

  // 搜索表单
  const searchForm = ref({
    keyword: '',
    app_type: ''
  })

  const formFilters = reactive({ ...searchForm.value })
  const appliedFilters = reactive({ ...searchForm.value })

  const formItems = computed(() => [
    {
      label: '素材名称',
      key: 'keyword',
      type: 'input',
      props: { clearable: true, placeholder: '请输入素材名称或ID' }
    },
    {
      label: '应用类型',
      key: 'app_type',
      type: 'select',
      props: {
        clearable: true,
        options: [
          { label: '公众号', value: 'official_account' },
          { label: '小程序', value: 'mini_program' }
        ]
      }
    }
  ])

  // 上传地址
  const uploadUrl = computed(
    () => (import.meta.env.VITE_API_URL || '') + '/admin/wechat/materials/upload'
  )

  // 上传请求头
  const uploadHeaders = computed(() => ({
    Authorization: userStore.accessToken ? `Bearer ${userStore.accessToken}` : ''
  }))

  const {
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
      apiFn: fetchWeChatMaterialList,
      apiParams: {
        page: 1,
        limit: 20,
        type: materialType.value,
        ...appliedFilters
      },
      paginationKey: { current: 'page', size: 'limit' },
      columnsFactory: () => getColumnsByType(materialType.value)
    }
  })

  // 列定义跟随素材类型变化（避免静态缓存导致切换 tab 后表头不更新）
  const tableColumns = computed(() => getColumnsByType(materialType.value))

  /**
   * 根据类型获取列配置
   */
  function getColumnsByType(type: string) {
    const baseColumns: any[] = [
      { type: 'selection' },
      {
        prop: 'thumbnail',
        label: '缩略图',
        width: 100,
        formatter: (row: WeChatMaterialListItem) => {
          if (row.url && (type === 'image' || type === 'video')) {
            return h('img', {
              src: resolveMediaUrl(row.url),
              style: 'width: 60px; height: 60px; object-fit: cover; border-radius: 4px;'
            })
          }
          if (type === 'voice') {
            return h(Microphone, { style: 'font-size: 32px; color: var(--el-color-primary)' })
          }
          if (type === 'video') {
            return h(VideoPlay, { style: 'font-size: 32px; color: var(--el-color-primary)' })
          }
          return '-'
        }
      },
      {
        prop: 'title',
        label: '素材名称',
        minWidth: 200,
        showOverflowTooltip: true,
        formatter: (row: WeChatMaterialListItem) => row.title || row.name || '-'
      },
      { prop: 'media_id', label: '素材ID', width: 200, showOverflowTooltip: true }
    ]

    const extraColumns =
      type === 'news'
        ? [
            { prop: 'title', label: '标题', minWidth: 200, showOverflowTooltip: true },
            { prop: 'author', label: '作者', width: 120 },
            { prop: 'digest', label: '摘要', minWidth: 250, showOverflowTooltip: true }
          ]
        : [
            {
              prop: 'size',
              label: '文件大小',
              width: 120,
              formatter: (row: WeChatMaterialListItem) => formatSize(row.size)
            }
          ]

    const endColumns: any[] = [
      {
        prop: 'type',
        label: '类型',
        width: 100,
        formatter: (row: WeChatMaterialListItem) => {
          return h(ElTag, { type: getTypeTagType(row.type), size: 'small' }, () =>
            getTypeText(row.type)
          )
        }
      },
      { prop: 'created_at', label: '创建时间', width: 180, sortable: true },
      { prop: 'updated_at', label: '更新时间', width: 180, sortable: true },
      {
        prop: 'operation',
        label: '操作',
        width: 100,
        fixed: 'right',
        formatter: (row: WeChatMaterialListItem) =>
          h('div', [
            h(ArtButtonTable, {
              type: 'view',
              onClick: () => viewMaterial(row),
              title: '预览'
            })
          ])
      }
    ]

    return [...baseColumns, ...extraColumns, ...endColumns]
  }

  /**
   * 搜索处理
   */
  const handleSearch = (): void => {
    Object.assign(appliedFilters, { ...formFilters })
    replaceSearchParams({ ...appliedFilters, type: materialType.value })
    getData()
  }

  /**
   * 重置搜索
   */
  const handleReset = (): void => {
    Object.assign(formFilters, { ...searchForm.value })
    Object.assign(appliedFilters, { ...searchForm.value })
    resetSearchParams()
    replaceSearchParams({ ...appliedFilters, type: materialType.value })
    getData()
  }

  /**
   * 类型切换
   */
  const handleTypeChange = (): void => {
    replaceSearchParams({ ...appliedFilters, type: materialType.value })
    getData()
  }

  /**
   * 同步素材
   */
  const syncMaterials = async (): Promise<void> => {
    ElMessageBox.confirm('确定要从微信服务器同步素材数据吗？此操作可能需要一些时间。', '同步确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        try {
          await syncWeChatMaterials({ type: materialType.value, app_type: appliedFilters.app_type })
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
   * 显示上传对话框
   */
  const showUploadDialog = (): void => {
    uploadForm.type = materialType.value === 'news' ? 'image' : materialType.value
    uploadForm.title = ''
    uploadForm.introduction = ''
    uploadRef.value?.clearFiles()
    uploadDialogVisible.value = true
  }

  /**
   * 上传前校验
   */
  const beforeUpload: UploadProps['beforeUpload'] = (rawFile: UploadRawFile) => {
    const type = uploadForm.type
    const bytes = rawFile.size

    if (type === 'image' && bytes > 2 * 1024 * 1024) {
      ElMessage.error('图片大小不能超过 2MB!')
      return false
    }
    if (type === 'voice' && bytes > 2 * 1024 * 1024) {
      ElMessage.error('语音大小不能超过 2MB!')
      return false
    }
    if (type === 'video' && bytes > 10 * 1024 * 1024) {
      ElMessage.error('视频大小不能超过 10MB!')
      return false
    }
    if (type === 'thumb' && bytes > 64 * 1024) {
      ElMessage.error('缩略图大小不能超过 64KB!')
      return false
    }
    return true
  }

  /**
   * 提交上传
   */
  const submitUpload = (): void => {
    // ElUpload 没有公开的 getFiles 方法，借助实例上的 uploadFiles 私有属性判断；
    // 若文件队列为空直接提示用户。
    const files = (uploadRef.value as any)?.uploadFiles ?? []
    if (!files.length) {
      ElMessage.warning('请选择要上传的文件')
      return
    }
    uploading.value = true
    uploadRef.value?.submit()
  }

  /**
   * 上传成功
   */
  const handleUploadSuccess: UploadProps['onSuccess'] = (response) => {
    uploading.value = false
    if (response?.code === 200) {
      ElMessage.success('上传成功')
      uploadDialogVisible.value = false
      refreshData()
    } else {
      ElMessage.error(`上传失败：${response?.msg || response?.message || '未知错误'}`)
    }
  }

  /**
   * 上传失败
   */
  const handleUploadError: UploadProps['onError'] = (error) => {
    uploading.value = false
    ElMessage.error('上传失败，请稍后重试')
    console.error('上传错误:', error)
  }

  /**
   * 预览素材
   */
  const viewMaterial = (row: WeChatMaterialListItem): void => {
    currentMaterial.value = row
    previewDialogVisible.value = true
  }

  /**
   * 处理表格行选择变化
   */
  const handleSelectionChange = (selection: WeChatMaterialListItem[]): void => {
    selectedRows.value = selection
  }

  /**
   * 获取类型文本
   */
  const getTypeText = (type?: string): string => {
    const typeMap: Record<string, string> = {
      image: '图片',
      voice: '语音',
      video: '视频',
      thumb: '缩略图',
      news: '图文'
    }
    return typeMap[type || ''] || type || '-'
  }

  /**
   * 获取类型标签颜色
   */
  const getTypeTagType = (type?: string): any => {
    const colorMap: Record<string, string> = {
      image: 'success',
      voice: 'warning',
      video: 'primary',
      thumb: 'info',
      news: 'danger'
    }
    return colorMap[type || ''] || 'info'
  }

  /**
   * 格式化文件大小
   */
  const formatSize = (bytes?: number): string => {
    if (!bytes) return '-'
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB'
    return (bytes / 1024 / 1024).toFixed(2) + ' MB'
  }

  /**
   * 解析媒体文件URL
   */
  const resolveMediaUrl = (url?: string): string => {
    if (!url) return ''
    if (/^https?:\/\//.test(url)) return url
    return (import.meta.env.VITE_API_URL || '') + url
  }
</script>

<style scoped lang="scss">
  .wechat-material-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .preview-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    background-color: var(--el-bg-color-page);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;

    .preview-image {
      max-width: 100%;
      max-height: 400px;
      object-fit: contain;
      border-radius: 8px;
    }

    .preview-video {
      width: 100%;
      max-height: 400px;
      border-radius: 8px;
    }

    .preview-audio {
      width: 100%;
      max-width: 400px;
    }
  }
</style>
