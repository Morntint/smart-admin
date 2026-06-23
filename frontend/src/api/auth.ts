import request from '@/utils/http'

/**
 * 获取图形验证码
 * @returns 验证码数据
 */
export function fetchCaptcha() {
  return request.get<{
    captcha_key: string
    captcha_image: string
  }>({
    url: '/admin/captcha'
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
