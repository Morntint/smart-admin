/**
 * API 接口类型定义模块
 *
 * 提供所有后端接口的类型定义
 *
 * ## 主要功能
 *
 * - 通用类型（分页参数、响应结构等）
 * - 认证类型（登录、用户信息等）
 * - 系统管理类型（用户、角色等）
 * - 全局命名空间声明
 *
 * ## 使用场景
 *
 * - API 请求参数类型约束
 * - API 响应数据类型定义
 * - 接口文档类型同步
 *
 * ## 注意事项
 *
 * - 在 .vue 文件使用需要在 eslint.config.mjs 中配置 globals: { Api: 'readonly' }
 * - 使用全局命名空间，无需导入即可使用
 *
 * ## 使用方式
 *
 * ```typescript
 * const params: Api.Auth.LoginParams = { userName: 'admin', password: '123456' }
 * const response: Api.Auth.UserInfo = await fetchUserInfo()
 * ```
 *
 * @module types/api/api
 * @author Art Design Pro Team
 */

declare namespace Api {
  /** 通用类型 */
  namespace Common {
    /** 分页参数 */
    interface PaginationParams {
      /** 当前页码 */
      current: number
      /** 每页条数 */
      size: number
      /** 总条数 */
      total: number
    }

    /** 通用搜索参数 */
    type CommonSearchParams = Pick<PaginationParams, 'current' | 'size'>

    /** 分页响应基础结构 */
    interface PaginatedResponse<T = any> {
      records: T[]
      current: number
      size: number
      total: number
    }

    /** 启用状态 */
    type EnableStatus = '1' | '2'
  }

  /** 认证类型 */
  namespace Auth {
    /** 登录参数（与后端一致：snake_case） */
    interface LoginParams {
      username: string
      password: string
      captcha_key: string
      captcha: string
    }

    /** 登录响应 */
    interface LoginResponse {
      token: string
      refreshToken: string
    }

    /** 用户信息 */
    interface UserInfo {
      buttons: string[]
      roles: string[]
      userId: number
      username: string
      email: string
      avatar?: string
    }
  }

  /** 系统管理类型（与后端字段一致：snake_case） */
  namespace SystemManage {
    /** 用户列表项（与后端 sys_user 表字段一致） */
    interface UserListItem {
      id: number
      username: string
      nickname?: string
      avatar?: string
      mobile?: string
      email?: string
      sex: number
      status: number
      dept_id?: number
      dept_name?: string
      remark?: string
      login_ip?: string
      login_time?: string
      login_count?: number
      created_at: string
      updated_at?: string
      /** 角色 ID 列表（详情接口返回，用于编辑回填） */
      role_ids?: number[]
      /** 角色名称列表（列表/详情接口返回，用于展示） */
      role_names?: string[]
    }

    /** 用户新增/编辑提交参数（与后端 UserValidator 字段一致） */
    interface UserSubmitParams {
      username?: string
      password?: string
      nickname?: string
      email?: string
      mobile?: string
      sex?: number
      status?: number
      dept_id?: number
      role_ids?: number[]
      remark?: string
    }

    /** 用户搜索参数 */
    type UserSearchParams = Partial<{
      page: number
      limit: number
      keyword: string
      status: string
      dept_id: number
    }>

    /** 角色列表项 */
    interface RoleListItem {
      id: number
      name: string
      code: string
      status: number
      data_scope: number
      data_scope_depts?: number[]
      sort: number
      remark?: string
      create_time: string
      update_time?: string
    }

    /** 角色搜索参数 */
    type RoleSearchParams = Partial<{
      page: number
      limit: number
      keyword: string
      status: string
    }>

    /** 菜单列表项（管理端） */
    interface MenuListItem {
      id: number
      parent_id: number
      name: string
      route_name?: string
      path: string
      component?: string
      redirect?: string
      icon?: string
      type: number
      permission?: string
      sort: number
      status: number
      is_visible: number
      is_cache: number
      is_hide_tab?: number
      is_iframe?: number
      is_full_page?: number
      fixed_tab?: number
      active_path?: string
      is_external?: number
      remark?: string
      created_at: string
      updated_at?: string
      children?: MenuListItem[]
    }

    /** 路由菜单列表项（前端路由） */
    interface MenuRouteItem {
      id: number
      path: string
      name: string
      component: string
      redirect?: string
      meta: {
        title: string
        icon: string
        isHide?: boolean
        isHideTab?: boolean
        keepAlive?: boolean
        isIframe?: boolean
        isFullPage?: boolean
        fixedTab?: boolean
        link?: string | null
        activePath?: string | null
      }
      children?: MenuRouteItem[]
    }

    /** 部门列表项 */
    interface DeptListItem {
      id: number
      parent_id: number
      name: string
      leader?: string
      phone?: string
      email?: string
      sort: number
      status: number
      create_time: string
      children?: DeptListItem[]
    }

    /** 字典类型列表项 */
    interface DictListItem {
      id: number
      name: string
      code: string
      type: number
      status: number
      remark?: string
      create_time: string
      update_time?: string
      data_count?: number
    }

    /** 字典数据列表项 */
    interface DictDataListItem {
      id: number
      dict_id: number
      label: string
      value: string
      sort: number
      status: number
      color?: string
      remark?: string
      create_time: string
    }

    /** 系统配置列表项 */
    interface ConfigListItem {
      id: number
      name: string
      key: string
      value: string
      group: string
      type: 'string' | 'number' | 'boolean' | 'json'
      options?: string
      sort: number
      status: number
      remark?: string
      is_public: number
      create_time: string
      update_time?: string
    }

    /** 操作日志列表项 */
    interface OperationLogListItem {
      id: number
      module?: string
      action?: string
      method: string
      url: string
      ip?: string
      user_agent?: string
      user_id?: number
      username?: string
      param?: string
      result?: string
      status: number
      error_msg?: string
      duration?: number
      created_at: string
      formatted_param?: Record<string, unknown>
    }

    /** 登录日志列表项 */
    interface LoginLogListItem {
      id: number
      user_id?: number
      username?: string
      login_type: number
      ip?: string
      ip_location?: string
      user_agent?: string
      status: number
      msg?: string
      created_at: string
    }

    /** 文件列表项 */
    interface FileListItem {
      id: number
      name: string
      original_name: string
      path: string
      url: string
      size: number
      mime_type: string
      extension: string
      storage: string
      hash?: string
      create_by?: number
      create_time: string
    }

    /** 系统通知列表项 */
    interface NoticeListItem {
      id: number
      user_id: number
      username?: string
      user_nickname?: string
      type: number
      type_text?: string
      level: 'info' | 'success' | 'warning' | 'danger'
      level_text?: string
      title: string
      content?: string
      biz_type?: string
      biz_id?: string
      link?: string
      is_read: number
      read_time?: string
      sender_id?: number
      sender_name?: string
      expire_time?: string
      /** 派生字段：1 已过期 / 0 未过期；myInbox 接口下发 */
      is_expired?: 0 | 1
      created_at: string
      updated_at?: string
    }

    /** 系统通知搜索参数 */
    type NoticeSearchParams = Partial<{
      page: number
      limit: number
      keyword: string
      type: string
      level: string
      is_read: string
      user_id: number
      start_date: string
      end_date: string
    }>

    /** 系统通知提交参数 */
    interface NoticeSubmitParams {
      user_id?: number
      user_ids?: number[]
      type?: number
      level?: 'info' | 'success' | 'warning' | 'danger'
      title?: string
      content?: string
      biz_type?: string
      biz_id?: string
      link?: string
      expire_time?: string
    }

    /** 系统通知未读统计 */
    interface NoticeUnreadStats {
      total: number
      by_level: {
        info: number
        success: number
        warning: number
        danger: number
      }
    }

    /** 系统公告列表项 */
    interface AnnouncementListItem {
      id: number
      title: string
      content: string
      category: 'notice' | 'announcement' | 'activity' | 'maintenance'
      category_text?: string
      level: 'info' | 'important' | 'urgent'
      level_text?: string
      is_top: number
      is_popup: number
      status: number
      status_text?: string
      publisher_id?: number
      publisher_name?: string
      published_at?: string
      effective_at?: string
      expire_at?: string
      view_count: number
      sort: number
      remark?: string
      created_at: string
      updated_at?: string
    }

    /** 系统公告搜索参数 */
    type AnnouncementSearchParams = Partial<{
      page: number
      limit: number
      keyword: string
      category: string
      level: string
      status: string
      is_top: string
      start_date: string
      end_date: string
    }>

    /** 系统公告提交参数 */
    interface AnnouncementSubmitParams {
      title?: string
      content?: string
      category?: 'notice' | 'announcement' | 'activity' | 'maintenance'
      level?: 'info' | 'important' | 'urgent'
      is_top?: number
      is_popup?: number
      status?: number
      effective_at?: string
      expire_at?: string
      sort?: number
      remark?: string
    }
  }
}
