<template>
  <ArtSearchBar
    ref="searchBarRef"
    v-model="formData"
    :items="formItems"
    :rules="rules"
    @reset="handleReset"
    @search="handleSearch"
  >
  </ArtSearchBar>
</template>

<script setup lang="ts">
  import { fetchGetDeptOptions } from '@/api/system-manage'

  interface Props {
    modelValue: Api.SystemManage.UserSearchParams
  }
  interface Emits {
    (e: 'update:modelValue', value: Api.SystemManage.UserSearchParams): void
    (e: 'search', params: Api.SystemManage.UserSearchParams): void
    (e: 'reset'): void
  }
  const props = defineProps<Props>()
  const emit = defineEmits<Emits>()

  // 表单数据双向绑定
  const searchBarRef = ref()
  const formData = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })

  // 校验规则
  const rules = {}

  // 状态选项（与后端一致：1=正常, 0=禁用）
  const statusOptions = ref([
    { label: '正常', value: '1' },
    { label: '禁用', value: '0' }
  ])

  // 部门选项（树形结构）
  const deptOptions = ref<any[]>([])

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
   * 加载部门选项
   */
  const loadDeptOptions = async () => {
    try {
      const depts = await fetchGetDeptOptions()
      deptOptions.value = buildDeptTree(depts || [])
    } catch (error) {
      console.error('加载部门选项失败:', error)
    }
  }

  // 表单配置（与后端参数字段一致）
  const formItems = computed(() => [
    {
      label: '关键词',
      key: 'keyword',
      type: 'input',
      placeholder: '搜索用户名/昵称/手机号',
      clearable: true
    },
    {
      label: '状态',
      key: 'status',
      type: 'select',
      props: {
        placeholder: '请选择状态',
        options: statusOptions.value
      }
    },
    {
      label: '部门',
      key: 'dept_id',
      type: 'treeselect',
      props: {
        data: deptOptions.value,
        props: { label: 'name', value: 'id', children: 'children' },
        nodeKey: 'id',
        checkStrictly: true,
        clearable: true,
        placeholder: '请选择部门'
      }
    }
  ])

  // 事件
  function handleReset() {
    console.log('重置表单')
    emit('reset')
  }

  async function handleSearch(params: Api.SystemManage.UserSearchParams) {
    await searchBarRef.value.validate()
    emit('search', params)
    console.log('搜索参数', params)
  }

  // 组件挂载时加载部门选项
  onMounted(() => {
    loadDeptOptions()
  })
</script>
