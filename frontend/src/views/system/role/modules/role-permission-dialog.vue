<template>
  <ElDialog
    v-model="visible"
    title="菜单权限"
    width="520px"
    align-center
    class="el-dialog-border"
    @close="handleClose"
  >
    <ElScrollbar height="70vh" v-loading="loading">
      <ElTree
        ref="treeRef"
        :data="processedMenuList"
        show-checkbox
        node-key="id"
        :default-expand-all="isExpandAll"
        :default-checked-keys="checkedKeys"
        :props="defaultProps"
        @check="handleTreeCheck"
      >
        <template #default="{ data }">
          <div class="custom-tree-node">
            <ElTag v-if="data.type === 1" type="info" size="small" effect="plain">目录</ElTag>
            <ElTag v-else-if="data.type === 2" type="primary" size="small" effect="plain">菜单</ElTag>
            <ElTag v-else type="danger" size="small" effect="plain">按钮</ElTag>
            <span class="node-label">{{ data.label }}</span>
          </div>
        </template>
      </ElTree>
    </ElScrollbar>
    <template #footer>
      <ElButton @click="toggleExpandAll">
        {{ isExpandAll ? '全部收起' : '全部展开' }}
      </ElButton>
      <ElButton @click="toggleSelectAll">
        {{ isSelectAll ? '取消全选' : '全部选择' }}
      </ElButton>
      <ElButton type="primary" :loading="saving" @click="savePermission">保存</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import { fetchGetMenuList, fetchGetRoleMenus, fetchAssignRoleMenus } from '@/api/system-manage'

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

  const treeRef = ref()
  const isExpandAll = ref(true)
  const isSelectAll = ref(false)
  const loading = ref(false)
  const saving = ref(false)
  const checkedKeys = ref<number[]>([])

  // 菜单树数据
  const menuTree = ref<any[]>([])

  /**
   * 弹窗显示状态双向绑定
   */
  const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
  })

  /**
   * 菜单节点类型
   */
  interface MenuNode {
    id: number
    parent_id: number
    name: string
    label: string
    type: number
    permission?: string
    children?: MenuNode[]
  }

  /**
   * 转换菜单数据格式
   * 将后端返回的菜单转换为树形展示所需格式
   */
  const processedMenuList = computed<MenuNode[]>(() => {
    return menuTree.value.map((item) => convertNode(item))
  })

  /**
   * 递归转换节点
   */
  const convertNode = (node: any): MenuNode => {
    return {
      id: node.id,
      parent_id: node.parent_id || 0,
      name: node.name,
      label: node.name,
      type: node.type,
      permission: node.permission,
      children: node.children?.map((child: any) => convertNode(child)) || []
    }
  }

  /**
   * 树形组件配置
   */
  const defaultProps = {
    children: 'children',
    label: 'label'
  }

  /**
   * 递归收集所有叶子节点 ID
   */
  const getLeafNodeKeys = (nodes: MenuNode[]): Set<number> => {
    const leafKeys = new Set<number>()
    const traverse = (nodeList: MenuNode[]) => {
      nodeList.forEach((node) => {
        if (node.children && node.children.length > 0) {
          traverse(node.children)
        } else {
          leafKeys.add(node.id)
        }
      })
    }
    traverse(nodes)
    return leafKeys
  }

  /**
   * 加载菜单数据和角色权限
   */
  const loadData = async () => {
    if (!props.roleData?.id) return

    loading.value = true
    try {
      // 并行加载菜单树和角色已有权限
      const [menuList, roleMenuIds] = await Promise.all([
        fetchGetMenuList(),
        fetchGetRoleMenus(props.roleData.id)
      ])

      menuTree.value = menuList
      checkedKeys.value = roleMenuIds || []

      // 在树形组件渲染后设置选中状态
      nextTick(() => {
        // 只传叶子节点 ID，父节点由树组件自动级联计算
        // 若传入父节点 ID 会导致所有子节点被全选
        const leafKeys = getLeafNodeKeys(processedMenuList.value)
        const leafCheckedKeys = checkedKeys.value.filter((id) => leafKeys.has(id))
        treeRef.value?.setCheckedKeys(leafCheckedKeys, false)
      })
    } catch (error) {
      console.error('加载菜单权限失败:', error)
      ElMessage.error('加载菜单权限失败')
    } finally {
      loading.value = false
    }
  }

  /**
   * 监听弹窗打开，加载数据
   */
  watch(
    () => props.modelValue,
    (newVal) => {
      if (newVal) {
        loadData()
      } else {
        // 关闭时清空
        menuTree.value = []
        checkedKeys.value = []
        isSelectAll.value = false
      }
    }
  )

  /**
   * 关闭弹窗并清空选中状态
   */
  const handleClose = () => {
    visible.value = false
    treeRef.value?.setCheckedKeys([])
  }

  /**
   * 保存权限配置
   * 传所有选中的菜单ID（含按钮），由后端处理父子关系
   */
  const savePermission = async () => {
    if (!props.roleData?.id || !treeRef.value) return

    const checkedKeys = treeRef.value.getCheckedKeys() as number[]
    // 同时获取半选中的节点（关联的子项被选中时，父节点会处于半选中状态）
    const halfCheckedKeys = treeRef.value.getHalfCheckedKeys() as number[]

    // 合并所有需要保存的菜单ID
    const allMenuIds = [...new Set([...checkedKeys, ...halfCheckedKeys])]

    saving.value = true
    try {
      await fetchAssignRoleMenus(props.roleData.id, allMenuIds)
      ElMessage.success('权限保存成功')
      emit('success')
      handleClose()
    } catch (error) {
      console.error('保存权限失败:', error)
      ElMessage.error('保存权限失败')
    } finally {
      saving.value = false
    }
  }

  /**
   * 切换全部展开/收起状态
   */
  const toggleExpandAll = () => {
    const tree = treeRef.value
    if (!tree) return

    const nodes = tree.store.nodesMap
    Object.values(nodes).forEach((node: any) => {
      node.expanded = !isExpandAll.value
    })

    isExpandAll.value = !isExpandAll.value
  }

  /**
   * 切换全选/取消全选状态
   */
  const toggleSelectAll = () => {
    const tree = treeRef.value
    if (!tree) return

    if (!isSelectAll.value) {
      // 全选所有节点
      const allKeys = getAllNodeKeys(processedMenuList.value)
      tree.setCheckedKeys(allKeys)
    } else {
      tree.setCheckedKeys([])
    }

    isSelectAll.value = !isSelectAll.value
  }

  /**
   * 递归获取所有节点的 key
   */
  const getAllNodeKeys = (nodes: MenuNode[]): number[] => {
    const keys: number[] = []
    const traverse = (nodeList: MenuNode[]) => {
      nodeList.forEach((node) => {
        keys.push(node.id)
        if (node.children?.length) traverse(node.children)
      })
    }
    traverse(nodes)
    return keys
  }

  /**
   * 处理树节点选中状态变化
   * 同步更新全选按钮状态
   */
  const handleTreeCheck = () => {
    const tree = treeRef.value
    if (!tree) return

    const checked = tree.getCheckedKeys().length
    const allKeys = getAllNodeKeys(processedMenuList.value)
    isSelectAll.value = checked === allKeys.length && allKeys.length > 0
  }
</script>

<style lang="scss" scoped>
  .custom-tree-node {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;

    .node-label {
      color: var(--el-text-color-primary);
    }
  }
</style>
