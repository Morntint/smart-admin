import { AppRouteRecord } from '@/types/router'

export const monitorRoutes: AppRouteRecord = {
  path: '/monitor',
  name: 'Monitor',
  component: '/index/index',
  meta: {
    title: 'menus.monitor.title',
    icon: 'ri:computer-line'
  },
  children: [
    {
      path: 'server',
      name: 'MonitorServer',
      component: '/monitor/server',
      meta: {
        title: 'menus.monitor.server',
        icon: 'ri:hard-drive-2-line',
        keepAlive: true,
        roles: ['R_SUPER']
      }
    }
  ]
}
