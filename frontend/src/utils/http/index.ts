/**
 * HTTP 请求封装模块
 * 基于 Axios 封装的 HTTP 请求工具，提供统一的请求/响应处理
 *
 * ## 主要功能
 *
 * - 请求/响应拦截器（自动添加 Token、统一错误处理）
 * - 401 未授权自动登出（带防抖机制）
 * - 请求失败自动重试（可配置）
 * - 统一的成功/错误消息提示
 * - 支持 GET/POST/PUT/DELETE 等常用方法
 *
 * @module utils/http
 * @author Art Design Pro Team
 */

import axios, { AxiosRequestConfig, AxiosResponse, InternalAxiosRequestConfig } from 'axios'
import { useUserStore } from '@/store/modules/user'
import { ApiStatus } from './status'
import { HttpError, handleError, showError, showSuccess } from './error'
import { $t } from '@/locales'
import { BaseResponse } from '@/types'

/** 请求配置常量 */
const REQUEST_TIMEOUT = 15000
const LOGOUT_DELAY = 500
const MAX_RETRIES = 0
const RETRY_DELAY = 1000
const UNAUTHORIZED_DEBOUNCE_TIME = 3000

/** Token 在过期前多久触发自动刷新（毫秒） */
const TOKEN_REFRESH_AHEAD = 5 * 60 * 1000

/** 不参与自动刷新的接口（登录链路自身） */
const REFRESH_EXCLUDE_URLS = ['/admin/login', '/admin/captcha', '/admin/refresh', '/admin/logout']

/** 401防抖状态 */
let isUnauthorizedErrorShown = false
let unauthorizedTimer: NodeJS.Timeout | null = null

/** 刷新 Token 的进行中 Promise，用于并发去重 */
let refreshTokenPromise: Promise<string | null> | null = null

/** 扩展 AxiosRequestConfig */
interface ExtendedAxiosRequestConfig extends AxiosRequestConfig {
  showErrorMessage?: boolean
  showSuccessMessage?: boolean
}

const { VITE_API_URL, VITE_WITH_CREDENTIALS } = import.meta.env

/** Axios实例 */
const axiosInstance = axios.create({
  timeout: REQUEST_TIMEOUT,
  baseURL: '', // 开发环境通过 Vite 代理转发，生产环境使用 Nginx 代理
  withCredentials: VITE_WITH_CREDENTIALS === 'true',
  validateStatus: (status) => status >= 200 && status < 300,
  transformResponse: [
    (data, headers) => {
      const contentType = headers['content-type']
      if (contentType?.includes('application/json')) {
        try {
          return JSON.parse(data)
        } catch {
          return data
        }
      }
      return data
    }
  ]
})

/** 请求拦截器 - 关键修改：Token格式添加Bearer前缀 */
axiosInstance.interceptors.request.use(
  async (request: InternalAxiosRequestConfig) => {
    // 业务接口在发起前检查 Token 是否临近过期，必要时先刷新
    const url = request.url || ''
    if (!REFRESH_EXCLUDE_URLS.some((u) => url.includes(u))) {
      await ensureFreshToken()
    }

    const { accessToken } = useUserStore()
    if (accessToken) request.headers.set('Authorization', `Bearer ${accessToken}`)

    if (request.data && !(request.data instanceof FormData) && !request.headers['Content-Type']) {
      request.headers.set('Content-Type', 'application/json')
      request.data = JSON.stringify(request.data)
    }

    return request
  },
  (error) => {
    showError(createHttpError($t('httpMsg.requestConfigError'), ApiStatus.error))
    return Promise.reject(error)
  }
)

/** 响应拦截器 */
axiosInstance.interceptors.response.use(
  (response: AxiosResponse<BaseResponse>) => {
    const { code, msg } = response.data
    if (code === ApiStatus.success) return response
    if (code === ApiStatus.unauthorized) handleUnauthorizedError(msg)
    throw createHttpError(msg || $t('httpMsg.requestFailed'), code)
  },
  (error) => {
    if (error.response?.status === ApiStatus.unauthorized) handleUnauthorizedError()
    return Promise.reject(handleError(error))
  }
)

/** 统一创建HttpError */
function createHttpError(message: string, code: number) {
  return new HttpError(message, code)
}

/** 处理401错误（带防抖） */
function handleUnauthorizedError(message?: string): never {
  const error = createHttpError(message || $t('httpMsg.unauthorized'), ApiStatus.unauthorized)

  if (!isUnauthorizedErrorShown) {
    isUnauthorizedErrorShown = true
    logOut()

    unauthorizedTimer = setTimeout(resetUnauthorizedError, UNAUTHORIZED_DEBOUNCE_TIME)

    showError(error, true)
    throw error
  }

  throw error
}

/** 重置401防抖状态 */
function resetUnauthorizedError() {
  isUnauthorizedErrorShown = false
  if (unauthorizedTimer) clearTimeout(unauthorizedTimer)
  unauthorizedTimer = null
}

/** 退出登录函数 */
function logOut() {
  setTimeout(() => {
    useUserStore().logOut()
  }, LOGOUT_DELAY)
}

/**
 * 确保 Token 处于有效期内：临近过期时自动刷新。
 * 并发请求共享同一个刷新 Promise，避免重复刷新。
 */
async function ensureFreshToken(): Promise<void> {
  const userStore = useUserStore()
  const { accessToken, accessTokenExpire } = userStore

  // 未登录或无法解析过期时间时不处理
  if (!accessToken || !accessTokenExpire) return

  // 未临近过期，无需刷新
  if (accessTokenExpire - Date.now() > TOKEN_REFRESH_AHEAD) return

  // Token 已过期，刷新接口也会失败，交由 401 流程处理
  if (accessTokenExpire <= Date.now()) return

  // 去重：复用进行中的刷新请求
  if (!refreshTokenPromise) {
    refreshTokenPromise = doRefreshToken().finally(() => {
      refreshTokenPromise = null
    })
  }

  await refreshTokenPromise
}

/**
 * 调用后端刷新接口换取新 Token 并写入 store。
 * 失败时静默返回 null（保留旧 Token，由后续 401 流程兜底）。
 */
async function doRefreshToken(): Promise<string | null> {
  const userStore = useUserStore()
  try {
    const res = await axiosInstance.post<BaseResponse<{ token: string }>>('/admin/refresh', null, {
      headers: { Authorization: `Bearer ${userStore.accessToken}` }
    })

    const token = res.data?.data?.token
    if (res.data?.code === ApiStatus.success && token) {
      userStore.setToken(token)
      return token
    }
    return null
  } catch {
    // 刷新失败不主动登出，等待业务请求触发 401 统一处理
    return null
  }
}

/** 是否需要重试 */
function shouldRetry(statusCode: number) {
  return [
    ApiStatus.requestTimeout,
    ApiStatus.internalServerError,
    ApiStatus.badGateway,
    ApiStatus.serviceUnavailable,
    ApiStatus.gatewayTimeout
  ].includes(statusCode)
}

/** 请求重试逻辑 */
async function retryRequest<T>(
  config: ExtendedAxiosRequestConfig,
  retries: number = MAX_RETRIES
): Promise<T> {
  try {
    return await request<T>(config)
  } catch (error) {
    if (retries > 0 && error instanceof HttpError && shouldRetry(error.code)) {
      await delay(RETRY_DELAY)
      return retryRequest<T>(config, retries - 1)
    }
    throw error
  }
}

/** 延迟函数 */
function delay(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

/** 请求函数 */
async function request<T = any>(config: ExtendedAxiosRequestConfig): Promise<T> {
  // POST | PUT 参数自动填充
  if (
    ['POST', 'PUT'].includes(config.method?.toUpperCase() || '') &&
    config.params &&
    !config.data
  ) {
    config.data = config.params
    config.params = undefined
  }

  try {
    const res = await axiosInstance.request<BaseResponse<T>>(config)

    // 显示成功消息
    if (config.showSuccessMessage && res.data.msg) {
      showSuccess(res.data.msg)
    }

    return res.data.data as T
  } catch (error) {
    if (error instanceof HttpError && error.code !== ApiStatus.unauthorized) {
      const showMsg = config.showErrorMessage !== false
      showError(error, showMsg)
    }
    return Promise.reject(error)
  }
}

/** API方法集合 */
const api = {
  get<T>(config: ExtendedAxiosRequestConfig) {
    return retryRequest<T>({ ...config, method: 'GET' })
  },
  post<T>(config: ExtendedAxiosRequestConfig) {
    return retryRequest<T>({ ...config, method: 'POST' })
  },
  put<T>(config: ExtendedAxiosRequestConfig) {
    return retryRequest<T>({ ...config, method: 'PUT' })
  },
  del<T>(config: ExtendedAxiosRequestConfig) {
    return retryRequest<T>({ ...config, method: 'DELETE' })
  },
  patch<T>(config: ExtendedAxiosRequestConfig) {
    return retryRequest<T>({ ...config, method: 'PATCH' })
  },
  request<T>(config: ExtendedAxiosRequestConfig) {
    return retryRequest<T>(config)
  }
}

export default api
