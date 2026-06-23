<!-- 系统配置对话框 -->
<template>
  <ElDialog
    :title="dialogTitle"
    :model-value="visible"
    @update:model-value="handleCancel"
    width="600px"
    align-center
    @closed="handleClosed"
  >
    <ArtForm
      ref="formRef"
      v-model="form"
      :items="formItems"
      :rules="rules"
      :span="12"
      :gutter="20"
      label-width="100px"
      :show-reset="false"
      :show-submit="false"
    />

    <template #footer>
      <span class="dialog-footer">
        <ElButton @click="handleCancel">取 消</ElButton>
        <ElButton type="primary" @click="handleSubmit">确 定</ElButton>
      </span>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import type { FormRules } from 'element-plus'
  import type { FormItem } from '@/components/core/forms/art-form/index.vue'
  import ArtForm from '@/components/core/forms/art-form/index.vue'
  import { fetchCreateConfig, fetchUpdateConfig } from '@/api/system-manage'
  import { ElMessage } from 'element-plus'

  defineOptions({ name: 'ConfigDialog' })

  type ConfigListItem = Api.SystemManage.ConfigListItem

  interface Props {
    visible: boolean
    type?: 'add' | 'edit'
    editData?: ConfigListItem | null
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'success'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    visible: false,
    type: 'add',
    editData: null
  })

  const emit = defineEmits<Emits>()

  const formRef = ref()

  const form = reactive<Partial<ConfigListItem>>({
    name: '',
    key: '',
    value: '',
    group: '',
    type: 'string',
    options: '',
    sort: 1,
    status: 1,
    is_public: 0,
    remark: ''
  })

  // 表单验证规则
  const rules = reactive<FormRules>({
    name: [
      { required: true, message: '请输入配置名称', trigger: 'blur' },
      { min: 2, max: 50, message: '长度在 2 到 50 个字符', trigger: 'blur' }
    ],
    key: [
      { required: true, message: '请输入配置键名', trigger: 'blur' },
      { min: 2, max: 100, message: '长度在 2 到 100 个字符', trigger: 'blur' }
    ],
    value: [{ required: true, message: '请输入配置值', trigger: 'blur' }]
  })

  // 表单项配置
  const formItems = computed<FormItem[]>(() => [
    {
      label: '配置名称',
      key: 'name',
      type: 'input',
      props: {
        placeholder: '请输入配置名称'
      }
    },
    {
      label: '配置键名',
      key: 'key',
      type: 'input',
      props: {
        placeholder: '请输入配置键名'
      }
    },
    {
      label: '配置值',
      key: 'value',
      type: 'input',
      props: {
        placeholder: '请输入配置值'
      }
    },
    {
      label: '配置分组',
      key: 'group',
      type: 'input',
      props: {
        placeholder: '请输入配置分组'
      }
    },
    {
      label: '值类型',
      key: 'type',
      type: 'select',
      props: {
        options: [
          { label: '字符串', value: 'string' },
          { label: '数字', value: 'number' },
          { label: '布尔值', value: 'boolean' },
          { label: 'JSON', value: 'json' }
        ]
      }
    },
    {
      label: '排序',
      key: 'sort',
      type: 'number',
      props: {
        min: 1,
        controlsPosition: 'right',
        style: { width: '100%' }
      }
    },
    {
      label: '可选值',
      key: 'options',
      type: 'input',
      span: 24,
      props: {
        placeholder: 'JSON格式，如：["选项1","选项2"]'
      }
    },
    {
      label: '备注',
      key: 'remark',
      type: 'textarea',
      span: 24,
      props: {
        rows: 3,
        placeholder: '请输入备注'
      }
    }
  ])

  const dialogTitle = computed(() => {
    return props.type === 'edit' ? '编辑系统配置' : '新增系统配置'
  })

  /**
   * 重置表单数据
   */
  const resetForm = (): void => {
    formRef.value?.reset()
    Object.assign(form, {
      name: '',
      key: '',
      value: '',
      group: '',
      type: 'string',
      options: '',
      sort: 1,
      status: 1,
      is_public: 0,
      remark: ''
    })
  }

  /**
   * 加载表单数据
   */
  const loadFormData = (): void => {
    if (!props.editData) return

    Object.assign(form, {
      name: props.editData.name || '',
      key: props.editData.key || '',
      value: props.editData.value || '',
      group: props.editData.group || '',
      type: props.editData.type ?? 'string',
      options: props.editData.options || '',
      sort: props.editData.sort || 1,
      status: props.editData.status ?? 1,
      is_public: props.editData.is_public ?? 0,
      remark: props.editData.remark || ''
    })
  }

  /**
   * 提交表单
   */
  const handleSubmit = async (): Promise<void> => {
    if (!formRef.value) return

    try {
      await formRef.value.validate()
      if (props.type === 'edit' && props.editData?.id) {
        await fetchUpdateConfig(props.editData.id, form)
      } else {
        await fetchCreateConfig(form)
      }
      ElMessage.success('保存成功')
      emit('success')
      handleCancel()
    } catch {
      ElMessage.error('表单校验失败，请检查输入')
    }
  }

  /**
   * 取消操作
   */
  const handleCancel = (): void => {
    emit('update:visible', false)
  }

  /**
   * 对话框关闭后的回调
   */
  const handleClosed = (): void => {
    resetForm()
  }

  /**
   * 监听对话框显示状态
   */
  watch(
    () => props.visible,
    (newVal) => {
      if (newVal) {
        nextTick(() => {
          if (props.editData) {
            loadFormData()
          }
        })
      }
    }
  )
</script>
