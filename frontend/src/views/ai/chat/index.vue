<template>
  <div class="ai-chat-page">
    <!-- 左侧：会话列表 -->
    <div class="chat-sidebar">
      <div class="sidebar-header">
        <el-button type="primary" @click="showAgentSelector = true" style="width: 100%">
          <el-icon><Plus /></el-icon> 新对话
        </el-button>
      </div>
      <div class="conversation-list">
        <div
          v-for="conv in conversations"
          :key="conv.id"
          class="conv-item"
          :class="{ active: conv.id === activeConvId }"
          @click="switchConversation(conv)"
        >
          <div class="conv-info">
            <span class="conv-title">{{ conv.title || '新对话' }}</span>
            <span class="conv-time">{{ formatTime(conv.updated_at) }}</span>
          </div>
          <el-button
            class="conv-delete"
            text
            size="small"
            @click.stop="handleDeleteConv(conv)"
          >
            <el-icon><Delete /></el-icon>
          </el-button>
        </div>
      </div>
    </div>

    <!-- 右侧：对话区 -->
    <div class="chat-main">
      <!-- 空状态 -->
      <div v-if="!activeConvId" class="chat-empty">
        <div class="empty-content">
          <el-icon class="empty-icon" :size="64"><ChatDotRound /></el-icon>
          <h2>AI 对话工作台</h2>
          <p>选择一个 Agent 开始对话，或从左侧选择已有会话</p>
          <el-button type="primary" size="large" @click="showAgentSelector = true">
            选择 Agent
          </el-button>
        </div>
      </div>

      <!-- 有活跃会话 -->
      <template v-else>
        <div class="chat-messages" ref="msgContainerRef">
          <div
            v-for="(msg, i) in messages"
            :key="i"
            class="message-item"
            :class="[msg.role, { 'tool-call': msg.role === 'tool' || msg.tool_calls }]"
          >
            <div class="msg-avatar">
              <el-avatar v-if="msg.role === 'user'" :size="36" icon="UserFilled" />
              <el-avatar v-else-if="msg.role === 'tool'" :size="36" icon="Tools" style="background: #67c23a" />
              <el-avatar v-else :size="36" icon="Cpu" style="background: #409eff" />
            </div>
            <div class="msg-body">
              <div class="msg-header">
                <span class="msg-role">
                  {{ getRoleLabel(msg.role) }}
                  <el-tag v-if="msg.tool_calls" size="small" type="warning" class="ml-2">
                    <el-icon class="mr-1"><Setting /></el-icon>
                    调用 {{ msg.tool_calls.length }} 个工具
                  </el-tag>
                  <el-tag v-if="msg.name" size="small" type="success" class="ml-2">
                    {{ msg.name }}
                  </el-tag>
                </span>
                <div v-if="msg.role === 'assistant'" class="msg-actions">
                  <el-button size="small" text :icon="RefreshRight" @click="regenerateMessage(i)">重新生成</el-button>
                  <el-button size="small" text :icon="CopyDocument" @click="copyContent(msg.content)">复制</el-button>
                </div>
              </div>

              <!-- 工具调用详情展示 -->
              <div v-if="msg.tool_calls && msg.tool_calls.length" class="tool-calls-panel">
                <div
                  v-for="(call, idx) in msg.tool_calls"
                  :key="idx"
                  class="tool-call-item"
                >
                  <div class="tool-call-header">
                    <el-icon class="tool-icon"><Promotion /></el-icon>
                    <span class="tool-name">{{ call.function?.name || call.name }}</span>
                  </div>
                  <div class="tool-call-args">
                    <pre>{{ formatToolArgs(call.function?.arguments) }}</pre>
                  </div>
                </div>
              </div>

              <!-- 消息内容 -->
              <div v-if="msg.content" class="msg-content" v-html="renderContent(msg.content)" />

              <!-- 工具返回结果展示 -->
              <div v-if="msg.role === 'tool'" class="tool-result-panel">
                <el-collapse>
                  <el-collapse-item title="工具执行结果" name="result">
                    <pre class="tool-result-content">{{ formatToolResult(msg.content) }}</pre>
                  </el-collapse-item>
                </el-collapse>
              </div>

              <div v-if="msg.role === 'assistant' && msg.token_usage" class="msg-meta">
                <el-tag size="small" type="info">{{ msg.token_usage.total_tokens }} tokens</el-tag>
                <el-tag size="small" type="success">{{ msg.model_name }}</el-tag>
              </div>
            </div>
          </div>

          <!-- 流式输出中的消息 -->
          <div v-if="streaming" class="message-item assistant">
            <div class="msg-avatar">
              <el-avatar :size="36" icon="Cpu" style="background: #409eff" />
            </div>
            <div class="msg-body">
              <div class="msg-header">
                <span class="msg-role">AI 助手</span>
                <el-tag v-if="toolCalling" size="small" type="warning">
                  <el-icon class="mr-1"><Loading /></el-icon>
                  工具执行中{{ currentToolName ? ': ' + currentToolName : '' }}
                </el-tag>
                <el-tag v-else size="small" type="warning">输出中</el-tag>
              </div>
              <!-- 有内容时显示气泡，无内容时只显示 loading 动画 -->
              <div v-if="streamContent" class="msg-content" v-html="renderContent(streamContent)" />
              <div v-else class="msg-loading">
                <span class="loading-dot"></span>
                <span class="loading-dot"></span>
                <span class="loading-dot"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- 输入区 -->
        <div class="chat-input-area">
          <!-- 工具选择栏 -->
          <div class="tool-select-bar" v-if="activeAgentId && agentTools.length > 0">
            <div class="tool-left">
              <el-icon class="tool-bar-icon"><Tools /></el-icon>
              <span class="tool-title">绑定工具</span>
              <span class="tool-badge" v-if="selectedToolIds.length > 0">
                {{ selectedToolIds.length }}/{{ agentTools.length }}
              </span>
              <span class="tool-hint" v-else>（勾选后 AI 才能调用）</span>
            </div>
            <div class="tool-right">
              <el-select
                v-model="selectedToolIds"
                multiple
                collapse-tags
                collapse-tags-tooltip
                placeholder="选择工具..."
                size="small"
                class="tool-select"
              >
                <el-option
                  v-for="tool in agentTools"
                  :key="tool.id"
                  :label="tool.name"
                  :value="tool.id"
                >
                  <div class="tool-option-item">
                    <span class="tool-option-name">{{ tool.name }}</span>
                    <span class="tool-option-desc">{{ tool.description }}</span>
                  </div>
                </el-option>
              </el-select>
              <el-button
                v-if="selectedToolIds.length < agentTools.length"
                type="primary"
                link
                size="small"
                @click="selectedToolIds = agentTools.map(t => t.id)"
              >
                全选
              </el-button>
              <el-button
                v-if="selectedToolIds.length > 0"
                type="danger"
                link
                size="small"
                @click="selectedToolIds = []"
              >
                清空
              </el-button>
            </div>
          </div>

          <el-input
            v-model="inputText"
            type="textarea"
            :rows="3"
            placeholder="输入消息，Enter 发送，Shift+Enter 换行..."
            :disabled="sending || streaming"
            @keydown.enter.exact="handleSend"
          />
          <div class="input-actions">
            <span class="input-hint">Enter 发送 · Shift+Enter 换行</span>
            <el-button
              type="primary"
              :loading="sending || streaming"
              :disabled="!inputText.trim()"
              @click="handleSend"
            >
              <el-icon><Promotion /></el-icon> 发送
            </el-button>
          </div>
        </div>
      </template>
    </div>

    <!-- Agent 选择弹窗 -->
    <el-dialog v-model="showAgentSelector" title="选择 Agent" width="600px">
      <div v-loading="loadingAgents">
        <div
          v-for="agent in agentList"
          :key="agent.id"
          class="agent-card"
          @click="startNewConversation(agent)"
        >
          <div class="agent-card-icon">
            <el-icon :size="24"><Cpu /></el-icon>
          </div>
          <div class="agent-card-body">
            <div class="agent-card-name">{{ agent.name }}</div>
            <div class="agent-card-desc">{{ agent.description || agent.welcome_message || '暂无描述' }}</div>
            <div v-if="agent.tools?.length" class="agent-tools">
              <el-tag v-for="tool in agent.tools.slice(0, 3)" :key="tool.id" size="small" type="info">
                {{ tool.name }}
              </el-tag>
              <el-tag v-if="agent.tools.length > 3" size="small">+{{ agent.tools.length - 3 }}</el-tag>
            </div>
          </div>
          <el-icon><ArrowRight /></el-icon>
        </div>
        <el-empty v-if="agentList.length === 0" description="暂无可用 Agent" />
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus, Delete, Promotion, ArrowRight, ChatDotRound, Cpu, RefreshRight,
  CopyDocument, Tools, Setting, Loading
} from '@element-plus/icons-vue'
import request from '@/utils/http'
import { useUserStore } from '@/store/modules/user'
import {
  fetchGetConversations, fetchCreateConversation, fetchDeleteConversation,
  fetchGetMessages, fetchGetPublicAiAgents, fetchGetAgentTools,
  fetchSendMessageStream
} from '@/api/ai-manage'

const conversations = ref<any[]>([])
const activeConvId = ref<number | null>(null)
const activeAgentId = ref<number | null>(null)
const messages = ref<any[]>([])
const inputText = ref('')
const sending = ref(false)
const streaming = ref(false)
const streamContent = ref('')
const toolCalling = ref(false)
const currentToolName = ref('')
const msgContainerRef = ref()
const showAgentSelector = ref(false)
const agentList = ref<any[]>([])
const loadingAgents = ref(false)

// 发送时的工具选择
const agentTools = ref<any[]>([])
const selectedToolIds = ref<number[]>([]) // 勾选的工具才会被调用

onMounted(async () => {
  await loadConversations()
})

const loadConversations = async () => {
  try {
    const res: any = await fetchGetConversations({ page: 1, limit: 50 })
    conversations.value = res.list || []
  } catch { /* */ }
}

const loadAgents = async () => {
  loadingAgents.value = true
  try {
    const res: any = await fetchGetPublicAiAgents({ page: 1, limit: 50 })
    agentList.value = res.list || []
  } finally {
    loadingAgents.value = false
  }
}

const getRoleLabel = (role: string) => {
  const labels: Record<string, string> = {
    user: '我',
    assistant: 'AI 助手',
    system: '系统',
    tool: '工具返回'
  }
  return labels[role] || role
}

const formatToolArgs = (args: string | object | null) => {
  if (!args) return '无参数'
  if (typeof args === 'string') {
    try {
      const parsed = JSON.parse(args)
      return JSON.stringify(parsed, null, 2)
    } catch {
      return args
    }
  }
  return JSON.stringify(args, null, 2)
}

const formatToolResult = (content: string) => {
  try {
    const parsed = JSON.parse(content)
    return JSON.stringify(parsed, null, 2)
  } catch {
    return content
  }
}

const startNewConversation = async (agent: any) => {
  showAgentSelector.value = false
  try {
    const res: any = await fetchCreateConversation(agent.id)
    activeConvId.value = res.id
    activeAgentId.value = agent.id
    messages.value = []
    // 显示欢迎语
    if (agent.welcome_message) {
      messages.value.push({
        role: 'assistant',
        content: agent.welcome_message,
        token_usage: null
      })
    }
    // 建议问题
    if (agent.suggested_questions?.length) {
      messages.value.push({
        role: 'system',
        content: '💡 试试这些问题：\n' + agent.suggested_questions.map((q: string, i: number) => `${i + 1}. ${q}`).join('\n')
      })
    }

    // 加载该 Agent 的工具列表
    loadAgentTools(agent.id)

    await loadConversations()
    scrollToBottom()
  } catch { /* */ }
}

const switchConversation = async (conv: any) => {
  activeConvId.value = conv.id
  activeAgentId.value = conv.agent_id
  try {
    const res: any = await fetchGetMessages(conv.id)
    messages.value = res || []
    scrollToBottom()
  } catch { /* */ }

  // 加载该 Agent 的工具列表
  if (conv.agent_id) {
    loadAgentTools(conv.agent_id)
  }
}

const loadAgentTools = async (agentId: number) => {
  try {
    const res: any = await fetchGetAgentTools(agentId)
    if (Array.isArray(res)) {
      agentTools.value = res
      // 默认不勾选任何工具，需要用户手动选择
      selectedToolIds.value = []
    }
  } catch { /* */ }
}

const handleDeleteConv = async (conv: any) => {
  try {
    await ElMessageBox.confirm('确认删除该对话？', '提示', { type: 'warning' })
  } catch {
    // 用户取消删除
    return
  }

  try {
    await fetchDeleteConversation(conv.id)
    if (activeConvId.value === conv.id) {
      activeConvId.value = null
      messages.value = []
    }
    await loadConversations()
  } catch (err: any) {
    ElMessage.error(err.msg || err.message || '删除失败')
  }
}

const handleSend = async () => {
  const text = inputText.value.trim()
  if (!text || sending.value || streaming.value) return

  inputText.value = ''

  // 添加用户消息
  messages.value.push({ role: 'user', content: text })
  scrollToBottom()

  // 第一条消息自动更新标题
  const isFirstMessage = messages.value.filter((m: any) => m.role === 'user').length === 1

  sending.value = true
  streaming.value = true
  streamContent.value = ''

  try {
    await handleSendMessage(text)

    // 重新加载对话列表（更新标题和排序）
    if (isFirstMessage) {
      await loadConversations()
    }
  } catch (e: any) {
    // 移除未成功的用户消息
    messages.value.pop()
  }
}

const scrollToBottom = () => {
  nextTick(() => {
    const el = msgContainerRef.value
    if (el) {
      el.scrollTop = el.scrollHeight
    }
  })
}

// 简单 Markdown 渲染
const renderContent = (content: string) => {
  let html = content
    // 转义 HTML 特殊字符
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

  // 代码块
  html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, (_, lang, code) => {
    const langLabel = lang ? `<span class="code-lang">${lang}</span>` : ''
    return `<div class="code-wrapper">${langLabel}<pre><code>${code.trim()}</code></pre></div>`
  })

  // 行内代码
  html = html.replace(/`([^`]+)`/g, '<code class="inline-code">$1</code>')

  // 标题
  html = html.replace(/^### (.*?)$/gm, '<h3>$1</h3>')
  html = html.replace(/^## (.*?)$/gm, '<h2>$1</h2>')
  html = html.replace(/^# (.*?)$/gm, '<h1>$1</h1>')

  // 粗体
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

  // 斜体
  html = html.replace(/\*(.*?)\*/g, '<em>$1</em>')

  // 列表
  html = html.replace(/^- (.*?)$/gm, '<li>$1</li>')
  html = html.replace(/^\d+\. (.*?)$/gm, '<li>$1</li>')

  // 链接
  html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')

  // 换行
  html = html.replace(/\n/g, '<br>')

  return html
}

// 复制消息内容
const copyContent = async (content: string) => {
  try {
    await navigator.clipboard.writeText(content)
    ElMessage.success('已复制到剪贴板')
  } catch (e) {
    ElMessage.error('复制失败')
  }
}

// 标记是否为重新生成（影响消息加载策略）
let isRegenerating = false

// 重新生成消息
const regenerateMessage = (index: number) => {
  // 找到上一条用户消息
  const userMsgIndex = findLastUserMessageIndex(index)
  if (userMsgIndex === -1) return

  const userMsg = messages.value[userMsgIndex]
  if (!userMsg) return

  // 删除 AI 回复（包括流式输出中的消息）
  messages.value.splice(index)

  isRegenerating = true

  // 重新发送
  sending.value = true
  streaming.value = true
  handleSendMessage(userMsg.content)
}

// 找到最后一条用户消息的索引
const findLastUserMessageIndex = (currentIndex: number): number => {
  for (let i = currentIndex - 1; i >= 0; i--) {
    if (messages.value[i]?.role === 'user') {
      return i
    }
  }
  return -1
}

// 提取出实际的发送逻辑，使用 SSE 流式
const handleSendMessage = async (text: string) => {
  try {
    // 第一个消息时 conversation_id 可能为 null，不传让后端自动创建
    const postData: any = { content: text }
    if (activeConvId.value) {
      postData.conversation_id = activeConvId.value
    } else {
      postData.agent_id = activeAgentId.value
    }

    // 只传用户勾选的工具，没勾选就传空数组（不使用任何工具）
    if (selectedToolIds.value.length > 0) {
      postData.tool_ids = selectedToolIds.value
    } else {
      // 没有选择任何工具，传空数组表示不使用工具
      postData.tool_ids = []
    }

    // 使用流式 SSE 请求
    const response = await fetchSendMessageStream(postData)
    const reader = response.body?.getReader()
    const decoder = new TextDecoder()

    if (!reader) {
      throw new Error('无法读取响应流')
    }

    let buffer = ''
    let currentContent = ''
    let done = false

    while (!done) {
      const { value, done: isDone } = await reader.read()
      done = isDone
      if (value) {
        buffer += decoder.decode(value, { stream: !done })

        // 处理 SSE 数据块
        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          const trimmed = line.trim()
          if (!trimmed || !trimmed.startsWith('data: ')) continue

          const dataStr = trimmed.slice(6)
          if (dataStr === '[DONE]') {
            done = true
            break
          }

          try {
            const data = JSON.parse(dataStr)

            if (data.type === 'content') {
              // 内容片段
              currentContent += data.data
              streamContent.value = currentContent
              scrollToBottom()
            } else if (data.type === 'tool_call_start') {
              // 开始调用工具
              toolCalling.value = true
            } else if (data.type === 'tool_call_end') {
              // 工具调用结束
              toolCalling.value = false
            } else if (data.type === 'tool_call_executing') {
              // 工具执行中（显示工具名称）
              const toolData = data.data || {}
              currentToolName.value = toolData.name || ''
            } else if (data.type === 'done') {
              // 完成
              const doneData = data.data || {}

              // 如果有新的会话 ID，更新
              if (doneData.conversation_id && !activeConvId.value) {
                activeConvId.value = doneData.conversation_id
              }

              streamContent.value = ''
              currentContent = ''
            }
          } catch (e) {
            // JSON 解析失败，可能是不完整的块，继续累积
          }
        }
      }
    }

    // 重新加载消息列表（服务端是唯一可靠来源，包含工具调用/结果等）
    if (activeConvId.value) {
      try {
        const res: any = await fetchGetMessages(activeConvId.value)
        if (res && Array.isArray(res)) {
          if (isRegenerating) {
            // 重新生成：保留已有用户消息，仅替换最新一轮的 AI 回复
            isRegenerating = false
            const userMsgs = messages.value.filter((m: any) => m.role === 'user')
            const rounds = res.filter((m: any) => m.round_index).map((m: any) => m.round_index)
            const maxRound = rounds.length > 0 ? Math.max(...rounds) : 0
            const latestMsgs = res.filter((m: any) => m.round_index === maxRound && m.role !== 'user')
            messages.value = [...userMsgs, ...latestMsgs]
          } else {
            // 普通发送：直接使用服务端完整数据
            messages.value = res
          }
        }
      } catch {
        // 加载失败时保留已推送的消息
        isRegenerating = false
      }
    }

    scrollToBottom()
  } catch (e: any) {
    ElMessage.error('发送失败: ' + (e.message || '未知错误'))
  } finally {
    sending.value = false
    streaming.value = false
    streamContent.value = ''
    toolCalling.value = false
    currentToolName.value = ''
    isRegenerating = false
  }
}

// 流式发送请求包装
const fetchSendMessageStream = async (data: any) => {
  const { accessToken } = useUserStore()
  return fetch('/admin/ai/chat/stream', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${accessToken}`
    },
    body: JSON.stringify(data)
  })
}

const formatTime = (t: string) => {
  if (!t) return ''
  return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

// 监听 Agent 选择器
// 监听 Agent 选择器打开时加载列表
watch(showAgentSelector, (val) => {
  if (val && agentList.value.length === 0) {
    loadAgents()
  }
})
</script>

<style scoped lang="scss">
.ai-chat-page {
  display: flex;
  height: calc(100vh - 120px);
  background: var(--default-bg-color);
  border-radius: 8px;
  overflow: hidden;
  box-shadow: var(--el-box-shadow-light);
}

.chat-sidebar {
  width: 280px;
  border-right: 1px solid var(--el-border-color-lighter);
  display: flex;
  flex-direction: column;
  background: var(--default-box-color);
}

.sidebar-header {
  padding: 16px;
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.conversation-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.conv-item {
  display: flex;
  align-items: center;
  padding: 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
  margin-bottom: 4px;

  &:hover,
  &.active {
    background: var(--el-color-primary-light-9);
  }
}

.conv-info {
  flex: 1;
  min-width: 0;
}

.conv-title {
  display: block;
  font-size: 14px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conv-time {
  font-size: 12px;
  color: var(--art-gray-500);
}

.conv-delete {
  opacity: 0;
  transition: opacity 0.2s;
}

.conv-item:hover .conv-delete {
  opacity: 1;
}

.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.chat-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-content {
  text-align: center;
  color: var(--el-text-color-regular);

  .empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    color: var(--el-color-primary);
  }

  h2 {
    font-size: 20px;
    margin-bottom: 8px;
  }

  p {
    color: var(--el-text-color-secondary);
    margin-bottom: 20px;
  }
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden; /* 防止横向滚动 */
  padding: 24px 32px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  min-width: 0;
  box-sizing: border-box;
}

.message-item {
  display: flex;
  margin-bottom: 32px;
  animation: fadeIn 0.3s ease;
  min-width: 0;
  width: 100%;
  box-sizing: border-box;

  &.user {
    flex-direction: row-reverse;
  }

  &.tool {
    .msg-content {
      background: #f0f9ff;
      border: 1px dashed #0ea5e9;
      border-radius: 8px;
    }
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.msg-avatar {
  flex-shrink: 0;
  margin: 0 16px;
}

.msg-body {
  max-width: 75%;
  flex: 1;
  min-width: 0; /* 防止 flex 子元素溢出 */
}

.message-item {
  min-width: 0; /* 防止 flex 容器溢出 */
}

.msg-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.msg-role {
  font-size: 13px;
  font-weight: 600;
  color: var(--el-text-color-secondary);
  display: flex;
  align-items: center;
}

.ml-2 {
  margin-left: 8px;
}

.mr-1 {
  margin-right: 4px;
}

.msg-actions {
  opacity: 0;
  transition: opacity 0.2s;
}

.message-item:hover .msg-actions {
  opacity: 1;
}

/* 工具调用面板 */
.tool-calls-panel {
  margin-bottom: 12px;
}

.tool-call-item {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 8px;
  border-left: 4px solid #f59e0b;
  max-width: 100%;
  box-sizing: border-box;
  overflow: hidden;
}

.tool-call-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-weight: 600;
  color: #92400e;
  flex-wrap: wrap;
}

.tool-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.tool-call-args {
  max-width: 100%;
  overflow-x: auto;
}

.tool-call-args pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-all;
  font-size: 12px;
  background: rgba(255, 255, 255, 0.5);
  padding: 8px 12px;
  border-radius: 6px;
  max-height: 120px;
  overflow-y: auto;
}

.tool-name {
  font-family: 'Fira Code', 'Consolas', monospace;
  font-size: 14px;
}

.tool-call-args {
  background: rgba(255, 255, 255, 0.7);
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 12px;

  pre {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
    color: #78350f;
  }
}

/* 工具结果面板 */
/* 工具结果面板 */
.tool-result-panel {
  margin-top: 12px;
  max-width: 100%;
}

.tool-result-panel :deep(.el-collapse) {
  border: none;
}

.tool-result-panel :deep(.el-collapse-item) {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 0;
  overflow: hidden;
}

.tool-result-panel :deep(.el-collapse-item__header) {
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  padding: 10px 12px;
  background: #f1f5f9;
  border-bottom: none;
}

.tool-result-panel :deep(.el-collapse-item__wrap) {
  border: none;
}

.tool-result-panel :deep(.el-collapse-item__content) {
  padding: 0;
}

.tool-result-content {
  margin: 0;
  font-size: 12px;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 220px;
  overflow-y: auto;
  overflow-x: auto;
  background: #fefefe;
  padding: 12px;
  margin: 0;
  line-height: 1.6;
  color: #475569;
  border-top: 1px solid #e2e8f0;
}

/* 滚动条美化 */
.tool-result-content::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

.tool-result-content::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 3px;
}

.tool-result-content::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

.tool-result-content::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.msg-content {
  background: #ffffff;
  padding: 16px 24px 16px 28px;
  border-radius: 16px;
  line-height: 1.8;
  font-size: 15px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  color: #334155;
  max-width: 100%;
  overflow-x: auto;
  word-wrap: break-word;
  word-break: break-word;

  h1, h2, h3 {
    margin: 16px 0 8px;
    font-weight: 600;

    &:first-child {
      margin-top: 0;
    }
  }

  h1 {
    font-size: 20px;
  }

  h2 {
    font-size: 18px;
  }

  h3 {
    font-size: 16px;
  }

  p {
    margin: 8px 0;
  }

  ul, ol {
    margin: 16px 0 !important;
    padding-left: 16px !important;
    list-style: none !important;
  }

  li {
    position: relative !important;
    margin: 10px 0 !important;
    padding-left: 24px !important;

    &::before {
      content: '' !important;
      position: absolute !important;
      left: 4px !important;
      top: 10px !important;
      width: 6px !important;
      height: 6px !important;
      border-radius: 50% !important;
      background: #94a3b8 !important;
    }
  }

  a {
    color: var(--el-color-primary);
    text-decoration: underline;

    &:hover {
      opacity: 0.8;
    }
  }
}

/* 用户消息里的列表圆点用白色 */
.message-item.user .msg-content li::before {
  background: rgba(255, 255, 255, 0.8) !important;
}

.message-item.user .msg-content {
  background: linear-gradient(135deg, #409eff 0%, #66b1ff 100%) !important;
  color: #fff !important;
  box-shadow: 0 4px 16px rgba(64, 158, 255, 0.35) !important;
}

/* Loading 动画 */
.msg-loading {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 16px 20px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.loading-dot {
  width: 8px;
  height: 8px;
  background: #94a3b8;
  border-radius: 50%;
  animation: bounce 1.4s infinite ease-in-out both;

  &:nth-child(1) {
    animation-delay: -0.32s;
  }
  &:nth-child(2) {
    animation-delay: -0.16s;
  }
  &:nth-child(3) {
    animation-delay: 0s;
  }
}

@keyframes bounce {
  0%, 80%, 100% {
    transform: scale(0.8);
    opacity: 0.5;
  }
  40% {
    transform: scale(1.2);
    opacity: 1;
  }
}

.code-wrapper {
  margin: 16px 0;
  border-radius: 12px;
  overflow: hidden;
  background: #1e293b;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.code-lang {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: #0f172a;
  color: #94a3b8;
  font-size: 12px;
  font-family: 'Fira Code', 'Consolas', monospace;
  border-bottom: 1px solid #334155;
}

.code-wrapper pre {
  margin: 0;
  padding: 16px;
  overflow-x: auto;
}

.code-wrapper code {
  color: #e2e8f0;
  font-family: 'Fira Code', 'Consolas', monospace;
  font-size: 13px;
  line-height: 1.7;
}

.message-item.user .code-wrapper {
  background: rgba(255, 255, 255, 0.15);

  .code-lang {
    background: rgba(255, 255, 255, 0.1);
    border-bottom-color: rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.8);
  }

  code {
    color: rgba(255, 255, 255, 0.95);
  }
}

.inline-code {
  background: #f1f5f9;
  padding: 3px 8px;
  border-radius: 6px;
  font-family: 'Fira Code', 'Consolas', monospace;
  font-size: 0.85em;
  color: #ef4444;
  border: 1px solid #e2e8f0;
}

.message-item.user .inline-code {
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
  border-color: rgba(255, 255, 255, 0.3);
}

.msg-meta {
  margin-top: 8px;
  display: flex;
  gap: 6px;
}

.chat-input-area {
  padding: 16px 20px;
  border-top: 1px solid var(--el-border-color-lighter);
  background: var(--default-box-color);
}

.input-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
}

.input-hint {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.empty-content {
  text-align: center;

  h2 {
    margin: 16px 0 8px;
    font-size: 24px;
    font-weight: 600;
  }

  p {
    color: var(--art-gray-500);
    margin-bottom: 24px;
  }
}

.agent-tools {
  margin-top: 8px;
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.agent-card {
  display: flex;
  align-items: center;
  padding: 16px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 8px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.2s;
  gap: 16px;

  &:hover {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(64, 158, 255, 0.15);
  }
}

.agent-card-icon {
  width: 48px;
  height: 48px;
  background: var(--el-color-primary-light-9);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--el-color-primary);
  flex-shrink: 0;
}

.agent-card-body {
  flex: 1;
}

.agent-card-name {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 4px;
}

.agent-card-desc {
  font-size: 13px;
  color: var(--art-gray-500);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 400px;
}

/* 工具选择栏样式（一行布局） */
.tool-select-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  margin-bottom: 12px;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8f4ff 100%);
  border-radius: 10px;
  border: 1px solid rgba(64, 158, 255, 0.15);
  gap: 16px;
}

.tool-left {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.tool-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  justify-content: flex-end;
}

.tool-bar-icon {
  font-size: 16px;
  color: var(--el-color-primary);
}

.tool-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--el-text-color-primary);
  white-space: nowrap;
}

.tool-badge {
  background: var(--el-color-primary);
  color: #fff;
  font-size: 11px;
  padding: 1px 7px;
  border-radius: 8px;
  font-weight: 500;
}

.tool-hint {
  font-size: 11px;
  color: var(--el-text-color-secondary);
  font-style: italic;
  white-space: nowrap;
}

.tool-select {
  flex: 1;
  max-width: 350px;
  min-width: 150px;
}

.tool-option-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.tool-option-name {
  font-weight: 500;
  font-size: 13px;
  color: var(--el-text-color-primary);
}

.tool-option-desc {
  font-size: 11px;
  color: var(--el-text-color-secondary);
}
</style>
