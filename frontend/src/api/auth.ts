import request from '@/utils/http'

/**
 * 验证码数据；后端关闭 captcha 时返回 204 No Content，前端在 `null` 时
 * 不渲染验证码控件（FE-3）。
 */
export type CaptchaPayload = {
  key: string
  image: string
} | null

/**
 * 获取图形验证码。
 *
 * 后端字段为 { key, image }（见 LoginService::captcha）；
 * 旧实现写的是 captcha_key/captcha_image，是错误的，会一直拿到 undefined。
 *
 * 关闭验证码时后端直接返回 HTTP 204，此处对应解析为 null。
 */
export function fetchCaptcha(): Promise<CaptchaPayload> {
  return request.get<CaptchaPayload>({
    url: '/admin/captcha',
    // 204 / data 为空时业务上不算错误，不弹消息
    showErrorMessage: false
  })
}

/**
 * 登录
 * @param params 登录参数
 * @returns 登录响应
 */
export function fetchLogin(params: {
  username: string
  password: string
  captcha_key: string
  captcha: string
}) {
  return request.post<{
    token: string
    user?: {
      id: number
      username: string
      nickname: string
      avatar: string
      email: string
      mobile?: string
    }
  }>({
    url: '/admin/login',
    params,
    showSuccessMessage: true
  })
}

/**
 * 退出登录
 */
export function fetchLogout() {
  return request.post({
    url: '/admin/logout',
    showSuccessMessage: true
  })
}

/**
 * 刷新Token
 */
export function fetchRefreshToken() {
  return request.post<{ token: string }>({
    url: '/admin/refresh'
  })
}

/**
 * 获取用户信息
 * @returns 用户信息
 */
export function fetchGetUserInfo() {
  return request.get<Api.Auth.UserInfo>({
    url: '/admin/user/info'
  })
}
