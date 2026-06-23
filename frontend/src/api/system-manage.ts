import request from '@/utils/http'

// ==================== 用户管理 ====================

/**
 * 获取用户分页列表
 * @param params 搜索参数
 */
export function fetchGetUserList(params: {
  page?: number
  limit?: number
  keyword?: string
  status?: string
  dept_id?: number
}) {
  return request.get<{
    list: Api.SystemManage.UserListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/user',
    params
  })
}

/**
 * 获取用户详情
 * @param id 用户ID
 */
export function fetchGetUser(id: number) {
  return request.get<Api.SystemManage.UserListItem>({
    url: `/admin/user/${id}`
  })
}

/**
 * 创建用户
 * @param data 用户数据
 */
export function fetchCreateUser(data: Api.SystemManage.UserSubmitParams) {
  return request.post<{ id: number }>({
    url: '/admin/user',
    params: data,
    showSuccessMessage: true
  })
}

/**
 * 更新用户
 * @param id 用户ID
 * @param data 用户数据
 */
export function fetchUpdateUser(id: number, data: Api.SystemManage.UserSubmitParams) {
  return request.put({
    url: `/admin/user/${id}`,
    params: data,
    showSuccessMessage: true
  })
}

/**
 * 删除用户
 * @param id 用户ID
 */
export function fetchDeleteUser(id: number) {
  return request.del({
    url: `/admin/user/${id}`,
    showSuccessMessage: true
  })
}

/**
 * 切换用户状态
 * @param id 用户ID
 */
export function fetchToggleUserStatus(id: number) {
  return request.patch<{ status: number }>({
    url: `/admin/user/${id}/status`
  })
}

/**
 * 重置用户密码
 * @param id 用户ID
 * @param password 新密码（为空则使用默认密码）
 */
export function fetchResetUserPassword(id: number, password?: string) {
  return request.put({
    url: `/admin/user/${id}/reset-password`,
    params: { password: password || '' },
    showSuccessMessage: true
  })
}

// ==================== 角色管理 ====================

/**
 * 获取角色分页列表
 * @param params 搜索参数
 */
export function fetchGetRoleList(params: {
  page?: number
  limit?: number
  keyword?: string
  status?: string
}) {
  return request.get<{
    list: Api.SystemManage.RoleListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/role',
    params
  })
}

/**
 * 获取所有启用角色（下拉框）
 */
export function fetchGetAllRoles() {
  return request.get<Array<{ id: number; name: string; code: string }>>({
    url: '/admin/role/all'
  })
}

/**
 * 获取角色详情
 * @param id 角色ID
 */
export function fetchGetRole(id: number) {
  return request.get<Api.SystemManage.RoleListItem>({
    url: `/admin/role/${id}`
  })
}

/**
 * 获取角色菜单ID列表
 * @param id 角色ID
 */
export function fetchGetRoleMenus(id: number) {
  return request.get<number[]>({
    url: `/admin/role/${id}/menus`
  })
}

/**
 * 创建角色
 * @param data 角色数据
 */
export function fetchCreateRole(data: Partial<Api.SystemManage.RoleListItem>) {
  return request.post<{ id: number }>({
    url: '/admin/role',
    params: data,
    showSuccessMessage: true
  })
}

/**
 * 更新角色
 * @param id 角色ID
 * @param data 角色数据
 */
export function fetchUpdateRole(id: number, data: Partial<Api.SystemManage.RoleListItem>) {
  return request.put({
    url: `/admin/role/${id}`,
    params: data,
    showSuccessMessage: true
  })
}

/**
 * 删除角色
 * @param id 角色ID
 */
export function fetchDeleteRole(id: number) {
  return request.del({
    url: `/admin/role/${id}`,
    showSuccessMessage: true
  })
}

/**
 * 分配菜单权限
 * @param id 角色ID
 * @param menu_ids 菜单ID数组
 */
export function fetchAssignRoleMenus(id: number, menu_ids: number[]) {
  return request.post({
    url: `/admin/role/${id}/menus`,
    params: { menu_ids }
  })
}

/**
 * 获取角色数据范围
 * @param id 角色ID
 */
export function fetchGetRoleDataScope(id: number) {
  return request.get<{ data_scope: number; data_scope_depts: number[] }>({
    url: `/admin/role/${id}/data-scope`
  })
}

/**
 * 设置角色数据范围
 * @param id 角色ID
 * @param data 数据范围与自定义部门
 */
export function fetchSetRoleDataScope(
  id: number,
  data: { data_scope: number; data_scope_depts?: number[] }
) {
  return request.put({
    url: `/admin/role/${id}/data-scope`,
    params: data,
    showSuccessMessage: true
  })
}

// ==================== 菜单管理 ====================

/**
 * 获取菜单树形列表
 */
export function fetchGetMenuList(params?: { type?: string }) {
  return request.get<Api.SystemManage.MenuListItem[]>({
    url: '/admin/menu',
    params
  })
}

/**
 * 获取前端路由菜单列表（树形结构，适配 AppRouteRecord）
 * 用于前端动态路由加载
 */
export function fetchGetMenuRoutes() {
  return request.get<Api.SystemManage.MenuRouteItem[]>({
    url: '/admin/menu/routes'
  })
}

/**
 * 获取菜单平铺列表
 */
export function fetchGetMenuFlatList() {
  return request.get<Api.SystemManage.MenuListItem[]>({
    url: '/admin/menu/list'
  })
}

/**
 * 获取父级菜单选项
 * @param exclude_id 排除的节点ID
 */
export function fetchGetMenuOptions(exclude_id?: number) {
  return request.get<Array<{ id: number; name: string; parent_id: number }>>({
    url: '/admin/menu/options',
    params: exclude_id ? { exclude_id } : {}
  })
}

/**
 * 获取按钮权限列表
 */
export function fetchGetMenuPermissions() {
  return request.get<Array<{ id: number; name: string; auth_mark: string }>>({
    url: '/admin/menu/permissions'
  })
}

/**
 * 获取菜单详情
 * @param id 菜单ID
 */
export function fetchGetMenu(id: number) {
  return request.get<Api.SystemManage.MenuListItem>({
    url: `/admin/menu/${id}`
  })
}

/**
 * 创建菜单
 * @param data 菜单数据
 */
export function fetchCreateMenu(data: Partial<Api.SystemManage.MenuListItem>) {
  return request.post<{ id: number }>({
    url: '/admin/menu',
    params: data
    // 成功提示由调用方统一控制，避免重复弹窗
  })
}

/**
 * 更新菜单
 * @param id 菜单ID
 * @param data 菜单数据
 */
export function fetchUpdateMenu(id: number, data: Partial<Api.SystemManage.MenuListItem>) {
  return request.put({
    url: `/admin/menu/${id}`,
    params: data
    // 成功提示由调用方统一控制，避免重复弹窗
  })
}

/**
 * 删除菜单
 * @param id 菜单ID
 */
export function fetchDeleteMenu(id: number) {
  return request.del({
    url: `/admin/menu/${id}`,
    showSuccessMessage: true
  })
}

// ==================== 部门管理 ====================

/**
 * 获取部门树形列表
 */
export function fetchGetDeptList(params?: { keyword?: string; status?: string }) {
  return request.get<Api.SystemManage.DeptListItem[]>({
    url: '/admin/dept',
    params
  })
}

/**
 * 获取部门平铺列表
 */
export function fetchGetDeptFlatList() {
  return request.get<Api.SystemManage.DeptListItem[]>({
    url: '/admin/dept/list'
  })
}

/**
 * 获取父级部门选项
 */
export function fetchGetDeptOptions(exclude_id?: number) {
  return request.get<Array<{ id: number; name: string; parent_id: number }>>({
    url: '/admin/dept/options',
    params: exclude_id ? { exclude_id } : {}
  })
}

/**
 * 创建部门
 * @param data 部门数据
 */
export function fetchCreateDept(data: Partial<Api.SystemManage.DeptListItem>) {
  return request.post<{ id: number }>({
    url: '/admin/dept',
    params: data,
    showSuccessMessage: true
  })
}

/**
 * 更新部门
 * @param id 部门ID
 * @param data 部门数据
 */
export function fetchUpdateDept(id: number, data: Partial<Api.SystemManage.DeptListItem>) {
  return request.put({
    url: `/admin/dept/${id}`,
    params: data,
    showSuccessMessage: true
  })
}

/**
 * 删除部门
 * @param id 部门ID
 */
export function fetchDeleteDept(id: number) {
  return request.del({
    url: `/admin/dept/${id}`,
    showSuccessMessage: true
  })
}

// ==================== 字典管理 ====================

/**
 * 获取字典分页列表
 * @param params 搜索参数
 */
export function fetchGetDictList(params: {
  page?: number
  limit?: number
  keyword?: string
  status?: string
}) {
  return request.get<{
    list: Api.SystemManage.DictListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/dict',
    params
  })
}

/**
 * 获取所有启用字典
 */
export function fetchGetAllDicts() {
  return request.get<Array<{ id: number; name: string; code: string; type: number }>>({
    url: '/admin/dict/all'
  })
}

/**
 * 获取字典详情
 * @param id 字典ID
 */
export function fetchGetDict(id: number) {
  return request.get<Api.SystemManage.DictListItem>({
    url: `/admin/dict/${id}`
  })
}

/**
 * 按编码获取字典数据
 * @param code 字典编码
 */
export function fetchGetDictByCode(code: string) {
  return request.get<Api.SystemManage.DictDataListItem[]>({
    url: `/admin/dict/code/${code}`
  })
}

/**
 * 创建字典
 * @param data 字典数据
 */
export function fetchCreateDict(data: Partial<Api.SystemManage.DictListItem>) {
  return request.post<{ id: number }>({
    url: '/admin/dict',
    params: data
  })
}

/**
 * 更新字典
 * @param id 字典ID
 * @param data 字典数据
 */
export function fetchUpdateDict(id: number, data: Partial<Api.SystemManage.DictListItem>) {
  return request.put({
    url: `/admin/dict/${id}`,
    params: data
  })
}

/**
 * 删除字典
 * @param id 字典ID
 */
export function fetchDeleteDict(id: number) {
  return request.del({
    url: `/admin/dict/${id}`
  })
}

/**
 * 批量获取字典数据
 * @param codes 字典编码数组
 */
export function fetchBatchGetDict(codes: string[]) {
  return request.post<Record<string, Api.SystemManage.DictDataListItem[]>>({
    url: '/admin/dict/batch',
    params: { codes }
  })
}

// ==================== 字典数据管理 ====================

/**
 * 获取字典数据分页列表
 * @param params 搜索参数
 */
export function fetchGetDictDataList(params: {
  dict_id: number
  page?: number
  limit?: number
  label?: string
  value?: string
  status?: string
}) {
  return request.get<{
    list: Api.SystemManage.DictDataListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/dict-data',
    params
  })
}

/**
 * 创建字典数据
 * @param data 字典数据
 */
export function fetchCreateDictData(data: Partial<Api.SystemManage.DictDataListItem>) {
  return request.post<{ id: number }>({
    url: '/admin/dict-data',
    params: data
  })
}

/**
 * 更新字典数据
 * @param id 字典数据ID
 * @param data 字典数据
 */
export function fetchUpdateDictData(id: number, data: Partial<Api.SystemManage.DictDataListItem>) {
  return request.put({
    url: `/admin/dict-data/${id}`,
    params: data
  })
}

/**
 * 删除字典数据
 * @param id 字典数据ID
 */
export function fetchDeleteDictData(id: number) {
  return request.del({
    url: `/admin/dict-data/${id}`
  })
}

// ==================== 系统配置管理 ====================

/**
 * 获取系统配置分页列表
 * @param params 搜索参数
 */
export function fetchGetConfigList(params: {
  page?: number
  limit?: number
  group?: string
  keyword?: string
}) {
  return request.get<{
    list: Api.SystemManage.ConfigListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/config',
    params
  })
}

/**
 * 获取所有分组名称
 */
export function fetchGetConfigGroups() {
  return request.get<string[]>({
    url: '/admin/config/groups'
  })
}

/**
 * 按分组获取配置
 * @param group 分组名称
 */
export function fetchGetConfigByGroup(group: string) {
  return request.get<Api.SystemManage.ConfigListItem[]>({
    url: `/admin/config/group/${group}`
  })
}

/**
 * 获取配置详情
 * @param id 配置ID
 */
export function fetchGetConfig(id: number) {
  return request.get<Api.SystemManage.ConfigListItem>({
    url: `/admin/config/${id}`
  })
}

/**
 * 创建配置
 * @param data 配置数据
 */
export function fetchCreateConfig(data: Partial<Api.SystemManage.ConfigListItem>) {
  return request.post<{ id: number }>({
    url: '/admin/config',
    params: data
  })
}

/**
 * 更新配置
 * @param id 配置ID
 * @param data 配置数据
 */
export function fetchUpdateConfig(id: number, data: Partial<Api.SystemManage.ConfigListItem>) {
  return request.put({
    url: `/admin/config/${id}`,
    params: data
  })
}

/**
 * 删除配置
 * @param id 配置ID
 */
export function fetchDeleteConfig(id: number) {
  return request.del({
    url: `/admin/config/${id}`
  })
}

/**
 * 批量更新配置
 * @param configs 配置数组
 */
export function fetchBatchUpdateConfig(configs: Array<{ id: number; value: string }>) {
  return request.put({
    url: '/admin/batch/config',
    params: { configs },
    showSuccessMessage: true
  })
}

// ==================== 操作日志管理 ====================

/**
 * 获取操作日志分页列表
 * @param params 搜索参数
 */
export function fetchGetOperationLogList(params: {
  page?: number
  limit?: number
  keyword?: string
  module?: string
  status?: string
  start_date?: string
  end_date?: string
}) {
  return request.get<{
    list: Api.SystemManage.OperationLogListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/log/operation',
    params
  })
}

/**
 * 获取操作日志统计
 * @param days 统计天数
 */
export function fetchGetOperationLogStatistics(days?: number) {
  return request.get<{
    total: number
    success_count: number
    fail_count: number
    trend: Array<{ date: string; count: number }>
  }>({
    url: '/admin/log/operation/statistics',
    params: days ? { days } : {}
  })
}

/**
 * 获取操作日志详情
 * @param id 日志ID
 */
export function fetchGetOperationLog(id: number) {
  return request.get<Api.SystemManage.OperationLogListItem>({
    url: `/admin/log/operation/${id}`
  })
}

/**
 * 删除操作日志
 * @param id 日志ID
 */
export function fetchDeleteOperationLog(id: number) {
  return request.del({
    url: `/admin/log/operation/${id}`,
    showSuccessMessage: true
  })
}

/**
 * 批量删除操作日志
 * @param ids 日志ID数组
 */
export function fetchBatchDeleteOperationLog(ids: number[]) {
  return request.del({
    url: '/admin/log/operation',
    params: { ids },
    showSuccessMessage: true
  })
}

/**
 * 清理操作日志
 * @param days 保留天数
 */
export function fetchClearOperationLog(days: number) {
  return request.del({
    url: '/admin/log/operation/clear',
    params: { days },
    showSuccessMessage: true
  })
}

// ==================== 登录日志管理 ====================

/**
 * 获取登录日志分页列表
 * @param params 搜索参数
 */
export function fetchGetLoginLogList(params: {
  page?: number
  limit?: number
  keyword?: string
  status?: string
  login_type?: string
  start_date?: string
  end_date?: string
}) {
  return request.get<{
    list: Api.SystemManage.LoginLogListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/log/login',
    params
  })
}

/**
 * 获取登录日志统计
 * @param days 统计天数
 */
export function fetchGetLoginLogStatistics(days?: number) {
  return request.get<{
    total: number
    success_count: number
    fail_count: number
    trend: Array<{ date: string; count: number }>
  }>({
    url: '/admin/log/login/statistics',
    params: days ? { days } : {}
  })
}

/**
 * 获取登录日志详情
 * @param id 日志ID
 */
export function fetchGetLoginLog(id: number) {
  return request.get<Api.SystemManage.LoginLogListItem>({
    url: `/admin/log/login/${id}`
  })
}

/**
 * 删除登录日志
 * @param id 日志ID
 */
export function fetchDeleteLoginLog(id: number) {
  return request.del({
    url: `/admin/log/login/${id}`,
    showSuccessMessage: true
  })
}

/**
 * 批量删除登录日志
 * @param ids 日志ID数组
 */
export function fetchBatchDeleteLoginLog(ids: number[]) {
  return request.del({
    url: '/admin/log/login',
    params: { ids },
    showSuccessMessage: true
  })
}

/**
 * 清理登录日志
 * @param days 保留天数
 */
export function fetchClearLoginLog(days: number) {
  return request.del({
    url: '/admin/log/login/clear',
    params: { days },
    showSuccessMessage: true
  })
}

// ==================== 文件管理 ====================

/**
 * 获取文件分页列表
 * @param params 搜索参数
 */
export function fetchGetFileList(params: {
  page?: number
  limit?: number
  keyword?: string
  storage?: string
}) {
  return request.get<{
    list: Api.SystemManage.FileListItem[]
    total: number
    page: number
    limit: number
  }>({
    url: '/admin/file',
    params
  })
}

/**
 * 获取文件详情
 * @param id 文件ID
 */
export function fetchGetFile(id: number) {
  return request.get<Api.SystemManage.FileListItem>({
    url: `/admin/file/${id}`
  })
}

/**
 * 删除文件
 * @param id 文件ID
 */
export function fetchDeleteFile(id: number) {
  return request.del({
    url: `/admin/file/${id}`,
    showSuccessMessage: true
  })
}

/**
 * 批量删除文件
 * @param ids 文件ID数组
 */
export function fetchBatchDeleteFile(ids: number[]) {
  return request.del({
    url: '/admin/file/batch',
    params: { ids },
    showSuccessMessage: true
  })
}
