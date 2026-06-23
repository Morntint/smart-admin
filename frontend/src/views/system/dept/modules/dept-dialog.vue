<!-- 部门管理对话框 -->
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
  import { fetchGetDeptOptions } from '@/api/system-manage'
  import { ElMessage } from 'element-plus'

  defineOptions({ name: 'DeptDialog' })

  type DeptListItem = Api.SystemManage.DeptListItem

  interface Props {
    visible: boolean
    type?: 'add' | 'edit'
    editData?: Partial<DeptListItem> | null
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'submit', data: Partial<DeptListItem>): void
  }

  const props = withDefaults(defineProps<Props>(), {
    visible: false,
    type: 'add',
    editData: null
  })

  const emit = defineEmits<Emits>()

  const formRef = ref()

  const form = reactive<Partial<DeptListItem>>({
    parent_id: 0,
    name: '',
    leader: '',
    phone: '',
    email: '',
    sort: 1,
    status: 1
  })

  // 上级部门选项
  const deptOptions = ref<Array<{ id: number; name: string; parent_id: number }>>([])

  // 表单验证规则
  const rules = reactive<FormRules>({
    name: [
      { required: true, message: '请输入部门名称', trigger: 'blur' },
      { min: 2, max: 50, message: '长度在 2 到 50 个字符', trigger: 'blur' }
    ],
    sort: [{ required: true, message: '请输入排序', trigger: 'blur' }]
  })

  // 表单项配置
  const formItems = computed<FormItem[]>(() => [
    {
      label: '上级部门',
      key: 'parent_id',
      type: 'select',
      props: {
        clearable: true,
        placeholder: '请选择上级部门',
        options: deptOptions.value.map((item) => ({
          label: item.name,
          value: item.id
        }))
      }
    },
    {
      label: '部门名称',
      key: 'name',
      type: 'input',
      props: {
        placeholder: '请输入部门名称'
      }
    },
    {
      label: '负责人',
      key: 'leader',
      type: 'input',
      props: {
        placeholder: '请输入负责人'
      }
    },
    {
      label: '联系电话',
      key: 'mobile',
      type: 'input',
      props: {
        placeholder: '请输入联系电话'
      }
    },
    {
      label: '邮箱',
      key: 'email',
      type: 'input',
      props: {
        placeholder: '请输入邮箱'
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
    }
  ])

  const dialogTitle = computed(() => {
    return props.type === 'edit' ? '编辑部门' : '新增部门'
  })

  /**
   * 获取部门选项
   */
  const getDeptOptions = async (): Promise<void> => {
    try {
      const options = await fetchGetDeptOptions()
      deptOptions.value = [{ id: 0, name: '顶级部门', parent_id: 0 }, ...options]
    } catch (error) {
      console.error('获取部门选项失败:', error)
    }
  }

  /**
   * 重置表单数据
   */
  const resetForm = (): void => {
    formRef.value?.reset()
    Object.assign(form, {
      parent_id: 0,
      name: '',
      leader: '',
      phone: '',
      email: '',
      sort: 1,
      status: 1
    })
  }

  /**
   * 加载表单数据
   */
  const loadFormData = (): void => {
    if (!props.editData) return

    Object.assign(form, {
      parent_id: props.editData.parent_id || 0,
      name: props.editData.name || '',
      leader: props.editData.leader || '',
      phone: props.editData.phone || '',
      email: props.editData.email || '',
      sort: props.editData.sort || 1,
      status: props.editData.status ?? 1
    })
  }

  /**
   * 提交表单
   */
  const handleSubmit = async (): Promise<void> => {
    if (!formRef.value) return

    try {
      await formRef.value.validate()
      const submitData = { ...form }
      // 如果 parent_id 为 0 或 undefined，删除该字段表示顶级部门
      if (!submitData.parent_id) {
        delete submitData.parent_id
      }
      emit('submit', submitData)
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
        getDeptOptions()
        nextTick(() => {
          if (props.editData) {
            loadFormData()
          }
        })
      }
    }
  )
</script>
