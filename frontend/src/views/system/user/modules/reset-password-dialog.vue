<template>
  <ElDialog
    v-model="dialogVisible"
    title="重置密码"
    width="460px"
    align-center
    @close="handleClose"
  >
    <ElForm ref="formRef" :model="formData" :rules="rules" label-width="100px">
      <ElFormItem label="用户名">
        <ElInput :model-value="userData?.username" disabled />
      </ElFormItem>

      <ElFormItem label="新密码" prop="password">
        <ElInput
          v-model="formData.password"
          type="password"
          show-password
          placeholder="请输入新密码，留空则使用默认密码 123456"
        />
      </ElFormItem>

      <ElFormItem label="确认密码" prop="confirmPassword">
        <ElInput
          v-model="formData.confirmPassword"
          type="password"
          show-password
          placeholder="请再次输入新密码"
        />
      </ElFormItem>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="submitting" @click="handleSubmit">确定</ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { fetchResetUserPassword } from '@/api/system-manage'
  import type { FormInstance, FormRules } from 'element-plus'
  import { ElMessage } from 'element-plus'

  type UserListItem = Api.SystemManage.UserListItem

  interface Props {
    visible: boolean
    userData?: Partial<UserListItem>
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'success'): void
  }

  const props = defineProps<Props>()
  const emit = defineEmits<Emits>()

  const submitting = ref(false)
  const formRef = ref<FormInstance>()

  const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
  })

  const formData = reactive({
    password: '',
    confirmPassword: ''
  })

  const validateConfirmPassword = (rule: any, value: string, callback: any) => {
    if (value && value !== formData.password) {
      callback(new Error('两次输入的密码不一致'))
    } else {
      callback()
    }
  }

  const rules = computed<FormRules>(() => ({
    password: [
      { min: 6, max: 50, message: '密码长度在 6 到 50 个字符', trigger: 'blur' }
    ],
    confirmPassword: [
      { validator: validateConfirmPassword, trigger: 'blur' }
    ]
  }))

  const initFormData = () => {
    formData.password = ''
    formData.confirmPassword = ''
  }

  watch(
    () => props.visible,
    (visible) => {
      if (visible) {
        initFormData()
        nextTick(() => {
          formRef.value?.clearValidate()
        })
      }
    }
  )

  const handleClose = () => {
    dialogVisible.value = false
  }

  const handleSubmit = async () => {
    if (!formRef.value || !props.userData?.id) return

    await formRef.value.validate(async (valid) => {
      if (!valid) return

      submitting.value = true
      try {
        await fetchResetUserPassword(props.userData.id, formData.password)
        ElMessage.success('密码重置成功')
        emit('success')
        dialogVisible.value = false
      } catch (error) {
        console.error('重置密码失败:', error)
      } finally {
        submitting.value = false
      }
    })
  }
</script>
