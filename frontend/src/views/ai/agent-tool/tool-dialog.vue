<!-- AI 工具库 编辑弹窗 -->
<template>
  <ElDialog
    v-model="visible"
    :title="isEdit ? '编辑工具' : '新增工具'"
    width="780px"
    :close-on-click-modal="false"
    align-center
    @closed="handleClosed"
  >
    <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="工具名称" prop="name">
            <ElInput v-model="form.name" placeholder="如：天气查询" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="工具标识" prop="code">
            <ElInput
              v-model="form.code"
              placeholder="如：get_weather"
              :disabled="isEdit"
            />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="工具描述">
        <ElInput
          v-model="form.description"
          type="textarea"
          :rows="2"
          placeholder="简短描述工具的用途"
        />
      </ElFormItem>
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="工具类型" prop="tool_type">
            <ElSelect v-model="form.tool_type" placeholder="选择类型" class="w-full">
              <ElOption label="函数 (function)" value="function" />
              <ElOption label="API (api)" value="api" />
              <ElOption label="插件 (plugin)" value="plugin" />
            </ElSelect>
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="处理器" prop="handler">
            <ElInput
              v-model="form.handler"
              placeholder="类名@方法 / HTTP URL"
            />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="参数 schema">
        <ElInput
          v-model="form.parameters_schema"
          type="textarea"
          :rows="5"
          placeholder='JSON 格式，例如：{ "type": "object", "properties": { "city": { "type": "string" } } }'
        />
        <span class="form-tip">JSON Schema 格式，描述工具接受的参数</span>
      </ElFormItem>
      <ElFormItem label="运行时配置">
        <ElInput
          v-model="form.config"
          type="textarea"
          :rows="3"
          placeholder='JSON 格式，例如：{ "timeout": 3000, "retries": 2 }'
        />
        <span class="form-tip">运行时配置项（超时、重试等）</span>
      </ElFormItem>
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="排序">
            <ElInputNumber v-model="form.sort" :min="0" class="w-full" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="状态">
            <ElRadioGroup v-model="form.status">
              <ElRadio :value="1">正常</ElRadio>
              <ElRadio :value="0">禁用</ElRadio>
            </ElRadioGroup>
          </ElFormItem>
        </ElCol>
      </ElRow>
    </ElForm>
    <template #footer>
      <ElButton @click="visible = false">取消</ElButton>
      <ElButton type="primary" :loading="submitting" @click="submitForm">确 定</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import {
    fetchGetAiAgentTool,
    fetchCreateAiAgentTool,
    fetchUpdateAiAgentTool
  } from '@/api/ai-manage'

  defineOptions({ name: 'AiAgentToolDialog' })

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
    code: '',
    description: '',
    tool_type: 'function',
    handler: '',
    parameters_schema: '',
    config: '',
    sort: 0,
    status: 1
  }

  const form = reactive({ ...defaultForm })

  const rules = {
    name: [{ required: true, message: '请输入工具名称', trigger: 'blur' }],
    code: [{ required: true, message: '请输入工具标识', trigger: 'blur' }],
    tool_type: [{ required: true, message: '请选择工具类型', trigger: 'change' }]
  }

  watch(
    () => [props.visible, props.editData, props.type] as const,
    async ([v, data, t]) => {
      if (!v) return
      if (t === 'edit' && data && data.id) {
        try {
          const res: any = await fetchGetAiAgentTool(data.id)
          Object.assign(form, {
            ...defaultForm,
            ...res,
            parameters_schema: stringifyJson(res.parameters_schema),
            config: stringifyJson(res.config)
          })
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

  /**
   * 对象/数组 → 多行 JSON 字符串；空值返回 ''
   */
  const stringifyJson = (v: any): string => {
    if (v === null || v === undefined || v === '') return ''
    if (typeof v === 'string') {
      // 已经是字符串：尝试解析后重新格式化，便于编辑
      try {
        return JSON.stringify(JSON.parse(v), null, 2)
      } catch {
        return v
      }
    }
    return JSON.stringify(v, null, 2)
  }

  /**
   * 校验并返回可序列化 JSON，失败抛错
   */
  const parseJson = (raw: string, fieldLabel: string): any => {
    if (raw === '' || raw === null || raw === undefined) return null
    try {
      return JSON.parse(raw)
    } catch {
      throw new Error(`${fieldLabel} 不是合法的 JSON`)
    }
  }

  const submitForm = async () => {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return

    // JSON 字段校验
    let parameters_schema: any = null
    let config: any = null
    try {
      parameters_schema = parseJson(form.parameters_schema, '参数 schema')
      config = parseJson(form.config, '运行时配置')
    } catch (e: any) {
      ElMessage.error(e.message)
      return
    }

    submitting.value = true
    try {
      const data = {
        name: form.name,
        code: form.code,
        description: form.description,
        tool_type: form.tool_type,
        handler: form.handler,
        parameters_schema,
        config,
        sort: form.sort,
        status: form.status
      }
      if (isEdit.value && props.editData?.id) {
        await fetchUpdateAiAgentTool(props.editData.id, data)
      } else {
        await fetchCreateAiAgentTool(data)
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
    margin-left: 8px;
  }
</style>
