<!-- 文件管理页面 -->
<template>
  <div class="file-page art-full-height">
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
            <ElButton type="primary" @click="showUploadDialog" v-ripple>上传文件</ElButton>
            <ElButton
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

    <!-- 上传对话框 -->
    <ElDialog v-model="uploadDialogVisible" title="上传文件" width="600px" align-center>
      <ElUpload
        ref="uploadRef"
        :action="uploadUrl"
        :headers="uploadHeaders"
        :on-success="handleUploadSuccess"
        :on-error="handleUploadError"
        :on-progress="handleUploadProgress"
        :before-upload="beforeUpload"
        :on-remove="handleRemove"
        :file-list="fileList"
        list-type="text"
        :limit="10"
        multiple
        drag
      >
        <ElIcon class="el-icon--upload">
          <upload-filled />
        </ElIcon>
        <div class="el-upload__text"> 将文件拖到此处，或 <em>点击上传</em></div>
        <template #tip>
          <div class="el-upload__tip"> 支持上传任意格式文件，单个文件大小不超过 10MB</div>
        </template>
      </ElUpload>

      <template #footer>
        <span class="dialog-footer">
          <ElButton @click="uploadDialogVisible = false">关闭</ElButton>
        </span>
      </template>
    </ElDialog>

    <!-- 预览对话框 -->
    <ElDialog v-model="previewDialogVisible" title="文件预览" width="80%" align-center fullscreen>
      <div class="preview-container">
        <!-- 图片预览 -->
        <img
          v-if="isImage(currentPreviewFile)"
          :src="resolveFileUrl(currentPreviewFile?.url)"
          class="preview-image"
          alt="预览图片"
        />
        <!-- 视频预览 -->
        <video
          v-else-if="isVideo(currentPreviewFile)"
          :src="resolveFileUrl(currentPreviewFile?.url)"
          class="preview-video"
          controls
        />
        <!-- 其他文件显示下载按钮 -->
        <div v-else class="preview-other">
          <ElEmpty description="该文件类型暂不支持预览，请下载后查看">
            <template #image>
              <ElIcon :size="80" color="#909399">
                <document />
              </ElIcon>
            </template>
            <ElButton type="primary" @click="downloadFile(currentPreviewFile)"> 下载文件</ElButton>
          </ElEmpty>
        </div>
      </div>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, computed, nextTick, h } from 'vue'
  import ArtButtonTable from '@/components/core/forms/art-button-table/index.vue'
  import { useTable } from '@/hooks/core/useTable'
  import { fetchGetFileList, fetchDeleteFile, fetchBatchDeleteFile } from '@/api/system-manage'
  import { useUserStore } from '@/store/modules/user'
  import { ElTag, ElMessageBox, ElMessage, ElUpload, ElIcon, ElEmpty, ElButton } from 'element-plus'
  import {
    UploadFilled,
    Document,
    Picture,
    VideoPlay,
    Download,
    View
  } from '@element-plus/icons-vue'
  import type { UploadInstance, UploadProps, UploadRawFile } from 'element-plus'

  defineOptions({ name: 'File' })

  type FileListItem = Api.SystemManage.FileListItem

  const userStore = useUserStore()

  // 上传相关
  const uploadRef = ref<UploadInstance>()
  const uploadDialogVisible = ref(false)
  const fileList = ref<UploadProps['fileList']>([])
  const uploadProgress = ref(0)

  // 预览相关
  const previewDialogVisible = ref(false)
  const currentPreviewFile = ref<FileListItem | null>(null)

  // 选中行
  const selectedRows = ref<FileListItem[]>([])

  // 搜索表单
  const searchForm = ref({
    keyword: '',
    storage: ''
  })

  const formFilters = reactive({ ...searchForm.value })
  const appliedFilters = reactive({ ...searchForm.value })

  const formItems = computed(() => [
    {
      label: '文件名',
      key: 'keyword',
      type: 'input',
      props: {
        clearable: true,
        placeholder: '请输入文件名'
      }
    },
    {
      label: '存储方式',
      key: 'storage',
      type: 'select',
      props: {
        clearable: true,
        options: [
          { label: '本地存储', value: 'local' },
          { label: '阿里云OSS', value: 'oss' },
          { label: '七牛云', value: 'qiniu' },
          { label: '腾讯云COS', value: 'cos' }
        ]
      }
    }
  ])

  // 上传地址（通用上传接口，生产环境 VITE_API_URL 为空时走相对路径 + 代理）
  const uploadUrl = computed(() => {
    return (import.meta.env.VITE_API_URL || '') + '/admin/file'
  })

  // 上传请求头（ElUpload 为原生上传，不走 axios 拦截器，需手动加 Bearer token）
  const uploadHeaders = computed(() => {
    return {
      Authorization: userStore.accessToken ? `Bearer ${userStore.accessToken}` : ''
    }
  })

  // 文件类型图标映射
  const FILE_TYPE_ICONS: Record<string, string> = {
    image: '图片',
    video: '视频',
    document: '文档',
    audio: '音频',
    archive: '压缩包',
    other: '其他'
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
      apiFn: fetchGetFileList,
      apiParams: {
        page: 1,
        limit: 20,
        ...appliedFilters
      },
      columnsFactory: () => [
        { type: 'selection' },
        {
          prop: 'name',
          label: '文件名',
          minWidth: 200,
          formatter: (row: FileListItem) => {
            return h('div', { class: 'flex items-center gap-2' }, [
              h(ElIcon, { size: 18 }, () => getFileIcon(row.mime_type)),
              h('span', row.name)
            ])
          }
        },
        {
          prop: 'original_name',
          label: '原始文件名',
          minWidth: 200,
          showOverflowTooltip: true
        },
        {
          prop: 'size',
          label: '文件大小',
          width: 120,
          formatter: (row: FileListItem) => formatFileSize(row.size)
        },
        {
          prop: 'mime_type',
          label: '文件类型',
          width: 120,
          formatter: (row: FileListItem) => {
            const type = getFileType(row.mime_type)
            return h(ElTag, { type: getFileTagType(type) as any }, () => FILE_TYPE_ICONS[type] || '其他')
          }
        },
        {
          prop: 'extension',
          label: '扩展名',
          width: 100
        },
        {
          prop: 'storage',
          label: '存储方式',
          width: 120,
          formatter: (row: FileListItem) => {
            const storageMap: Record<string, string> = {
              local: '本地存储',
              oss: '阿里云OSS',
              qiniu: '七牛云',
              cos: '腾讯云COS'
            }
            return storageMap[row.storage] || row.storage
          }
        },
        {
          prop: 'create_time',
          label: '上传时间',
          width: 180,
          sortable: true
        },
        {
          prop: 'operation',
          label: '操作',
          width: 180,
          fixed: 'right',
          formatter: (row: FileListItem) =>
            h('div', [
              h(ArtButtonTable, {
                type: 'view',
                onClick: () => previewFile(row),
                title: '预览'
              }),
              h(ArtButtonTable, {
                type: 'download',
                onClick: () => downloadFile(row),
                title: '下载'
              }),
              h(ArtButtonTable, {
                type: 'delete',
                onClick: () => deleteFile(row),
                title: '删除'
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
   * 显示上传对话框
   */
  const showUploadDialog = (): void => {
    fileList.value = []
    uploadProgress.value = 0
    uploadDialogVisible.value = true
  }

  /**
   * 上传前校验
   */
  const beforeUpload: UploadProps['beforeUpload'] = (rawFile: UploadRawFile) => {
    const isLt10M = rawFile.size / 1024 / 1024 < 10
    if (!isLt10M) {
      ElMessage.error('文件大小不能超过 10MB!')
      return false
    }
    return true
  }

  /**
   * 上传成功
   */
  const handleUploadSuccess: UploadProps['onSuccess'] = (response, uploadFile, uploadFiles) => {
    if (response.code === 200 || response.success) {
      ElMessage.success(`${uploadFile.name} 上传成功`)
      refreshData()
    } else {
      ElMessage.error(`${uploadFile.name} 上传失败：${response.message || '未知错误'}`)
    }
  }

  /**
   * 上传失败
   */
  const handleUploadError: UploadProps['onError'] = (error, uploadFile) => {
    ElMessage.error(`${uploadFile.name} 上传失败`)
    console.error('上传错误:', error)
  }

  /**
   * 上传进度
   */
  const handleUploadProgress: UploadProps['onProgress'] = (evt, uploadFile) => {
    uploadProgress.value = evt.percent || 0
  }

  /**
   * 移除文件
   */
  const handleRemove: UploadProps['onRemove'] = () => {
    // 文件被移除时的处理
  }

  /**
   * 获取文件类型图标
   */
  const getFileType = (mimeType: string): string => {
    if (mimeType.startsWith('image/')) return 'image'
    if (mimeType.startsWith('video/')) return 'video'
    if (mimeType.startsWith('audio/')) return 'audio'
    if (
      mimeType.includes('pdf') ||
      mimeType.includes('word') ||
      mimeType.includes('excel') ||
      mimeType.includes('document') ||
      mimeType.includes('sheet') ||
      mimeType.includes('presentation')
    ) {
      return 'document'
    }
    if (
      mimeType.includes('zip') ||
      mimeType.includes('rar') ||
      mimeType.includes('7z') ||
      mimeType.includes('tar') ||
      mimeType.includes('gzip')
    ) {
      return 'archive'
    }
    return 'other'
  }

  /**
   * 获取文件类型对应的图标组件
   */
  const getFileIcon = (mimeType: string) => {
    const type = getFileType(mimeType)
    switch (type) {
      case 'image':
        return h(Picture)
      case 'video':
        return h(VideoPlay)
      case 'document':
        return h(Document)
      default:
        return h(Document)
    }
  }

  /**
   * 获取文件标签类型
   */
  const getFileTagType = (type: string): string => {
    const tagMap: Record<string, string> = {
      image: 'success',
      video: 'primary',
      audio: 'warning',
      document: 'info',
      archive: 'danger',
      other: 'info'
    }
    return tagMap[type] || 'info'
  }

  /**
   * 格式化文件大小
   */
  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]
  }

  /**
   * 解析文件静态访问地址
   * 后端返回的 url 为相对路径（/uploads/...）。开发环境前端与后端不同源，
   * 需前置 VITE_API_URL 直连后端静态资源；生产环境 VITE_API_URL 为空时保持相对路径（同源）。
   */
  const resolveFileUrl = (url?: string): string => {
    if (!url) return ''
    if (/^https?:\/\//.test(url)) return url
    return (import.meta.env.VITE_API_URL || '') + url
  }

  /**
   * 是否为图片
   */
  const isImage = (file: FileListItem | null): boolean => {
    return file?.mime_type?.startsWith('image/') || false
  }

  /**
   * 是否为视频
   */
  const isVideo = (file: FileListItem | null): boolean => {
    return file?.mime_type?.startsWith('video/') || false
  }

  /**
   * 预览文件
   */
  const previewFile = (file: FileListItem): void => {
    currentPreviewFile.value = file
    previewDialogVisible.value = true
  }

  /**
   * 下载文件（走后端接口以增加下载次数）
   * 后端 AuthMiddleware 支持 ?token= 鉴权，原生请求无法加 Authorization 头故拼 token
   */
  const downloadFile = (file: FileListItem | null): void => {
    if (!file) return
    const base = import.meta.env.VITE_API_URL || ''
    const url = `${base}/admin/file/${file.id}/download?token=${encodeURIComponent(userStore.accessToken)}`
    window.open(url, '_blank')
  }

  /**
   * 删除文件
   */
  const deleteFile = (row: FileListItem): void => {
    ElMessageBox.confirm(`确定要删除文件"${row.name}"吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteFile(row.id)
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
  const batchDelete = (): void => {
    if (selectedRows.value.length === 0) {
      ElMessage.warning('请选择要删除的文件')
      return
    }

    ElMessageBox.confirm(`确定要删除选中的 ${selectedRows.value.length} 个文件吗？`, '删除确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
      .then(async () => {
        const ids = selectedRows.value.map((item) => item.id)
        await fetchBatchDeleteFile(ids)
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
  const handleSelectionChange = (selection: FileListItem[]): void => {
    selectedRows.value = selection
  }
</script>

<style scoped lang="scss">
  .preview-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 400px;
    background-color: var(--el-bg-color-page);
    border-radius: 6px;
    padding: 20px;

    .preview-image {
      max-width: 100%;
      max-height: 600px;
      object-fit: contain;
      border-radius: 6px;
    }

    .preview-video {
      width: 100%;
      max-height: 600px;
      border-radius: 6px;
    }

    .preview-other {
      text-align: center;
    }
  }
</style>
