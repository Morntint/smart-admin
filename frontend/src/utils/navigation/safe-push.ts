/**
 * 安全跳转工具
 *
 * 解决后端驱动菜单场景下 `router.push({ name: 'Xxx' })` 抛
 * "No match for {name: 'Xxx'}" 的问题（FE-N7）：
 *
 * 项目路由由后端 sys_menu 表驱动，命名路由是否存在取决于：
 *  - 该 name 的菜单是否在 sys_menu 表里
 *  - 当前用户角色是否被绑定到该菜单
 *  - MenuService.routeTree 是否把该菜单返回了
 *
 * 任一环节出问题都会让 router 没注册该 name，vue-router 立刻抛错。
 * 通过本工具：
 *  1. 命名跳转前用 `router.hasRoute(name)` 预检；
 *  2. 未注册时用预设的 fallback 路径降级；
 *  3. 仍然失败给出友好提示，不让整个 UI 卡死。
 *
 * 用法：
 *   import { safePush } from '@/utils/navigation/safe-push'
 *   safePush({ name: 'NoticeSend' }, { fallback: '/system/notice/system' })
 */

import { ElMessage } from 'element-plus'
import type { RouteLocationNamedRaw, RouteLocationPathRaw, Router } from 'vue-router'
import { router as defaultRouter } from '@/router'

export interface SafePushOptions {
  /** 命名路由不存在时跳转到的 path（推荐传相对稳定的列表页路径） */
  fallback?: string
  /** 命名路由不存在时是否提示用户 */
  notify?: boolean
  /** 自定义 router 实例（测试时用） */
  router?: Router
}

/**
 * 安全的路由跳转。
 *
 *  - 传 `name`：先 hasRoute 预检，未注册则走 fallback；
 *  - 传 `path`：直接走 router.push（路径未匹配 vue-router 会去 404 路由，不会抛错）。
 *
 * 返回 Promise<boolean> —— true 表示发生了导航；false 表示连 fallback 都失败。
 */
export const safePush = async (
  to: RouteLocationNamedRaw | RouteLocationPathRaw,
  options: SafePushOptions = {}
): Promise<boolean> => {
  const router = options.router ?? defaultRouter
  const fallback = options.fallback
  const notify = options.notify ?? false

  // path 模式：直接放行，vue-router 自己处理未匹配
  if ('path' in to && to.path) {
    try {
      await router.push(to)
      return true
    } catch {
      return false
    }
  }

  // name 模式：先预检
  const name = (to as RouteLocationNamedRaw).name
  if (typeof name === 'string' && router.hasRoute(name)) {
    try {
      await router.push(to)
      return true
    } catch {
      // 失败兜底走 fallback
    }
  }

  if (fallback) {
    try {
      await router.push(fallback)
      if (notify) {
        ElMessage.warning('当前账号未授权该页面，已跳转到列表页')
      }
      return true
    } catch {
      return false
    }
  }

  if (notify) {
    ElMessage.warning('当前账号未授权该页面')
  }
  return false
}
