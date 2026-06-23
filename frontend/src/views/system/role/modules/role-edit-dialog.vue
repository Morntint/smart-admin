<template>
  <ElDialog
    v-model="visible"
    :title="dialogType === 'add' ? '新增角色' : '编辑角色'"
    width="480px"
    align-center
    class="el-dialog-border"
    @close="handleClose"
  >
    <ElForm ref="formRef" :model="form" :rules="rules" label-width="90px">
      <ElFormItem label="角色名称" prop="name">
        <ElInput v-model="form.name" placeholder="请输入角色名称" />
      </ElFormItem>
      <ElFormItem label="角色编码" prop="code">
        <ElInput
          v-model="form.code"
          placeholder="只能包含小写字母和下划线"
          :disabled="isSuperAdmin"
        />
      </ElFormItem>
      <ElFormItem label="排序" prop="sort">
        <ElInputNumber v-model="form.sort" :min="0" :max="9999" controls-position="right" />
      </ElFormItem>
      <ElFormItem label="状态" prop="status">
        <ElSwitch v-model="form.status" :active-value="1" :inactive-value="0" />
      </ElFormItem>
      <ElFormItem label="备注" prop="remark">
        <ElInput v-model="form.remark" type="textarea" :rows="3" placeholder="请输入备注" />
      </ElFormItem>
    </ElForm>
    <template #footer>
      <ElButton @click="handleClose">取消</ElButton>
      <ElButton type="primary" :loading="saving" @click="handleSubmit">提交</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { type FormInstance, type FormRules } from 'element-plus'
  import { fetchCreateRole, fetchUpdateRole } from '@/api/system-manage'

  type RoleListItem = Api.SystemManage.RoleListItem

  interface Props {
    modelValue: boolean
    dialogType: 'add' | 'edit'
    roleData?: RoleListItem
  }

  interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    dialogType: 'add',
    roleData: undefined
  })

  const emit = defineEmits<Emits>()

  const formRef = ref<FormInstance>()
  const saving = ref(false)

  const form = reactive<{
    id: number
    name: string
    code: string
    sort: number
    status: number
    remark: string
  }>({
    id: 0,
    name: '',
    code: '',
    sort: 0,
    status: 1,
    remark: ''
  })

  // 超级管理员角色编码不可修改
  const isSuperAdmin = computed(() => props.dialogType === 'edit' && form.code === 'super_admin')

  /**
   * 弹窗显示状态双向绑定
   */
  const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
  })

  /**
   * 表单验证规则（与后端 RoleValidator 对齐）
   */
  const rules = reactive<FormRules>({
    name: [
      { required: true, message: '请输入角色名称', trigger: 'blur' },
      { max: 50, message: '角色名称不能超过50个字符', trigger: 'blur' }
    ],
    code: [
      { required: true, message: '请输入角色编码', trigger: 'blur' },
      {
        pattern: /^[a-z_]+$/,
        message: '角色编码只能包含小写字母和下划线',
        trigger: 'blur'
      },
      { max: 50, message: '角色编码不能超过50个字符', trigger: 'blur' }
    ]
  })

  /**
   * 初始化表单数据
   */
  const initForm = () => {
    if (props.dialogType === 'edit' && props.roleData) {
      Object.assign(form, {
        id: props.roleData.id,
        name: props.roleData.name,
        code: props.roleData.code,
        sort: props.roleData.sort ?? 0,
        status: props.roleData.status ?? 1,
        remark: props.roleData.remark ?? ''
      })
    } else {
      Object.assign(form, {
        id: 0,
        name: '',
        code: '',
        sort: 0,
        status: 1,
        remark: ''
      })
    }
  }

  watch(
    () => props.modelValue,
    (newVal) => {
      if (newVal) {
        nextTick(initForm)
      }
    }
  )

  /**
   * 关闭弹窗并重置表单
   */
  const handleClose = () => {
    visible.value = false
    formRef.value?.clearValidate()
  }

  /**
   * 提交表单
   */
  const handleSubmit = async () => {
    if (!formRef.value) return

    try {
      await formRef.value.validate()
    } catch {
      return
    }

    saving.value = true
    try {
      const payload = {
        name: form.name,
        code: form.code,
        sort: form.sort,
        status: form.status,
        remark: form.remark
      }
      if (props.dialogType === 'add') {
        await fetchCreateRole(payload)
      } else {
        await fetchUpdateRole(form.id, payload)
      }
      emit('success')
      handleClose()
    } catch (error) {
      console.error('保存角色失败:', error)
    } finally {
      saving.value = false
    }
  }
</script>
