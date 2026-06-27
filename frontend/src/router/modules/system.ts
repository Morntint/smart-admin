import { AppRouteRecord } from '@/types/router'

export const systemRoutes: AppRouteRecord = {
  path: '/system',
  name: 'System',
  component: '/index/index',
  meta: {
    title: 'menus.system.title',
    icon: 'ri:user-3-line',
    roles: ['R_SUPER', 'R_ADMIN']
  },
  children: [
    {
      path: 'user',
      name: 'User',
      component: '/system/user',
      meta: {
        title: 'menus.system.user',
        icon: 'ri:user-line',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN']
      }
    },
    {
      path: 'role',
      name: 'Role',
      component: '/system/role',
      meta: {
        title: 'menus.system.role',
        icon: 'ri:user-settings-line',
        keepAlive: true,
        roles: ['R_SUPER']
      }
    },
    {
      path: 'user-center',
      name: 'UserCenter',
      component: '/system/user-center',
      meta: {
        title: 'menus.system.userCenter',
        icon: 'ri:user-line',
        isHide: true,
        keepAlive: true,
        isHideTab: true
      }
    },
    {
      path: 'menu',
      name: 'Menus',
      component: '/system/menu',
      meta: {
        title: 'menus.system.menu',
        icon: 'ri:menu-line',
        keepAlive: true,
        roles: ['R_SUPER'],
        authList: [
          { title: '新增', authMark: 'add' },
          { title: '编辑', authMark: 'edit' },
          { title: '删除', authMark: 'delete' }
        ]
      }
    },
    {
      path: 'dept',
      name: 'Dept',
      component: '/system/dept',
      meta: {
        title: 'menus.system.dept',
        icon: 'ri:organization-chart',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN']
      }
    },
    {
      path: 'dict',
      name: 'Dict',
      component: '/system/dict',
      meta: {
        title: 'menus.system.dict',
        icon: 'ri:book-2-line',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN']
      }
    },
    {
      path: 'config',
      name: 'Config',
      component: '/system/config',
      meta: {
        title: 'menus.system.config',
        icon: 'ri:settings-3-line',
        keepAlive: true,
        roles: ['R_SUPER']
      }
    },
    {
      path: 'log',
      name: 'Log',
      component: '/system/log',
      meta: {
        title: 'menus.system.log',
        icon: 'ri:file-list-2-line',
        keepAlive: true,
        roles: ['R_SUPER'],
        isHide: true
      }
    },
    {
      path: 'login-log',
      name: 'LoginLog',
      component: '/system/login-log',
      meta: {
        title: 'menus.system.loginLog',
        icon: 'ri:login-circle-line',
        keepAlive: true,
        roles: ['R_SUPER'],
        isHide: true
      }
    },
    {
      path: 'system-log',
      name: 'SystemLog',
      component: '/system/system-log',
      meta: {
        title: 'menus.system.systemLog',
        icon: 'ri:file-list-3-line',
        keepAlive: true,
        roles: ['R_SUPER']
      }
    },
    {
      path: 'file',
      name: 'File',
      component: '/system/file',
      meta: {
        title: 'menus.system.file',
        icon: 'ri:folder-3-line',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN']
      }
    },
    {
      path: 'system-notice',
      name: 'SystemNotice',
      component: '/system/notice/system',
      meta: {
        title: 'menus.system.systemNotice',
        icon: 'ri:mail-line',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN'],
        isHide: true,
        authList: [
          { title: '查询', authMark: 'system:notice:list' },
          { title: '发送', authMark: 'system:notice:add' },
          { title: '编辑', authMark: 'system:notice:edit' },
          { title: '删除', authMark: 'system:notice:del' },
          { title: '标记已读', authMark: 'system:notice:read' }
        ]
      }
    },
    {
      path: 'notice/inbox',
      name: 'NoticeInbox',
      component: '/system/notice/inbox',
      meta: {
        title: 'menus.system.noticeInbox',
        icon: 'ri:mail-unread-line',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN']
      }
    },
    {
      path: 'system-announcement',
      name: 'SystemAnnouncement',
      component: '/system/notice/announcement',
      meta: {
        title: 'menus.system.systemAnnouncement',
        icon: 'ri:volume-up-line',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN'],
        isHide: true,
        authList: [
          { title: '查询', authMark: 'system:announcement:list' },
          { title: '新增', authMark: 'system:announcement:add' },
          { title: '编辑', authMark: 'system:announcement:edit' },
          { title: '删除', authMark: 'system:announcement:del' },
          { title: '发布', authMark: 'system:announcement:publish' }
        ]
      }
    },
    {
      path: 'notice',
      name: 'Notice',
      component: '/system/notice/index',
      meta: {
        title: 'menus.system.notice',
        icon: 'ri:notification-3-line',
        keepAlive: true,
        roles: ['R_SUPER', 'R_ADMIN']
      }
    },
    {
      path: 'nested',
      name: 'Nested',
      component: '',
      meta: {
        title: 'menus.system.nested',
        icon: 'ri:menu-unfold-3-line',
        keepAlive: true
      },
      children: [
        {
          path: 'menu1',
          name: 'NestedMenu1',
          component: '/system/nested/menu1',
          meta: {
            title: 'menus.system.menu1',
            icon: 'ri:align-justify',
            keepAlive: true
          }
        },
        {
          path: 'menu2',
          name: 'NestedMenu2',
          component: '',
          meta: {
            title: 'menus.system.menu2',
            icon: 'ri:align-justify',
            keepAlive: true
          },
          children: [
            {
              path: 'menu2-1',
              name: 'NestedMenu2-1',
              component: '/system/nested/menu2',
              meta: {
                title: 'menus.system.menu21',
                icon: 'ri:align-justify',
                keepAlive: true
              }
            }
          ]
        },
        {
          path: 'menu3',
          name: 'NestedMenu3',
          component: '',
          meta: {
            title: 'menus.system.menu3',
            icon: 'ri:align-justify',
            keepAlive: true
          },
          children: [
            {
              path: 'menu3-1',
              name: 'NestedMenu3-1',
              component: '/system/nested/menu3',
              meta: {
                title: 'menus.system.menu31',
                keepAlive: true
              }
            },
            {
              path: 'menu3-2',
              name: 'NestedMenu3-2',
              component: '',
              meta: {
                title: 'menus.system.menu32',
                keepAlive: true
              },
              children: [
                {
                  path: 'menu3-2-1',
                  name: 'NestedMenu3-2-1',
                  component: '/system/nested/menu3/menu3-2',
                  meta: {
                    title: 'menus.system.menu321',
                    keepAlive: true
                  }
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
