<!-- 字典类型对话框 -->
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
  import { fetchCreateDict, fetchUpdateDict } from '@/api/system-manage'
  import { ElMessage } from 'element-plus'

  defineOptions({ name: 'DictTypeDialog' })

  type DictListItem = Api.SystemManage.DictListItem

  interface Props {
    visible: boolean
    type?: 'add' | 'edit'
    editData?: DictListItem | null
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

  const form = reactive<Partial<DictListItem>>({
    name: '',
    code: '',
    type: 1,
    status: 1,
    remark: ''
  })

  // 表单验证规则
  const rules = reactive<FormRules>({
    name: [
      { required: true, message: '请输入字典名称', trigger: 'blur' },
      { min: 2, max: 50, message: '长度在 2 到 50 个字符', trigger: 'blur' }
    ],
    code: [
      { required: true, message: '请输入字典编码', trigger: 'blur' },
      { min: 2, max: 50, message: '长度在 2 到 50 个字符', trigger: 'blur' }
    ]
  })

  // 表单项配置
  const formItems = computed<FormItem[]>(() => [
    {
      label: '字典名称',
      key: 'name',
      type: 'input',
      props: {
        placeholder: '请输入字典名称'
      }
    },
    {
      label: '字典编码',
      key: 'code',
      type: 'input',
      props: {
        placeholder: '请输入字典编码'
      }
    },
    {
      label: '字典类型',
      key: 'type',
      type: 'select',
      props: {
        options: [
          { label: '字符串', value: 1 },
          { label: '数字', value: 2 },
          { label: '布尔值', value: 3 }
        ]
      }
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        options: [
          { label: '正常', value: 1 },
          { label: '禁用', value: 0 }
        ]
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
    return props.type === 'edit' ? '编辑字典类型' : '新增字典类型'
  })

  /**
   * 重置表单数据
   */
  const resetForm = (): void => {
    formRef.value?.reset()
    Object.assign(form, {
      name: '',
      code: '',
      type: 1,
      status: 1,
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
      code: props.editData.code || '',
      type: props.editData.type ?? 1,
      status: props.editData.status ?? 1,
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
        await fetchUpdateDict(props.editData.id, form)
      } else {
        await fetchCreateDict(form)
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
