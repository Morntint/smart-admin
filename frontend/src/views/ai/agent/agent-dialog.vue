<!-- AI Agent 编辑弹窗 -->
<template>
  <ElDialog
    v-model="visible"
    :title="isEdit ? '编辑 Agent' : '新增 Agent'"
    width="780px"
    :close-on-click-modal="false"
    align-center
    @closed="handleClosed"
  >
    <ElForm ref="formRef" :model="form" :rules="rules" label-width="110px">
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="Agent 名称" prop="name">
            <ElInput v-model="form.name" placeholder="如：代码助手" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="Agent 标识" prop="code">
            <ElInput
              v-model="form.code"
              placeholder="如：code-helper"
              :disabled="isEdit"
            />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="描述">
        <ElInput v-model="form.description" type="textarea" :rows="2" placeholder="简短描述 Agent 的用途" />
      </ElFormItem>
      <ElRow :gutter="16">
        <ElCol :span="12">
          <ElFormItem label="关联模型" prop="model_id">
            <ElSelect v-model="form.model_id" placeholder="选择 AI 模型" filterable class="w-full">
              <ElOption
                v-for="m in modelOptions"
                :key="m.id"
                :label="`${m.name} (${m.model_name})`"
                :value="m.id"
              />
            </ElSelect>
          </ElFormItem>
        </ElCol>
        <ElCol :span="12">
          <ElFormItem label="图标">
            <ElInput v-model="form.icon" placeholder="如：cpu" />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="系统提示词">
        <ElInput
          v-model="form.system_prompt"
          type="textarea"
          :rows="5"
          placeholder="定义 Agent 的角色和行为..."
        />
      </ElFormItem>
      <ElFormItem label="欢迎语">
        <ElInput v-model="form.welcome_message" placeholder="用户打开对话时的欢迎语" />
      </ElFormItem>
      <ElFormItem label="推荐问题">
        <ElTag
          v-for="(q, i) in suggestedList"
          :key="i"
          closable
          class="mr-2 mb-2"
          @close="removeSuggested(i)"
        >
          {{ q }}
        </ElTag>
        <ElInput
          v-if="showSuggestedInput"
          ref="suggestedInputRef"
          v-model="suggestedNew"
          size="small"
          class="!w-40 inline-block"
          @blur="addSuggested"
          @keyup.enter="addSuggested"
        />
        <ElButton v-else size="small" @click="showSuggestedInput = true">+ 添加</ElButton>
      </ElFormItem>
      <ElRow :gutter="16">
        <ElCol :span="8">
          <ElFormItem label="历史轮数">
            <ElInputNumber v-model="form.max_history_rounds" :min="0" :max="50" class="w-full" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="8">
          <ElFormItem label="温度">
            <ElInputNumber v-model="form.temperature" :min="0" :max="2" :step="0.1" :precision="2" class="w-full" />
          </ElFormItem>
        </ElCol>
        <ElCol :span="8">
          <ElFormItem label="排序">
            <ElInputNumber v-model="form.sort" :min="0" class="w-full" />
          </ElFormItem>
        </ElCol>
      </ElRow>
      <ElFormItem label="公开 Agent">
        <ElSwitch v-model="form.is_public" :active-value="1" :inactive-value="0" />
        <span class="form-tip">公开后所有用户可在对话工作台使用</span>
      </ElFormItem>
      <ElFormItem label="状态">
        <ElRadioGroup v-model="form.status">
          <ElRadio :value="1">正常</ElRadio>
          <ElRadio :value="0">禁用</ElRadio>
        </ElRadioGroup>
      </ElFormItem>

      <ElDivider content-position="left">工具绑定（可选）</ElDivider>
      <ElFormItem label="绑定工具">
        <ElSelect
          v-model="form.tools"
          multiple
          collapse-tags
          collapse-tags-tooltip
          placeholder="从工具库选择要绑定给本 Agent 的工具"
          class="w-full"
          :loading="toolsLoading"
          @visible-change="onToolsDropdownToggle"
        >
          <ElOption
            v-for="t in toolOptions"
            :key="t.id"
            :label="`${t.name}（${toolTypeLabel(t.tool_type)}）`"
            :value="t.id"
          >
            <div class="flex items-center justify-between w-full">
              <span class="flex items-center gap-1.5">
                <ElIcon><Tools /></ElIcon>
                {{ t.name }}
              </span>
              <ElTag size="small" :type="toolTypeTag(t.tool_type) as any">
                {{ toolTypeLabel(t.tool_type) }}
              </ElTag>
            </div>
          </ElOption>
        </ElSelect>
        <span class="form-tip">
          需先在「工具库」页面创建工具。每个工具的调用方式在「工具库」中维护。
        </span>
      </ElFormItem>
      <ElFormItem v-if="form.tools?.length" label="绑定预览">
        <div class="tool-preview art-card-xs w-full">
          <div
            v-for="tid in form.tools"
            :key="tid"
            class="tool-preview-item"
          >
            <ElIcon class="tool-icon"><Tools /></ElIcon>
            <span class="tool-name">{{ toolNameOf(tid) }}</span>
            <ElTag size="small" :type="toolTypeTag(toolTypeOf(tid)) as any">
              {{ toolTypeLabel(toolTypeOf(tid)) }}
            </ElTag>
          </div>
        </div>
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
  import { Tools } from '@element-plus/icons-vue'
  import {
    fetchGetAiAgent,
    fetchCreateAiAgent,
    fetchUpdateAiAgent,
    fetchGetEnabledAiModels,
    fetchGetAiAgentToolList
  } from '@/api/ai-manage'

  defineOptions({ name: 'AiAgentDialog' })

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
  const modelOptions = ref<any[]>([])
  const suggestedList = ref<string[]>([])
  const suggestedNew = ref('')
  const showSuggestedInput = ref(false)
  const suggestedInputRef = ref()

  // 工具库（全局资源，仅引用 ID，不在 Agent 这里维护具体参数）
  const toolOptions = ref<Array<{ id: number; name: string; tool_type: string }>>([])
  const toolsLoading = ref(false)
  const toolsLoaded = ref(false)

  const defaultForm = {
    name: '',
    code: '',
    icon: 'robot',
    description: '',
    model_id: null as number | null,
    system_prompt: '',
    welcome_message: '',
    max_history_rounds: 10,
    temperature: null as number | null,
    max_tokens: null as number | null,
    is_public: 0,
    is_streaming: 1,
    status: 1,
    sort: 0,
    tools: [] as any[]
  }

  const form = reactive({ ...defaultForm })

  const rules = {
    name: [{ required: true, message: '请输入 Agent 名称', trigger: 'blur' }],
    code: [{ required: true, message: '请输入 Agent 标识', trigger: 'blur' }],
    model_id: [{ required: true, message: '请选择模型', trigger: 'change' }]
  }

  const loadModels = async () => {
    try {
      const res: any = await fetchGetEnabledAiModels()
      modelOptions.value = res || []
    } catch {
      // handled
    }
  }

  const loadTools = async () => {
    if (toolsLoaded.value || toolsLoading.value) return
    toolsLoading.value = true
    try {
      const res: any = await fetchGetAiAgentToolList({ page: 1, limit: 100, status: '1' })
      toolOptions.value = res?.list || []
      toolsLoaded.value = true
    } catch {
      // handled
    } finally {
      toolsLoading.value = false
    }
  }

  const onToolsDropdownToggle = (open: boolean) => {
    if (open) loadTools()
  }

  const TOOL_TYPE_MAP: Record<string, { label: string; type: string }> = {
    function: { label: '函数', type: 'primary' },
    api: { label: 'API', type: 'success' },
    plugin: { label: '插件', type: 'warning' }
  }
  const toolTypeLabel = (t: string) => TOOL_TYPE_MAP[t]?.label || t || '-'
  const toolTypeTag = (t: string) => TOOL_TYPE_MAP[t]?.type || 'info'
  const toolNameOf = (id: number) =>
    toolOptions.value.find((t) => t.id === id)?.name || `#${id}`
  const toolTypeOf = (id: number) =>
    toolOptions.value.find((t) => t.id === id)?.tool_type || ''

  watch(
    () => [props.visible, props.editData, props.type] as const,
    async ([v, data, t]) => {
      if (!v) return
      await loadModels()
      if (t === 'edit' && data && data.id) {
        try {
          const res: any = await fetchGetAiAgent(data.id)
          // tools 后端关联返回结构 [{ id, name, code, ... }]，这里只保留 id 数组
          const toolIds = Array.isArray(res.tools)
            ? res.tools.map((x: any) => (typeof x === 'object' ? x.id : x))
            : []
          Object.assign(form, res, { tools: toolIds })
          suggestedList.value = res.suggested_questions || []
        } catch {
          // handled
        }
      } else {
        Object.assign(form, defaultForm)
        suggestedList.value = []
      }
      // 编辑时预热工具列表（让预览能显示名称）
      if (t === 'edit') loadTools()
    },
    { immediate: true }
  )

  const handleClosed = () => {
    formRef.value?.resetFields()
  }

  const addSuggested = () => {
    if (suggestedNew.value.trim()) {
      suggestedList.value.push(suggestedNew.value.trim())
      suggestedNew.value = ''
    }
    showSuggestedInput.value = false
  }

  const removeSuggested = (i: number) => {
    suggestedList.value.splice(i, 1)
  }

  const submitForm = async () => {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
      // 后端保存时只需要工具 ID 列表（service 会先 delete 再 insert）
      const data = {
        ...form,
        tools: form.tools.map((id: number) => ({ id })),
        suggested_questions: suggestedList.value
      }
      if (isEdit.value && props.editData?.id) {
        await fetchUpdateAiAgent(props.editData.id, data)
      } else {
        await fetchCreateAiAgent(data)
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
  .tool-preview {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    max-height: 180px;
    overflow-y: auto;
  }
  .tool-preview-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    background: var(--default-box-color);
    border-radius: 6px;
    .tool-icon {
      color: var(--el-color-primary);
    }
    .tool-name {
      flex: 1;
      font-weight: 500;
    }
  }
</style>
