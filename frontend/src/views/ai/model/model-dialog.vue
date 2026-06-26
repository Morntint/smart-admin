<!-- AI 模型编辑弹窗 -->
<template>
  <ElDialog
    v-model="visible"
    :title="isEdit ? '编辑模型' : '新增模型'"
    width="780px"
    :close-on-click-modal="false"
    align-center
    @closed="handleClosed"
  >
    <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
      <ElFormItem label="模型名称" prop="name">
        <ElInput v-model="form.name" placeholder="如：DeepSeek V3" />
      </ElFormItem>
      <ElFormItem label="供应商" prop="provider">
        <ElSelect v-model="form.provider" placeholder="选择供应商" class="w-full">
          <ElOption label="OpenAI" value="openai" />
          <ElOption label="DeepSeek" value="deepseek" />
          <ElOption label="通义千问" value="qwen" />
          <ElOption label="智谱 AI" value="zhipu" />
          <ElOption label="Moonshot" value="moonshot" />
          <ElOption label="自定义" value="custom" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="模型标识" prop="model_name">
        <ElInput v-model="form.model_name" placeholder="如：deepseek-chat" />
        <div class="form-tip">对应 API 调用的 model 参数</div>
      </ElFormItem>
      <ElFormItem label="API 地址" prop="base_url">
        <ElInput v-model="form.base_url" placeholder="留空使用官方默认地址" />
      </ElFormItem>
      <ElFormItem label="API Key" prop="api_key">
        <ElInput v-model="form.api_key" type="password" show-password placeholder="sk-..." />
      </ElFormItem>
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="上下文窗口">
            <ElInputNumber v-model="form.context_window" :min="1024" :max="1000000" :step="1000" class="w-full" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="最大 Token">
            <ElInputNumber v-model="form.max_tokens" :min="256" :max="128000" :step="1024" class="w-full" />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElRow :gutter="16">
        <ElCol :span="8">
          <ElFormItem label="温度">
            <ElInputNumber v-model="form.temperature" :min="0" :max="2" :step="0.1" :precision="2" class="w-full" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="8">
          <ElFormItem label="Top P">
            <ElInputNumber v-model="form.top_p" :min="0" :max="1" :step="0.05" :precision="2" class="w-full" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="8">
          <ElFormItem label="排序">
            <ElInputNumber v-model="form.sort" :min="0" :max="999" class="w-full" />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="功能支持">
        <ElCheckbox v-model="form.supports_vision" :true-value="1" :false-value="0">视觉</ElCheckbox>
        <ElCheckbox v-model="form.supports_function_calling" :true-value="1" :false-value="0">函数调用</ElCheckbox>
        <ElCheckbox v-model="form.supports_streaming" :true-value="1" :false-value="0">流式输出</ElCheckbox>
      </ElFormItem>
      <ElFormItem label="状态">
        <ElRadioGroup v-model="form.status">
          <ElRadio :value="1">正常</ElRadio>
          <ElRadio :value="0">禁用</ElRadio>
        </ElRadioGroup>
      </ElFormItem>
      <ElFormItem label="备注">
        <ElInput v-model="form.remark" type="textarea" :rows="2" />
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
  import { fetchGetAiModel, fetchCreateAiModel, fetchUpdateAiModel } from '@/api/ai-manage'

  defineOptions({ name: 'AiModelDialog' })

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
    provider: '',
    model_name: '',
    base_url: '',
    api_key: '',
    context_window: 128000,
    max_tokens: 4096,
    temperature: 0.7,
    top_p: 1.0,
    sort: 0,
    supports_vision: 0,
    supports_function_calling: 0,
    supports_streaming: 1,
    status: 1,
    remark: ''
  }

  const form = reactive({ ...defaultForm })

  const rules = {
    name: [{ required: true, message: '请输入模型名称', trigger: 'blur' }],
    provider: [{ required: true, message: '请选择供应商', trigger: 'change' }],
    model_name: [{ required: true, message: '请输入模型标识', trigger: 'blur' }]
  }

  watch(
    () => [props.visible, props.editData, props.type] as const,
    async ([v, data, t]) => {
      if (!v) return
      if (t === 'edit' && data && data.id) {
        try {
          const res: any = await fetchGetAiModel(data.id)
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
      const data = { ...form }
      if (isEdit.value && !data.api_key) {
        delete (data as any).api_key
      }
      if (isEdit.value && props.editData?.id) {
        await fetchUpdateAiModel(props.editData.id, data)
      } else {
        await fetchCreateAiModel(data)
      }
      ElMessage.success(isEdit.value ? '更新成功' : '创建成功')
      visible.value = false
      emit('success')
    } finally {
      submitting.value = false
    }
  }
</script>

<style scoped lang="scss">
  .form-tip {
    font-size: 12px;
    color: var(--art-gray-500);
    margin-top: 4px;
  }
</style>
