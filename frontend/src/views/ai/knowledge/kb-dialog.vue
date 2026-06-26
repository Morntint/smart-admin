<!-- 知识库编辑弹窗 -->
<template>
  <ElDialog
    v-model="visible"
    :title="isEdit ? '编辑知识库' : '新增知识库'"
    width="600px"
    :close-on-click-modal="false"
    align-center
    @closed="handleClosed"
  >
    <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
      <ElFormItem label="知识库名称" prop="name">
        <ElInput v-model="form.name" placeholder="如：技术文档库" />
      </ElFormItem>
      <ElFormItem label="描述">
        <ElInput v-model="form.description" type="textarea" :rows="2" />
      </ElFormItem>
      <ElFormItem label="向量化模型">
        <ElInput v-model="form.embedding_model" placeholder="text-embedding-3-small" />
      </ElFormItem>
      <ElFormItem label="向量维度">
        <ElInputNumber v-model="form.embedding_dimension" :min="128" :max="8192" :step="128" />
      </ElFormItem>
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="分块大小">
            <ElInputNumber v-model="form.chunk_size" :min="100" :max="10000" :step="100" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="分块重叠">
            <ElInputNumber v-model="form.chunk_overlap" :min="0" :max="2000" :step="50" />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="返回条数">
            <ElInputNumber v-model="form.top_k" :min="1" :max="20" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="相似度阈值">
            <ElInputNumber
              v-model="form.similarity_threshold"
              :min="0"
              :max="1"
              :step="0.05"
              :precision="3"
            />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="状态">
        <ElRadioGroup v-model="form.status">
          <ElRadio :value="1">正常</ElRadio>
          <ElRadio :value="0">禁用</ElRadio>
        </ElRadioGroup>
      </ElFormItem>
    </ElForm>
    <template #footer>
      <ElButton @click="visible = false">取消</ElButton>
      <ElButton type="primary" :loading="submitting" @click="submitForm">确 定</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import { fetchGetKnowledge, fetchCreateKnowledge, fetchUpdateKnowledge } from '@/api/ai-manage'

  defineOptions({ name: 'AiKnowledgeDialog' })

  const props = defineProps<{
    visible: boolean
    type: 'add' | 'edit'
    editData: Record<string, any> | null
  }>()

  const emit = defineEmits<{
    'update:visible': [v: boolean]
    success: []
  }>()

  const visible = computed({
    get: () => props.visible,
    set: (v) => emit('update:visible', v)
  })

  const isEdit = computed(() => props.type === 'edit')

  const formRef = ref()
  const submitting = ref(false)

  const defaultForm = {
    name: '',
    description: '',
    embedding_model: 'text-embedding-3-small',
    embedding_dimension: 1536,
    chunk_size: 1000,
    chunk_overlap: 200,
    top_k: 5,
    similarity_threshold: 0.7,
    status: 1,
    sort: 0
  }

  const form = reactive({ ...defaultForm })

  const rules = {
    name: [{ required: true, message: '请输入知识库名称', trigger: 'blur' }]
  }

  // 打开/切换时初始化表单
  watch(
    () => [props.visible, props.editData, props.type] as const,
    async ([v, data, t]) => {
      if (!v) return
      if (t === 'edit' && data && data.id) {
        try {
          const res: any = await fetchGetKnowledge(data.id)
          Object.assign(form, res)
        } catch {
          // handled
        }
      } else {
        Object.assign(form, defaultForm)
      }
    },
    { immediate: true }
  )

  const handleClosed = () => {
    formRef.value?.resetFields()
  }

  const submitForm = async () => {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
      if (isEdit.value && props.editData?.id) {
        await fetchUpdateKnowledge(props.editData.id, { ...form })
      } else {
        await fetchCreateKnowledge({ ...form })
      }
      ElMessage.success(isEdit.value ? '更新成功' : '创建成功')
      visible.value = false
      emit('success')
    } finally {
      submitting.value = false
    }
  }
</script>
