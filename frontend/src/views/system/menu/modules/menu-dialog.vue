<template>
  <ElDialog
    :title="dialogTitle"
    :model-value="visible"
    @update:model-value="handleCancel"
    width="860px"
    align-center
    class="menu-dialog"
    @closed="handleClosed"
  >
    <ArtForm
      ref="formRef"
      v-model="form"
      :items="formItems"
      :rules="rules"
      :span="width > 640 ? 12 : 24"
      :gutter="20"
      label-width="100px"
      :show-reset="false"
      :show-submit="false"
    >
      <template #menuType>
        <ElRadioGroup v-model="form.menuType" :disabled="disableMenuType">
          <ElRadioButton value="dir">目录</ElRadioButton>
          <ElRadioButton value="menu">菜单</ElRadioButton>
          <ElRadioButton value="button">按钮</ElRadioButton>
        </ElRadioGroup>
      </template>
    </ArtForm>

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
  import { ElIcon, ElTooltip } from 'element-plus'
  import { QuestionFilled } from '@element-plus/icons-vue'
  import { formatMenuTitle } from '@/utils/router'
  import type { AppRouteRecord } from '@/types/router'
  import type { FormItem } from '@/components/core/forms/art-form/index.vue'
  import ArtForm from '@/components/core/forms/art-form/index.vue'
  import { useWindowSize } from '@vueuse/core'

  const { width } = useWindowSize()

  /**
   * 创建带 tooltip 的表单标签
   * @param label 标签文本
   * @param tooltip 提示文本
   * @returns 渲染函数
   */
  const createLabelTooltip = (label: string, tooltip: string) => {
    return () =>
      h('span', { class: 'flex items-center' }, [
        h('span', label),
        h(
          ElTooltip,
          {
            content: tooltip,
            placement: 'top'
          },
          () => h(ElIcon, { class: 'ml-0.5 cursor-help' }, () => h(QuestionFilled))
        )
      ])
  }

  // 前端表单数据格式
  interface MenuFormData {
    id: number
    name: string
    path: string
    label: string
    component: string
    icon: string
    isEnable: boolean
    sort: number
    isMenu: boolean
    keepAlive: boolean
    isHide: boolean
    isHideTab: boolean
    link: string
    isIframe: boolean
    showBadge: boolean
    showTextBadge: string
    fixedTab: boolean
    activePath: string
    roles: string[]
    isFullPage: boolean
    authName: string
    authLabel: string
    authIcon: string
    authSort: number
    parent_id: number
    redirect: string
  }

  // 后端提交数据格式（snake_case）
  interface MenuSubmitData {
    id?: number
    parent_id: number
    name: string
    route_name?: string
    path: string
    component: string
    redirect?: string
    icon: string
    type: number
    permission?: string
    sort: number
    status: number
    is_visible: number
    is_cache: number
    is_hide_tab: number
    is_iframe: number
    is_full_page: number
    fixed_tab: number
    active_path?: string
    is_external: number
    remark?: string
  }

  interface Props {
    visible: boolean
    editData?: AppRouteRecord | any
    type?: 'menu' | 'button'
    lockType?: boolean
  }

  interface Emits {
    (e: 'update:visible', value: boolean): void
    (e: 'submit', data: MenuFormData): void
  }

  const props = withDefaults(defineProps<Props>(), {
    visible: false,
    type: 'menu',
    lockType: false
  })

  const emit = defineEmits<Emits>()

  const formRef = ref()
  const isEdit = ref(false)

  const form = reactive<MenuFormData & { menuType: 'menu' | 'button' }>({
    menuType: 'menu',
    id: 0,
    name: '',
    path: '',
    label: '',
    component: '',
    icon: '',
    isEnable: true,
    sort: 1,
    isMenu: true,
    keepAlive: true,
    isHide: false,
    isHideTab: false,
    link: '',
    isIframe: false,
    showBadge: false,
    showTextBadge: '',
    fixedTab: false,
    activePath: '',
    roles: [],
    isFullPage: false,
    authName: '',
    authLabel: '',
    authIcon: '',
    authSort: 1,
    parent_id: 0,
    redirect: ''
  })

  // 动态验证规则
  const rules = computed<FormRules>(() => {
    const baseRules: FormRules = {
      name: [
        { required: true, message: '请输入菜单名称', trigger: 'blur' },
        { min: 2, max: 20, message: '长度在 2 到 20 个字符', trigger: 'blur' }
      ],
    }

    // 目录和菜单的验证规则
    if (['dir', 'menu'].includes(form.menuType)) {
      baseRules.path = [{ required: true, message: '请输入路由地址', trigger: 'blur' }]
      baseRules.label = [{ required: false, message: '请输入路由名称', trigger: 'blur' }]
      // 菜单类型必填组件路径，目录可选
      if (form.menuType === 'menu') {
        baseRules.component = [{ required: true, message: '请输入组件路径', trigger: 'blur' }]
      }
    }

    // 按钮的验证规则
    if (form.menuType === 'button') {
      baseRules.authName = [{ required: true, message: '请输入权限名称', trigger: 'blur' }]
      baseRules.authLabel = [{ required: true, message: '请输入权限标识', trigger: 'blur' }]
    }

    return baseRules
  })

  /**
   * 表单项配置
   */
  const formItems = computed<FormItem[]>(() => {
    const baseItems: FormItem[] = [{ label: '菜单类型', key: 'menuType', span: 24 }]

    // Switch 组件的 span：小屏幕 12，大屏幕 6
    const switchSpan = width.value < 640 ? 12 : 6

    // dir（目录）和 menu（菜单）都显示完整的表单，只有 button（按钮）显示简化表单
    if (['dir', 'menu'].includes(form.menuType)) {
      return [
        ...baseItems,
        { label: '菜单名称', key: 'name', type: 'input', props: { placeholder: '菜单名称' } },
        {
          label: createLabelTooltip(
            '路由地址',
            '一级菜单：以 / 开头的绝对路径（如 /dashboard）\n二级及以下：相对路径（如 console、user）'
          ),
          key: 'path',
          type: 'input',
          props: { placeholder: '如：/dashboard 或 console' }
        },
        { label: '路由名称', key: 'label', type: 'input', props: { placeholder: '如：User（PascalCase 格式）' } },
        {
          label: createLabelTooltip(
            '组件路径',
            '目录菜单：留空\n具体页面：填写组件路径（如 /system/user）'
          ),
          key: 'component',
          type: 'input',
          props: { placeholder: '目录可留空，菜单必填，如：/system/user' }
        },
        {
          label: '重定向地址',
          key: 'redirect',
          type: 'input',
          props: { placeholder: '如：/system/user，可选' }
        },
        { label: '图标', key: 'icon', type: 'input', props: { placeholder: '如：ri:user-line' } },
        {
          label: '菜单排序',
          key: 'sort',
          type: 'number',
          props: { min: 1, controlsPosition: 'right', style: { width: '100%' } }
        },
        {
          label: '外部链接',
          key: 'link',
          type: 'input',
          props: { placeholder: '如：https://www.example.com' }
        },
        {
          label: '激活路径',
          key: 'activePath',
          type: 'input',
          props: { placeholder: '如：/system/user，可选' }
        },
        { label: '是否启用', key: 'isEnable', type: 'switch', span: switchSpan },
        { label: '页面缓存', key: 'keepAlive', type: 'switch', span: switchSpan },
        { label: '隐藏菜单', key: 'isHide', type: 'switch', span: switchSpan },
        { label: '标签隐藏', key: 'isHideTab', type: 'switch', span: switchSpan },
        { label: '是否内嵌', key: 'isIframe', type: 'switch', span: switchSpan },
        { label: '固定标签', key: 'fixedTab', type: 'switch', span: switchSpan },
        { label: '全屏页面', key: 'isFullPage', type: 'switch', span: switchSpan }
      ]
    } else {
      // 按钮类型只显示简化的权限字段
      return [
        ...baseItems,
        {
          label: '权限名称',
          key: 'authName',
          type: 'input',
          props: { placeholder: '如：新增、编辑、删除' }
        },
        {
          label: '权限标识',
          key: 'authLabel',
          type: 'input',
          props: { placeholder: '如：system:user:add' }
        },
        {
          label: '权限排序',
          key: 'authSort',
          type: 'number',
          props: { min: 1, controlsPosition: 'right', style: { width: '100%' } }
        }
      ]
    }
  })

  const dialogTitle = computed(() => {
    const typeMap: Record<string, string> = {
      dir: '目录',
      menu: '菜单',
      button: '按钮',
    }
    const type = typeMap[form.menuType] || '菜单'
    return isEdit.value ? `编辑${type}` : `新建${type}`
  })

  /**
   * 是否禁用菜单类型切换
   * - 编辑模式：禁用（类型不可修改）
   * - 新增子菜单：如果是按钮子级，禁用；否则可选
   */
  const disableMenuType = computed(() => {
    // 编辑模式下禁用
    if (isEdit.value) return true
    // lockType 为 true 时禁用（如新增子菜单时）
    return props.lockType ?? false
  })

  /**
   * 重置表单数据
   */
  const resetForm = (): void => {
    formRef.value?.reset()
    form.menuType = 'menu'
  }

  /**
   * 加载表单数据（编辑模式）
   * 适配后端 snake_case 字段格式
   * 类型映射：1=目录(dir), 2=菜单(menu), 3=按钮(button)
   */
  const loadFormData = (): void => {
    if (!props.editData) return

    isEdit.value = true
    const row = props.editData

    // 只有编辑模式（有 id）才根据后端 type 设置前端菜单类型
    if (row.id) {
      const typeReverseMap: Record<number, 'dir' | 'menu' | 'button'> = {
        1: 'dir',
        2: 'menu',
        3: 'button',
      }
      form.menuType = typeReverseMap[row.type] || 'menu'
    }
    // 新增模式（无 id）保持外部传入的 form.menuType（通过 props.type 设置）

    if (form.menuType === 'button') {
      form.authName = row.name || row.title || ''
      form.authLabel = row.permission || row.authMark || ''
      form.authSort = row.sort || 1
    } else {
      form.id = row.id || 0
      form.parent_id = row.parent_id || 0
      form.name = row.name || formatMenuTitle(row.meta?.title || '')
      form.path = row.path || ''
      form.label = row.route_name || ''
      form.component = row.component || ''
      form.redirect = row.redirect || ''
      form.icon = row.icon || row.meta?.icon || ''
      form.sort = row.sort || 1
      form.keepAlive = row.is_cache === 1 || row.meta?.keepAlive || false
      form.isHide = row.is_visible === 0 || row.meta?.isHide || false
      form.isHideTab = row.is_hide_tab === 1 || row.meta?.isHideTab || false
      form.isEnable = row.status === 1 || row.meta?.isEnable || true
      form.link = (row.is_external === 1 ? row.path : '') || row.meta?.link || ''
      form.isIframe = row.is_iframe === 1 || row.meta?.isIframe || false
      form.fixedTab = row.fixed_tab === 1 || row.meta?.fixedTab || false
      form.activePath = row.active_path || row.meta?.activePath || ''
      form.isFullPage = row.is_full_page === 1 || row.meta?.isFullPage || false
      form.roles = row.meta?.roles || []
    }
  }

  /**
   * 将前端表单数据转换为后端需要的 snake_case 格式
   * 类型映射：dir=1, menu=2, button=3
   */
  const convertToBackendFormat = (): MenuSubmitData => {
    // 按钮类型
    if (form.menuType === 'button') {
      return {
        parent_id: form.parent_id || 0,
        name: form.authName,
        permission: form.authLabel,
        type: 3,
        sort: form.authSort,
        status: 1,
        is_visible: 1,
        is_cache: 0,
        is_hide_tab: 0,
        is_iframe: 0,
        is_full_page: 0,
        fixed_tab: 0,
        is_external: 0,
      }
    }

    // 自动生成 route_name（PascalCase）
    const generateRouteName = (path: string): string => {
      const segments = path.split('/').filter(Boolean)
      return segments.map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('')
    }

    // 类型映射：dir=1, menu=2
    const typeMap: Record<string, number> = {
      dir: 1,
      menu: 2,
    }

    return {
      ...(form.id > 0 ? { id: form.id } : {}),
      parent_id: form.parent_id || 0,
      name: form.name,
      route_name: form.label || generateRouteName(form.path),
      path: form.path,
      component: form.component,
      redirect: form.redirect || undefined,
      icon: form.icon,
      type: typeMap[form.menuType] ?? 2, // 默认为菜单类型
      permission: form.label || undefined,
      sort: form.sort,
      status: form.isEnable ? 1 : 0,
      is_visible: form.isHide ? 0 : 1,
      is_cache: form.keepAlive ? 1 : 0,
      is_hide_tab: form.isHideTab ? 1 : 0,
      is_iframe: form.isIframe ? 1 : 0,
      is_full_page: form.isFullPage ? 1 : 0,
      fixed_tab: form.fixedTab ? 1 : 0,
      active_path: form.activePath || undefined,
      is_external: form.link ? 1 : 0,
    }
  }

  /**
   * 提交表单
   */
  const handleSubmit = async (): Promise<void> => {
    if (!formRef.value) return

    try {
      await formRef.value.validate()
      const submitData = convertToBackendFormat()
      emit('submit', submitData)
      // 成功提示由父组件统一处理，避免重复弹窗
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
    isEdit.value = false
  }

  /**
   * 监听对话框显示状态
   */
  watch(
    () => props.visible,
    (newVal) => {
      if (newVal) {
        form.menuType = props.type
        nextTick(() => {
          if (props.editData) {
            loadFormData()
          }
        })
      }
    }
  )

  /**
   * 监听菜单类型变化
   */
  watch(
    () => props.type,
    (newType) => {
      if (props.visible) {
        form.menuType = newType
      }
    }
  )
</script>
