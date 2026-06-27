import request from '@/utils/http'

// 微信配置
export const fetchWechatConfig = () => request.get<any>({ url: '/admin/wechat/config' })

export const fetchWechatConfigSecret = (params: { key: string }) =>
  request.get<{ key: string; value: string }>({
    url: '/admin/wechat/config/secret',
    params
  })

export const updateWechatConfig = (data: Record<string, string>) =>
  request.post({ url: '/admin/wechat/config', data })

// 微信用户
export const fetchWeChatUserList = (params: {
  page: number
  limit: number
  app_type?: string
  keyword?: string
}) =>
  request.get<{ list: any[]; total: number; page: number; limit: number }>({
    url: '/admin/wechat/users',
    params
  })

export const fetchWeChatUserDetail = (params: { openid: string; app_type?: string }) =>
  request.get<any>({ url: '/admin/wechat/user/detail', params })

export const syncWeChatUsers = (data: { app_type?: string }) =>
  request.post({ url: '/admin/wechat/users/sync', data })

// 微信消息
export const fetchWeChatMessageList = (params: {
  page: number
  limit: number
  app_type?: string
  msg_type?: string
}) =>
  request.get<{ list: any[]; total: number; page: number; limit: number }>({
    url: '/admin/wechat/messages',
    params
  })

export const sendWeChatTemplateMessage = (data: {
  openid: string
  template_id: string
  data: Record<string, any>
  url?: string
  app_type?: string
}) => request.post({ url: '/admin/wechat/message/send', data })

// 微信模板
export const fetchWeChatTemplateList = (params: { app_type?: string }) =>
  request.get<{ list: any[]; total: number }>({ url: '/admin/wechat/templates', params })

export const syncWeChatTemplates = (data: { app_type?: string }) =>
  request.post({ url: '/admin/wechat/templates/sync', data })

// 微信菜单
export const fetchWeChatMenuConfig = (params: { app_type?: string; only_active?: boolean }) =>
  request.get<any>({ url: '/admin/wechat/menu', params })

export const saveWeChatMenu = (data: { app_type: string; button: any[] }) =>
  request.post({ url: '/admin/wechat/menu/save', data })

export const publishWeChatMenu = (data: { app_type: string; button?: any[] }) =>
  request.post({ url: '/admin/wechat/menu/publish', data })

// 微信素材
export const fetchWeChatMaterialList = (params: {
  page: number
  limit: number
  app_type?: string
  type?: string
}) =>
  request.get<{ list: any[]; total: number; page: number; limit: number }>({
    url: '/admin/wechat/materials',
    params
  })

export const syncWeChatMaterials = (data: { app_type?: string; type?: string }) =>
  request.post({ url: '/admin/wechat/materials/sync', data })

// JSSDK
export const fetchWeChatJssdkConfig = (params: { url: string; apis?: string; debug?: boolean }) =>
  request.get<any>({ url: '/admin/wechat/jssdk', params })

// 小程序
export const fetchWeChatMiniQrCode = (params: { path?: string; width?: number }) =>
  request.get<Blob>({ url: '/admin/wechat/mini/qrcode', params, responseType: 'blob' })

export const fetchWeChatMiniUnlimitedQrCode = (params: { scene: string }) =>
  request.get<Blob>({ url: '/admin/wechat/mini/unlimited-qrcode', params, responseType: 'blob' })
