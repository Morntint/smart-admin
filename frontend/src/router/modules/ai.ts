/**
 * AI 智能模块 - 本地路由
 *
 * 注：实际路由由后端 /admin/menu/routes 动态生成。
 * 此文件仅作为前端开发时的参考和 fallback。
 */
import type { RouteRecordRaw } from 'vue-router'

const LAYOUT = () => import('@/layouts/index.vue')

export const aiRoutes: RouteRecordRaw = {
  path: '/ai',
  component: LAYOUT,
  redirect: '/ai/chat',
  name: 'AI',
  meta: {
    title: 'AI 智能',
    icon: 'cpu',
    roles: ['R_SUPER']
  },
  children: [
    {
      path: 'chat',
      name: 'AIChat',
      component: () => import('@/views/ai/chat/index.vue'),
      meta: {
        title: '对话工作台',
        icon: 'chat-dot-round',
        keepAlive: true
      }
    },
    {
      path: 'agent',
      name: 'AIAgent',
      component: () => import('@/views/ai/agent/index.vue'),
      meta: {
        title: 'Agent 管理',
        icon: 'robot',
        keepAlive: true
      }
    },
    {
      path: 'agent-tool',
      name: 'AIAgentTool',
      component: () => import('@/views/ai/agent-tool/index.vue'),
      meta: {
        title: '工具库',
        icon: 'tools',
        keepAlive: true
      }
    },
    {
      path: 'model',
      name: 'AIModel',
      component: () => import('@/views/ai/model/index.vue'),
      meta: {
        title: '模型管理',
        icon: 'cpu',
        keepAlive: true
      }
    },
    {
      path: 'knowledge',
      name: 'AIKnowledge',
      component: () => import('@/views/ai/knowledge/index.vue'),
      meta: {
        title: '知识库',
        icon: 'collection',
        keepAlive: true
      }
    },
    {
      path: 'prompt',
      name: 'AIPrompt',
      component: () => import('@/views/ai/prompt/index.vue'),
      meta: {
        title: '提示词管理',
        icon: 'document',
        keepAlive: true
      }
    },
    {
      path: 'usage',
      name: 'AIUsage',
      component: () => import('@/views/ai/usage/index.vue'),
      meta: {
        title: '用量统计',
        icon: 'data-line',
        keepAlive: true
      }
    }
  ]
}
