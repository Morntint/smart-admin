<!-- AI 提示词编辑弹窗 -->
<template>
  <ElDialog
    v-model="visible"
    :title="isEdit ? '编辑提示词' : '新增提示词'"
    width="680px"
    :close-on-click-modal="false"
    align-center
    @closed="handleClosed"
  >
    <ElForm ref="formRef" :model="form" :rules="rules" label-width="100px">
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="模板名称" prop="name">
            <ElInput v-model="form.name" placeholder="如：代码审查助手" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="模板标识" prop="code">
            <ElInput
              v-model="form.code"
              placeholder="如：code-review"
              :disabled="isEdit"
            />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="分类">
            <ElSelect v-model="form.category" placeholder="选择分类" class="w-full">
              <ElOption label="通用" value="general" />
              <ElOption label="编程" value="coding" />
              <ElOption label="营销" value="marketing" />
              <ElOption label="分析" value="analysis" />
              <ElOption label="自定义" value="custom" />
            </ElSelect>
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="排序">
            <ElInputNumber v-model="form.sort" :min="0" class="w-full" />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="描述">
        <ElInput v-model="form.description" placeholder="简短描述模板用途" />
      </ElFormItem>
      <ElFormItem label="模板内容" prop="content">
        <ElInput
          v-model="form.content"
          type="textarea"
          :rows="8"
          placeholder="使用 {{variable}} 占位变量，如：请帮我写一篇关于 {{topic}} 的文章"
        />
        <div class="form-tip">使用 <code>&#123;&#123; variable &#125;&#125;</code> 作为变量占位符</div>
      </ElFormItem>
      <ElFormItem label="变量定义">
        <ElInput
          v-model="variablesJson"
          type="textarea"
          :rows="3"
          placeholder='{"topic": "主题", "length": "字数"}'
        />
        <div class="form-tip">JSON 格式定义变量说明（key 对应模板中的变量名）</div>
      </ElFormItem>
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
  import { fetchGetPrompt, fetchCreatePrompt, fetchUpdatePrompt } from '@/api/ai-manage'

  defineOptions({ name: 'AiPromptDialog' })

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
  const variablesJson = ref('')

  const defaultForm = {
    name: '',
    code: '',
    category: 'general',
    description: '',
    content: '',
    status: 1,
    sort: 0
  }

  const form = reactive({ ...defaultForm })

  const rules = {
    name: [{ required: true, message: '请输入模板名称', trigger: 'blur' }],
    code: [{ required: true, message: '请输入模板标识', trigger: 'blur' }],
    content: [{ required: true, message: '请输入模板内容', trigger: 'blur' }]
  }

  watch(
    () => [props.visible, props.editData, props.type] as const,
    async ([v, data, t]) => {
      if (!v) return
      if (t === 'edit' && data && data.id) {
        try {
          const res: any = await fetchGetPrompt(data.id)
          Object.assign(form, res)
          variablesJson.value = res.variables ? JSON.stringify(res.variables, null, 2) : ''
        } catch {
          // handled
        }
      } else {
        Object.assign(form, defaultForm)
        variablesJson.value = ''
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
      let variables: any = null
      if (variablesJson.value.trim()) {
        try {
          variables = JSON.parse(variablesJson.value)
        } catch {
          ElMessage.warning('变量定义为无效 JSON')
          submitting.value = false
          return
        }
      }
      const data: any = { ...form, variables }
      if (isEdit.value && props.editData?.id) {
        await fetchUpdatePrompt(props.editData.id, data)
      } else {
        await fetchCreatePrompt(data)
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
