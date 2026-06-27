import { AppRouteRecord } from '@/types/router'

export const wechatRoutes: AppRouteRecord = {
  path: '/wechat',
  name: 'WeChat',
  component: '/index/index',
  meta: {
    title: 'menus.wechat.title',
    icon: 'ri:wechat-2-line'
  },
  children: [
    {
      path: '',
      name: 'WeChatManage',
      component: '/wechat/index',
      meta: {
        title: 'menus.wechat.manage',
        icon: 'ri:settings-3-line',
        keepAlive: true
      }
    },
    {
      path: 'user',
      name: 'WeChatUser',
      component: '/wechat/user',
      meta: {
        title: 'menus.wechat.user',
        icon: 'ri:user-line',
        keepAlive: true,
        authList: [{ title: '同步用户', authMark: 'wechat:user:sync' }]
      }
    },
    {
      path: 'message',
      name: 'WeChatMessage',
      component: '/wechat/message',
      meta: {
        title: 'menus.wechat.message',
        icon: 'ri:message-2-line',
        keepAlive: true,
        authList: [{ title: '发送消息', authMark: 'wechat:message:send' }]
      }
    },
    {
      path: 'template',
      name: 'WeChatTemplate',
      component: '/wechat/template',
      meta: {
        title: 'menus.wechat.template',
        icon: 'ri:file-text-line',
        keepAlive: true,
        authList: [{ title: '同步模板', authMark: 'wechat:template:sync' }]
      }
    },
    {
      path: 'menu',
      name: 'WeChatMenu',
      component: '/wechat/menu',
      meta: {
        title: 'menus.wechat.menu',
        icon: 'ri:menu-line',
        keepAlive: true,
        authList: [
          { title: '编辑菜单', authMark: 'wechat:menu:edit' },
          { title: '发布菜单', authMark: 'wechat:menu:publish' }
        ]
      }
    },
    {
      path: 'material',
      name: 'WeChatMaterial',
      component: '/wechat/material',
      meta: {
        title: 'menus.wechat.material',
        icon: 'ri:image-line',
        keepAlive: true,
        authList: [
          { title: '同步素材', authMark: 'wechat:material:sync' },
          { title: '删除素材', authMark: 'wechat:material:del' }
        ]
      }
    },
    {
      path: 'config',
      name: 'WeChatConfig',
      component: '/wechat/config',
      meta: {
        title: 'menus.wechat.config',
        icon: 'ri:settings-4-line',
        keepAlive: true,
        authList: [
          { title: '编辑配置', authMark: 'wechat:config:edit' },
          { title: '查看密钥', authMark: 'wechat:config:view-secret' }
        ]
      }
    }
  ]
}
