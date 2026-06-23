<template>
  <ElDialog
    v-model="dialogVisible"
    :title="dialogType === 'add' ? '添加用户' : '编辑用户'"
    width="560px"
    align-center
    @close="handleClose"
  >
    <ElForm ref="formRef" :model="formData" :rules="rules" label-width="90px">
      <ElFormItem label="用户名" prop="username">
        <ElInput
          v-model="formData.username"
          placeholder="请输入用户名"
          :disabled="dialogType === 'edit'"
        />
      </ElFormItem>

      <ElFormItem v-if="dialogType === 'add'" label="密码" prop="password">
        <ElInput
          v-model="formData.password"
          type="password"
          show-password
          placeholder="请输入密码"
        />
      </ElFormItem>

      <ElFormItem label="昵称" prop="nickname">
        <ElInput v-model="formData.nickname" placeholder="请输入昵称" />
      </ElFormItem>

      <ElFormItem label="手机号" prop="mobile">
        <ElInput v-model="formData.mobile" placeholder="请输入手机号" />
      </ElFormItem>

      <ElFormItem label="邮箱" prop="email">
        <ElInput v-model="formData.email" placeholder="请输入邮箱" />
      </ElFormItem>

      <ElFormItem label="性别" prop="sex">
        <ElRadioGroup v-model="formData.sex">
          <ElRadio :value="1">男</ElRadio>
          <ElRadio :value="2">女</ElRadio>
          <ElRadio :value="0">未知</ElRadio>
        </ElRadioGroup>
      </ElFormItem>

      <ElFormItem label="部门" prop="dept_id">
        <ElTreeSelect
          v-model="formData.dept_id"
          :data="deptOptions"
          :props="{ label: 'name', value: 'id', children: 'children' }"
          node-key="id"
          check-strictly
          clearable
          placeholder="请选择部门"
          style="width: 100%"
        />
      </ElFormItem>

      <ElFormItem label="角色" prop="role_ids">
        <ElSelect
          v-model="formData.role_ids"
          multiple
          clearable
          placeholder="请选择角色"
          style="width: 100%"
        >
          <ElOption
            v-for="role in roleList"
            :key="role.id"
            :value="role.id"
            :label="role.name"
          />
        </ElSelect>
      </ElFormItem>

      <ElFormItem label="状态" prop="status">
        <ElSwitch
          v-model="formData.status"
          :active-value="1"
          :inactive-value="0"
          active-text="正常"
          inactive-text="禁用"
          inline-prompt
        />
      </ElFormItem>

      <ElFormItem label="备注" prop="remark">
        <ElInput
          v-model="formData.remark"
          type="textarea"
          :rows="3"
          placeholder="请输入备注"
          maxlength="500"
          show-word-limit
        />
      </ElFormItem>
    </ElForm>

    <template #footer>
      <div class="dialog-footer">
        <ElButton @click="dialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="submitting" @click="handleSubmit">提交</ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { fetchGetAllRoles, fetchGetDeptOptions } from '@/api/system-manage'
  import type { FormInstance, FormRules } from 'element-plus'

  type UserListItem = Api.SystemManage.UserListItem
  type UserSubmitParams = Api.SystemManage.UserSubmitParams

  interface Props {
    visible: boolean
    type: string
    userData?: Partial<UserListItem>
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'submit', data: UserSubmitParams): void
  }

  const props = defineProps<Props>()
  const emit = defineEmits<Emits>()

  // 角色列表（下拉数据）
  const roleList = ref<Array<{ id: number; name: string; code: string }>>([])

  // 部门树选项
  const deptOptions = ref<any[]>([])

  // 提交状态
  const submitting = ref(false)

  // 对话框显示控制
  const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
  })

  const dialogType = computed(() => props.type)

  // 表单实例
  const formRef = ref<FormInstance>()

  // 表单数据（与后端 UserValidator 字段一致）
  const formData = reactive<UserSubmitParams>({
    username: '',
    password: '',
    nickname: '',
    mobile: '',
    email: '',
    sex: 0,
    status: 1,
    dept_id: undefined,
    role_ids: [],
    remark: ''
  })

  // 表单验证规则
  const rules = computed<FormRules>(() => ({
    username: [
      { required: true, message: '请输入用户名', trigger: 'blur' },
      {
        pattern: /^[a-zA-Z0-9_]{3,32}$/,
        message: '用户名只能由 3-32 位字母、数字、下划线组成',
        trigger: 'blur'
      }
    ],
    password:
      dialogType.value === 'add'
        ? [
            { required: true, message: '请输入密码', trigger: 'blur' },
            { min: 6, max: 50, message: '密码长度在 6 到 50 个字符', trigger: 'blur' }
          ]
        : [],
    nickname: [{ max: 30, message: '昵称不能超过 30 个字符', trigger: 'blur' }],
    mobile: [{ pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号格式', trigger: 'blur' }],
    email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }]
  }))

  /**
   * 加载角色和部门下拉数据
   */
  const loadOptions = async () => {
    try {
      const [roles, depts] = await Promise.all([fetchGetAllRoles(), fetchGetDeptOptions()])
      roleList.value = roles || []
      deptOptions.value = buildDeptTree(depts || [])
    } catch (error) {
      console.error('加载下拉数据失败:', error)
    }
  }

  /**
   * 将平铺部门列表转换为树形结构
   */
  const buildDeptTree = (list: Array<{ id: number; name: string; parent_id: number }>) => {
    const map = new Map<number, any>()
    const roots: any[] = []
    list.forEach((item) => map.set(item.id, { ...item, children: [] }))
    map.forEach((node) => {
      const parent = map.get(node.parent_id)
      if (parent) {
        parent.children.push(node)
      } else {
        roots.push(node)
      }
    })
    // 清理空 children，避免出现展开箭头
    const clean = (nodes: any[]) => {
      nodes.forEach((n) => {
        if (n.children.length) clean(n.children)
        else delete n.children
      })
    }
    clean(roots)
    return roots
  }

  /**
   * 初始化表单数据
   * 根据对话框类型（新增/编辑）填充表单
   */
  const initFormData = () => {
    const isEdit = props.type === 'edit' && props.userData
    const row = props.userData

    Object.assign(formData, {
      username: isEdit && row ? row.username || '' : '',
      password: '',
      nickname: isEdit && row ? row.nickname || '' : '',
      mobile: isEdit && row ? row.mobile || '' : '',
      email: isEdit && row ? row.email || '' : '',
      sex: isEdit && row ? (row.sex ?? 0) : 0,
      status: isEdit && row ? (row.status ?? 1) : 1,
      dept_id: isEdit && row ? row.dept_id : undefined,
      role_ids: isEdit && row && Array.isArray(row.role_ids) ? [...row.role_ids] : [],
      remark: isEdit && row ? row.remark || '' : ''
    })
  }

  /**
   * 监听对话框状态变化
   * 当对话框打开时初始化表单数据并清除验证状态
   */
  watch(
    () => [props.visible, props.type, props.userData],
    ([visible]) => {
      if (visible) {
        initFormData()
        nextTick(() => {
          formRef.value?.clearValidate()
        })
      }
    },
    { immediate: true }
  )

  // 组件挂载时加载下拉数据
  onMounted(() => {
    loadOptions()
  })

  /**
   * 关闭弹窗
   */
  const handleClose = () => {
    dialogVisible.value = false
  }

  /**
   * 提交表单
   * 验证通过后触发提交事件，把表单数据传给父组件调用接口
   */
  const handleSubmit = async () => {
    if (!formRef.value) return

    await formRef.value.validate(async (valid) => {
      if (!valid) return

      submitting.value = true
      try {
        // 组装提交数据：编辑时不传 password
        const payload: UserSubmitParams = {
          nickname: formData.nickname || undefined,
          email: formData.email || undefined,
          mobile: formData.mobile || undefined,
          sex: formData.sex,
          status: formData.status,
          dept_id: formData.dept_id,
          role_ids: formData.role_ids || [],
          remark: formData.remark || undefined
        }
        if (dialogType.value === 'add') {
          payload.username = formData.username
          payload.password = formData.password
        }
        emit('submit', payload)
      } finally {
        submitting.value = false
      }
    })
  }
</script>
