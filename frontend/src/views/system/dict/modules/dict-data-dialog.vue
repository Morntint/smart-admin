<!-- 字典数据对话框 -->
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
  import { fetchCreateDictData, fetchUpdateDictData } from '@/api/system-manage'
  import { ElMessage } from 'element-plus'

  defineOptions({ name: 'DictDataDialog' })

  type DictDataListItem = Api.SystemManage.DictDataListItem

  interface Props {
    visible: boolean
    type?: 'add' | 'edit'
    editData?: DictDataListItem | null
    dictId?: number
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'success'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    visible: false,
    type: 'add',
    editData: null,
    dictId: 0
  })

  const emit = defineEmits<Emits>()

  const formRef = ref()

  const form = reactive<Partial<DictDataListItem>>({
    dict_id: 0,
    label: '',
    value: '',
    sort: 1,
    status: 1,
    color: '',
    remark: ''
  })

  // 表单验证规则
  const rules = reactive<FormRules>({
    label: [
      { required: true, message: '请输入数据标签', trigger: 'blur' },
      { min: 1, max: 50, message: '长度在 1 到 50 个字符', trigger: 'blur' }
    ],
    value: [
      { required: true, message: '请输入数据键值', trigger: 'blur' },
      { min: 1, max: 100, message: '长度在 1 到 100 个字符', trigger: 'blur' }
    ]
  })

  // 预设颜色
  const colorOptions = [
    { label: '蓝色', value: '#409EFF' },
    { label: '成功绿', value: '#67C23A' },
    { label: '警告黄', value: '#E6A23C' },
    { label: '危险红', value: '#F56C6C' },
    { label: '信息灰', value: '#909399' },
    { label: '紫色', value: '#9B59B6' },
    { label: '青色', value: '#1ABC9C' },
    { label: '橙色', value: '#E67E22' }
  ]

  // 表单项配置
  const formItems = computed<FormItem[]>(() => [
    {
      label: '数据标签',
      key: 'label',
      type: 'input',
      props: {
        placeholder: '请输入数据标签'
      }
    },
    {
      label: '数据键值',
      key: 'value',
      type: 'input',
      props: {
        placeholder: '请输入数据键值'
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
      label: '标签颜色',
      key: 'color',
      type: 'select',
      props: {
        clearable: true,
        placeholder: '请选择标签颜色',
        options: colorOptions
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
    return props.type === 'edit' ? '编辑字典数据' : '新增字典数据'
  })

  /**
   * 重置表单数据
   */
  const resetForm = (): void => {
    formRef.value?.reset()
    Object.assign(form, {
      dict_id: props.dictId || 0,
      label: '',
      value: '',
      sort: 1,
      status: 1,
      color: '',
      remark: ''
    })
  }

  /**
   * 加载表单数据
   */
  const loadFormData = (): void => {
    if (!props.editData) return

    Object.assign(form, {
      dict_id: props.editData.dict_id || props.dictId || 0,
      label: props.editData.label || '',
      value: props.editData.value || '',
      sort: props.editData.sort || 1,
      status: props.editData.status ?? 1,
      color: props.editData.color || '',
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
      const submitData = { ...form, dict_id: props.dictId || form.dict_id }
      if (props.type === 'edit' && props.editData?.id) {
        await fetchUpdateDictData(props.editData.id, submitData)
      } else {
        await fetchCreateDictData(submitData)
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
        form.dict_id = props.dictId || 0
        nextTick(() => {
          if (props.editData) {
            loadFormData()
          }
        })
      }
    }
  )
</script>
