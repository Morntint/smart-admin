<!-- 知识库文档管理弹窗 -->
<template>
  <ElDialog
    v-model="visible"
    title="知识库文档管理"
    width="900px"
    align-center
  >
    <!-- 文档上传区域 -->
    <div class="doc-upload-card">
      <div class="upload-header">
        <div class="header-left">
          <div class="icon-wrapper">
            <el-icon><Document /></el-icon>
          </div>
          <div class="header-text">
            <div class="title">添加文档</div>
            <div class="subtitle">支持文本粘贴和文件上传，自动进行智能分块</div>
          </div>
        </div>
        <ElTag type="info" effect="light" size="small">AI 智能处理</ElTag>
      </div>

      <div class="upload-content">
        <!-- 文本输入区域 -->
        <div class="text-input-section">
          <ElInput
            v-model="docTitle"
            placeholder="文档标题（可选，不填将自动提取）"
            clearable
            class="title-input"
            size="large"
          />
          <ElInput
            v-model="docContent"
            type="textarea"
            :rows="5"
            placeholder="在此粘贴或输入文档内容，支持 Markdown 格式..."
            class="content-textarea"
            resize="none"
          />
        </div>

        <!-- 文件拖拽上传区域 -->
        <div
          class="file-upload-area"
          :class="{ 'is-dragover': isDragover }"
          @dragover.prevent="isDragover = true"
          @dragleave="isDragover = false"
          @drop.prevent="handleDrop"
        >
          <ElUpload
            ref="uploadRef"
            :auto-upload="false"
            :show-file-list="false"
            :on-change="handleFileSelect"
            accept=".txt,.md,.docx,.pdf"
            drag
          >
            <div class="upload-inner">
              <div class="upload-icon-wrapper">
                <el-icon class="upload-main-icon"><Upload /></el-icon>
              </div>
              <div class="upload-text">
                <span class="primary-text">拖拽文件到此处，或</span>
                <span class="link-text">点击选择文件</span>
              </div>
              <div class="file-types">
                支持 .txt, .md, .docx, .pdf 格式，单文件不超过 10MB
              </div>
            </div>
          </ElUpload>
        </div>

        <!-- 已选择文件显示 -->
        <div v-if="selectedFile" class="selected-file">
          <div class="file-info">
            <el-icon class="file-icon"><Document /></el-icon>
            <div class="file-detail">
              <div class="file-name">{{ selectedFile.name }}</div>
              <div class="file-size">{{ formatFileSize(selectedFile.size ?? 0) }}</div>
            </div>
          </div>
          <ElButton type="danger" link size="small" @click="clearFile">
            <el-icon><Close /></el-icon>
          </ElButton>
        </div>

        <!-- 操作栏 -->
        <div class="upload-actions">
          <div class="action-hint">
            <el-icon><MagicStick /></el-icon>
            <span>上传后将自动进行智能分块和向量化处理</span>
          </div>
          <div class="action-buttons">
            <ElButton @click="clearAll" :disabled="uploading">清空</ElButton>
            <ElButton
              type="primary"
              :loading="uploading"
              @click="handleUpload"
              v-ripple
              class="submit-btn"
            >
              <template #icon><Plus /></template>
              添加文档
            </ElButton>
          </div>
        </div>
      </div>
    </div>

    <div class="doc-list-header">
      <el-icon class="list-icon"><List /></el-icon>
      <span class="list-title">文档列表</span>
      <el-tag type="info" size="small">{{ docTotal }} 个文档</el-tag>
    </div>

    <ElTable v-loading="loading" :data="docList" border stripe size="small">
      <ElTableColumn type="index" label="#" width="60" />
      <ElTableColumn prop="title" label="标题" min-width="180" show-overflow-tooltip />
      <ElTableColumn prop="file_type" label="类型" width="80" align="center" />
      <ElTableColumn prop="char_count" label="字符数" width="100" align="center" />
      <ElTableColumn prop="chunk_count" label="分块数" width="80" align="center" />
      <ElTableColumn label="状态" width="100" align="center">
        <template #default="{ row }">
          <ElTag v-if="row.status === 0" type="info" size="small">待处理</ElTag>
          <ElTag v-else-if="row.status === 1" type="warning" size="small">处理中</ElTag>
          <ElTag v-else-if="row.status === 2" type="success" size="small">已完成</ElTag>
          <ElTooltip v-else :content="row.error_msg || '处理失败'">
            <ElTag type="danger" size="small">失败</ElTag>
          </ElTooltip>
        </template>
      </ElTableColumn>
      <ElTableColumn label="操作" width="120" align="center">
        <template #default="{ row }">
          <ElButton
            v-if="row.status === 3"
            type="warning"
            link
            size="small"
            @click="handleReprocess(row)"
          >
            重试
          </ElButton>
          <ElButton type="danger" link size="small" @click="handleDeleteDoc(row)">删除</ElButton>
        </template>
      </ElTableColumn>
    </ElTable>

    <div class="mt-3 flex justify-end">
      <ElPagination
        v-model:current-page="docPage"
        v-model:page-size="docLimit"
        :total="docTotal"
        layout="total, prev, pager, next"
        small
        @current-change="loadDocuments"
      />
    </div>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ref, watch, computed } from 'vue'
  import { ElMessage, ElMessageBox, type UploadInstance, type UploadFile } from 'element-plus'
  import { Document, Upload, InfoFilled, List, MagicStick, Plus, Close } from '@element-plus/icons-vue'
  import {
    fetchGetDocuments,
    fetchUploadDocument,
    fetchDeleteDocument,
    fetchReprocessDocument
  } from '@/api/ai-manage'

  defineOptions({ name: 'AiDocumentDialog' })

  const props = defineProps<{ visible: boolean; kbId: number }>()
  const emit = defineEmits<{ 'update:visible': [v: boolean] }>()

  const visible = computed({
    get: () => props.visible,
    set: (v) => emit('update:visible', v)
  })

  const loading = ref(false)
  const docList = ref<any[]>([])
  const docTotal = ref(0)
  const docPage = ref(1)
  const docLimit = ref(10)
  const docTitle = ref('')
  const docContent = ref('')
  const uploading = ref(false)
  const isDragover = ref(false)
  const selectedFile = ref<UploadFile | null>(null)
  const uploadRef = ref<UploadInstance>()

  // 格式化文件大小
  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1024 / 1024).toFixed(2) + ' MB'
  }

  // 处理文件拖拽
  const handleDrop = (e: DragEvent) => {
    isDragover.value = false
    const files = e.dataTransfer?.files
    if (files && files.length > 0) {
      handleFileSelect({ raw: files[0] } as UploadFile)
    }
  }

  // 处理文件选择
  const handleFileSelect = (file: UploadFile) => {
    const validTypes = ['.txt', '.md', '.docx', '.pdf']
    const fileName = file.name.toLowerCase()
    const isValid = validTypes.some((type) => fileName.endsWith(type))

    if (!isValid) {
      ElMessage.error('仅支持 .txt, .md, .docx, .pdf 格式文件')
      return
    }

    if (file.size && file.size > 10 * 1024 * 1024) {
      ElMessage.error('文件大小不能超过 10MB')
      return
    }

    selectedFile.value = file
    // 如果没有填写标题，使用文件名作为标题
    if (!docTitle.value.trim() && file.name) {
      docTitle.value = file.name.replace(/\.[^/.]+$/, '')
    }
  }

  // 清除已选文件
  const clearFile = () => {
    selectedFile.value = null
    uploadRef.value?.clearFiles()
  }

  // 清空所有输入
  const clearAll = () => {
    docTitle.value = ''
    docContent.value = ''
    clearFile()
  }

  watch(
    () => [props.visible, props.kbId] as const,
    ([v, id]) => {
      if (v && id > 0) {
        docPage.value = 1
        loadDocuments()
      }
    }
  )

  const loadDocuments = async () => {
    if (!props.kbId) return
    loading.value = true
    try {
      const res: any = await fetchGetDocuments(props.kbId, {
        page: docPage.value,
        limit: docLimit.value
      })
      docList.value = res.list || []
      docTotal.value = res.total || 0
    } finally {
      loading.value = false
    }
  }

  const handleUpload = async () => {
    // 检查是否有内容或文件
    const hasContent = docContent.value.trim()
    const hasFile = selectedFile.value

    if (!hasContent && !hasFile) {
      ElMessage.warning('请输入文档内容或选择文件')
      return
    }

    if (!docTitle.value.trim()) {
      ElMessage.warning('请填写文档标题')
      return
    }

    uploading.value = true
    try {
      if (hasFile && selectedFile.value?.raw) {
        // 文件上传模式
        const formData = new FormData()
        formData.append('title', docTitle.value)
        formData.append('file', selectedFile.value.raw as Blob)
        await fetchUploadDocument(props.kbId, formData)
      } else {
        // 文本上传模式
        await fetchUploadDocument(props.kbId, {
          title: docTitle.value,
          content: docContent.value,
          file_type: 'txt'
        })
      }
      ElMessage.success('上传成功')
      clearAll()
      loadDocuments()
    } finally {
      uploading.value = false
    }
  }

  const handleDeleteDoc = (row: any) => {
    ElMessageBox.confirm('确认删除该文档？', '删除确认', {
      type: 'warning'
    })
      .then(async () => {
        await fetchDeleteDocument(row.id)
        ElMessage.success('已删除')
        loadDocuments()
      })
      .catch(() => ElMessage.info('已取消删除'))
  }

  const handleReprocess = async (row: any) => {
    try {
      await fetchReprocessDocument(row.id)
      ElMessage.success('重新处理完成')
      loadDocuments()
    } catch {
      // handled
    }
  }
</script>

<style scoped lang="scss">
  .doc-upload-card {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;

    &:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
      border-color: #cbd5e1;
    }

    .upload-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid #f1f5f9;

      .header-left {
        display: flex;
        align-items: center;
        gap: 14px;

        .icon-wrapper {
          width: 48px;
          height: 48px;
          background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);

          .el-icon {
            font-size: 24px;
            color: #ffffff;
          }
        }

        .header-text {
          .title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.4;
          }

          .subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 2px;
          }
        }
      }
    }

    .upload-content {
      display: flex;
      flex-direction: column;
      gap: 16px;

      .text-input-section {
        display: flex;
        flex-direction: column;
        gap: 12px;

        .title-input :deep(.el-input__wrapper) {
          border-radius: 10px;
          box-shadow: 0 0 0 1px #e2e8f0 inset;
          transition: all 0.2s ease;

          &:hover {
            box-shadow: 0 0 0 1px #cbd5e1 inset;
          }

          &.is-focus {
            box-shadow: 0 0 0 2px #3b82f6 inset;
          }
        }

        .content-textarea :deep(.el-textarea__inner) {
          border-radius: 10px;
          line-height: 1.7;
          font-size: 14px;
          border-color: #e2e8f0;
          transition: all 0.2s ease;

          &:hover {
            border-color: #cbd5e1;
          }

          &:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
          }
        }
      }

      .file-upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        background: #f8fafc;
        transition: all 0.3s ease;

        &.is-dragover {
          border-color: #3b82f6;
          background: #eff6ff;

          .upload-main-icon {
            transform: scale(1.1);
          }
        }

        :deep(.el-upload-dragger) {
          background: transparent;
          border: none;
          padding: 20px;
          width: 100%;
        }

        .upload-inner {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 8px;
        }

        .upload-icon-wrapper {
          width: 56px;
          height: 56px;
          background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          margin-bottom: 4px;

          .upload-main-icon {
            font-size: 28px;
            color: #2563eb;
            transition: transform 0.3s ease;
          }
        }

        .upload-text {
          display: flex;
          gap: 4px;
          font-size: 14px;

          .primary-text {
            color: #64748b;
          }

          .link-text {
            color: #3b82f6;
            font-weight: 500;
            cursor: pointer;

            &:hover {
              text-decoration: underline;
            }
          }
        }

        .file-types {
          font-size: 12px;
          color: #94a3b8;
        }
      }

      .selected-file {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;

        .file-info {
          display: flex;
          align-items: center;
          gap: 12px;

          .file-icon {
            font-size: 24px;
            color: #16a34a;
          }

          .file-detail {
            .file-name {
              font-size: 14px;
              font-weight: 500;
              color: #15803d;
            }

            .file-size {
              font-size: 12px;
              color: #22c55e;
              margin-top: 2px;
            }
          }
        }
      }

      .upload-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 4px;

        .action-hint {
          display: flex;
          align-items: center;
          gap: 6px;
          font-size: 13px;
          color: #64748b;

          .el-icon {
            color: #8b5cf6;
            font-size: 16px;
          }
        }

        .action-buttons {
          display: flex;
          gap: 10px;

          .submit-btn {
            border-radius: 8px;
            height: 40px;
            padding: 0 24px;
            font-weight: 500;
          }
        }
      }
    }
  }

  .doc-list-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    padding: 0 4px;

    .list-icon {
      font-size: 18px;
      color: #475569;
    }

    .list-title {
      font-size: 15px;
      font-weight: 600;
      color: #334155;
    }
  }
</style>
