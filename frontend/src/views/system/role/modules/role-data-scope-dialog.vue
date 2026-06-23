<template>
  <ElDialog
    v-model="visible"
    title="数据范围"
    width="480px"
    align-center
    class="el-dialog-border"
    @close="handleClose"
  >
    <ElForm label-width="90px" v-loading="loading">
      <ElFormItem label="数据范围">
        <ElSelect v-model="form.data_scope" style="width: 100%">
          <ElOption :value="1" label="全部数据" />
          <ElOption :value="2" label="本部门数据" />
          <ElOption :value="3" label="本部门及以下数据" />
          <ElOption :value="4" label="仅本人数据" />
          <ElOption :value="5" label="自定义数据" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem v-if="form.data_scope === 5" label="部门范围">
        <ElTreeSelect
          v-model="form.data_scope_depts"
          :data="deptOptions"
          :props="{ label: 'name', value: 'id', children: 'children' }"
          node-key="id"
          multiple
          show-checkbox
          check-strictly
          clearable
          placeholder="请选择部门"
          style="width: 100%"
        />
      </ElFormItem>
    </ElForm>
    <template #footer>
      <ElButton @click="handleClose">取消</ElButton>
      <ElButton type="primary" :loading="saving" @click="handleSubmit">保存</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import {
    fetchGetRoleDataScope,
    fetchSetRoleDataScope,
    fetchGetDeptOptions
  } from '@/api/system-manage'

  type RoleListItem = Api.SystemManage.RoleListItem

  interface Props {
    modelValue: boolean
    roleData?: RoleListItem
  }

  interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    roleData: undefined
  })

  const emit = defineEmits<Emits>()

  const loading = ref(false)
  const saving = ref(false)
  const deptOptions = ref<any[]>([])

  const form = reactive<{ data_scope: number; data_scope_depts: number[] }>({
    data_scope: 1,
    data_scope_depts: []
  })

  /**
   * 弹窗显示状态双向绑定
   */
  const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
  })

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
   * 加载部门树
   */
  const loadDeptOptions = async () => {
    try {
      const depts = await fetchGetDeptOptions()
      deptOptions.value = buildDeptTree(depts || [])
    } catch (error) {
      console.error('加载部门数据失败:', error)
    }
  }

  /**
   * 加载角色当前数据范围
   */
  const loadData = async () => {
    if (!props.roleData?.id) return

    loading.value = true
    try {
      const res = await fetchGetRoleDataScope(props.roleData.id)
      form.data_scope = res.data_scope ?? 1
      form.data_scope_depts = res.data_scope_depts ?? []
    } catch (error) {
      console.error('加载数据范围失败:', error)
      ElMessage.error('加载数据范围失败')
    } finally {
      loading.value = false
    }
  }

  onMounted(loadDeptOptions)

  /**
   * 监听弹窗打开，加载数据
   */
  watch(
    () => props.modelValue,
    (newVal) => {
      if (newVal) {
        loadData()
      } else {
        form.data_scope = 1
        form.data_scope_depts = []
      }
    }
  )

  /**
   * 关闭弹窗
   */
  const handleClose = () => {
    visible.value = false
  }

  /**
   * 提交保存
   */
  const handleSubmit = async () => {
    if (!props.roleData?.id) return

    saving.value = true
    try {
      await fetchSetRoleDataScope(props.roleData.id, {
        data_scope: form.data_scope,
        data_scope_depts: form.data_scope === 5 ? form.data_scope_depts : []
      })
      emit('success')
      handleClose()
    } catch (error) {
      console.error('保存数据范围失败:', error)
    } finally {
      saving.value = false
    }
  }
</script>
