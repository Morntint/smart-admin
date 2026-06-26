import request from '@/utils/http'
import { useUserStore } from '@/store/modules/user'

// ==================== AI 模型管理 ====================

export function fetchGetAiModelList(params: {
  page?: number
  limit?: number
  keyword?: string
  provider?: string
  status?: string
}) {
  return request.get({
    url: '/admin/ai/model',
    params
  })
}

export function fetchGetEnabledAiModels() {
  return request.get({ url: '/admin/ai/model/enabled' })
}

export function fetchGetAiModel(id: number) {
  return request.get({ url: `/admin/ai/model/${id}` })
}

export function fetchCreateAiModel(data: Record<string, any>) {
  return request.post({ url: '/admin/ai/model', params: data, showSuccessMessage: true })
}

export function fetchUpdateAiModel(id: number, data: Record<string, any>) {
  return request.put({ url: `/admin/ai/model/${id}`, params: data, showSuccessMessage: true })
}

export function fetchDeleteAiModel(id: number) {
  return request.del({ url: `/admin/ai/model/${id}`, showSuccessMessage: true })
}

// ==================== AI Agent 管理 ====================

export function fetchGetAiAgentList(params: {
  page?: number
  limit?: number
  keyword?: string
  status?: string
  is_public?: string
}) {
  return request.get({
    url: '/admin/ai/agent',
    params
  })
}

export function fetchGetPublicAiAgents(params: {
  page?: number
  limit?: number
  keyword?: string
}) {
  return request.get({ url: '/admin/ai/agent/public', params })
}

export function fetchGetAiAgent(id: number) {
  return request.get({ url: `/admin/ai/agent/${id}` })
}

export function fetchCreateAiAgent(data: Record<string, any>) {
  return request.post({ url: '/admin/ai/agent', params: data, showSuccessMessage: true })
}

export function fetchUpdateAiAgent(id: number, data: Record<string, any>) {
  return request.put({ url: `/admin/ai/agent/${id}`, params: data, showSuccessMessage: true })
}

export function fetchDeleteAiAgent(id: number) {
  return request.del({ url: `/admin/ai/agent/${id}`, showSuccessMessage: true })
}

// ==================== Agent 工具库 ====================

export function fetchGetAiAgentToolList(params: {
  page?: number
  limit?: number
  keyword?: string
  tool_type?: string
  status?: string
}) {
  return request.get({ url: '/admin/ai/agent-tool', params })
}

export function fetchGetAiAgentToolAvailable() {
  return request.get({ url: '/admin/ai/agent-tool/available' })
}

export function fetchGetAiAgentTool(id: number) {
  return request.get({ url: `/admin/ai/agent-tool/${id}` })
}

export function fetchCreateAiAgentTool(data: Record<string, any>) {
  return request.post({ url: '/admin/ai/agent-tool', params: data, showSuccessMessage: true })
}

export function fetchUpdateAiAgentTool(id: number, data: Record<string, any>) {
  return request.put({ url: `/admin/ai/agent-tool/${id}`, params: data, showSuccessMessage: true })
}

export function fetchDeleteAiAgentTool(id: number) {
  return request.del({ url: `/admin/ai/agent-tool/${id}`, showSuccessMessage: true })
}

// ==================== 知识库管理 ====================

export function fetchGetKnowledgeList(params: {
  page?: number
  limit?: number
  keyword?: string
  status?: string
}) {
  return request.get({ url: '/admin/ai/knowledge', params })
}

export function fetchGetKnowledge(id: number) {
  return request.get({ url: `/admin/ai/knowledge/${id}` })
}

export function fetchCreateKnowledge(data: Record<string, any>) {
  return request.post({ url: '/admin/ai/knowledge', params: data, showSuccessMessage: true })
}

export function fetchUpdateKnowledge(id: number, data: Record<string, any>) {
  return request.put({ url: `/admin/ai/knowledge/${id}`, params: data, showSuccessMessage: true })
}

export function fetchDeleteKnowledge(id: number) {
  return request.del({ url: `/admin/ai/knowledge/${id}`, showSuccessMessage: true })
}

export function fetchGetDocuments(kbId: number, params: { page?: number; limit?: number; keyword?: string; status?: string }) {
  return request.get({ url: `/admin/ai/knowledge/${kbId}/documents`, params })
}

export function fetchUploadDocument(kbId: number, data: Record<string, any> | FormData) {
  const config: any = { url: `/admin/ai/knowledge/${kbId}/documents`, showSuccessMessage: true }
  if (data instanceof FormData) {
    config.data = data
  } else {
    config.params = data
  }
  return request.post(config)
}

export function fetchDeleteDocument(id: number) {
  return request.del({ url: `/admin/ai/knowledge/document/${id}`, showSuccessMessage: true })
}

export function fetchReprocessDocument(id: number) {
  return request.post({ url: `/admin/ai/knowledge/document/${id}/reprocess`, showSuccessMessage: true })
}

// ==================== 提示词管理 ====================

export function fetchGetPromptList(params: {
  page?: number
  limit?: number
  keyword?: string
  category?: string
  status?: string
}) {
  return request.get({ url: '/admin/ai/prompt', params })
}

export function fetchGetPromptCategories() {
  return request.get({ url: '/admin/ai/prompt/categories' })
}

export function fetchGetPrompt(id: number) {
  return request.get({ url: `/admin/ai/prompt/${id}` })
}

export function fetchCreatePrompt(data: Record<string, any>) {
  return request.post({ url: '/admin/ai/prompt', params: data, showSuccessMessage: true })
}

export function fetchUpdatePrompt(id: number, data: Record<string, any>) {
  return request.put({ url: `/admin/ai/prompt/${id}`, params: data, showSuccessMessage: true })
}

export function fetchDeletePrompt(id: number) {
  return request.del({ url: `/admin/ai/prompt/${id}`, showSuccessMessage: true })
}

export function fetchRenderPrompt(code: string, variables: Record<string, string>) {
  return request.post({ url: '/admin/ai/prompt/render', params: { code, variables } })
}

// ==================== AI 对话 ====================

export function fetchGetConversations(params: { page?: number; limit?: number }) {
  return request.get({ url: '/admin/ai/chat/conversations', params })
}

export function fetchCreateConversation(agentId: number) {
  return request.post({ url: '/admin/ai/chat/conversations', params: { agent_id: agentId } })
}

export function fetchGetAgentTools(agentId: number) {
  return request.get({ url: `/admin/ai/chat/agents/${agentId}/tools` })
}

export function fetchDeleteConversation(id: number) {
  return request.del({ url: `/admin/ai/chat/conversations/${id}`, showSuccessMessage: true })
}

export function fetchGetMessages(conversationId: number) {
  return request.get({ url: `/admin/ai/chat/conversations/${conversationId}/messages` })
}

export function fetchSendMessage(data: { conversation_id?: number; agent_id?: number; content: string }) {
  return request.post({ url: '/admin/ai/chat/send', params: data })
}

/**
 * 流式消息（返回原生 fetch response，前端自己处理 SSE）
 * 支持 tool_ids 参数来指定本次消息发送要使用的工具
 */
export function fetchSendMessageStream(data: {
  conversation_id?: number
  agent_id?: number
  content: string
  tool_ids?: number[] | null
}) {
  const userStore = useUserStore()
  const token = userStore.accessToken || ''
  // 直接用相对路径，开发环境走 Vite 代理，生产环境走 Nginx 代理
  return fetch('/admin/ai/chat/stream', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(data)
  })
}

// ==================== 用量统计 ====================

export function fetchGetUsageList(params: {
  page?: number
  limit?: number
  keyword?: string
  user_id?: number
  agent_id?: number
  model_name?: string
  start_date?: string
  end_date?: string
}) {
  return request.get({ url: '/admin/ai/usage', params })
}

export function fetchGetUsageSummary(params: { start_date?: string; end_date?: string }) {
  return request.get({ url: '/admin/ai/usage/summary', params })
}
