<!-- AI 聊天侧边栏 -->
<template>
  <ElDrawer
    v-model="isDrawerVisible"
    :size="isMobile ? '100%' : '480px'"
    :with-header="false"
    :modal="false"
    :append-to-body="true"
    direction="rtl"
    class="ai-chat-drawer"
  >
    <div class="chat-wrapper">
      <div class="chat-container">
        <!-- 头部区域 -->
        <div class="chat-header">
          <div class="chat-header-left">
            <div class="chat-avatar">
              <ArtSvgIcon icon="ri:sparkles-fill" class="text-white" size="18" />
            </div>
            <div class="chat-header-info">
              <h3 class="chat-title">{{ currentAgent?.name || '智能助手' }}</h3>
              <span class="chat-subtitle">{{ currentAgent?.description || 'AI 驱动的智能对话' }}</span>
            </div>
          </div>
          <div class="chat-header-right">
            <ElTooltip content="历史对话" placement="bottom">
              <button class="icon-btn" @click="showConversationList = !showConversationList">
                <ArtSvgIcon icon="ri:history-line" size="18" />
              </button>
            </ElTooltip>
            <ElTooltip content="新建对话" placement="bottom">
              <button class="icon-btn" @click="createNewConversation">
                <ArtSvgIcon icon="ri:add-line" size="18" />
              </button>
            </ElTooltip>
            <ElTooltip content="选择助手" placement="bottom">
              <button class="icon-btn" @click="showAgentSelector = true">
                <ArtSvgIcon icon="ri:robot-2-line" size="18" />
              </button>
            </ElTooltip>
            <ElTooltip content="关闭" placement="bottom">
              <button class="icon-btn" @click="closeChat">
                <ArtSvgIcon icon="ri:close-line" size="18" />
              </button>
            </ElTooltip>
          </div>
        </div>

        <!-- 对话列表侧边栏（可折叠） -->
        <Transition name="slide-left">
          <div v-if="showConversationList" class="conversation-sidebar">
            <div class="sidebar-header">
              <div class="sidebar-header-left">
                <ArtSvgIcon icon="ri:history-line" class="sidebar-icon" />
                <span class="sidebar-title">历史对话</span>
              </div>
              <button class="icon-btn-sm" @click="showConversationList = false">
                <ArtSvgIcon icon="ri:close-line" size="14" />
              </button>
            </div>
            <div class="conversation-list" v-if="conversationList.length > 0">
              <div
                v-for="item in conversationList"
                :key="item.id"
                class="conversation-item"
                :class="{ active: currentConversation?.id === item.id }"
                @click="selectConversation(item)"
              >
                <ArtSvgIcon icon="ri:chat-3-line" class="conversation-icon" />
                <div class="item-content">
                  <span class="conversation-title">{{ truncate(item.title, 18) }}</span>
                  <span class="conversation-time">{{ formatRelativeDate(item.created_at) }}</span>
                </div>
                <button class="item-delete" @click.stop="deleteConversation(item)">
                  <ArtSvgIcon icon="ri:delete-bin-line" size="14" />
                </button>
              </div>
            </div>
            <div v-else class="empty-conversations">
              <ArtSvgIcon icon="ri:chat-smile-2-line" size="36" class="empty-icon" />
              <span class="empty-text">暂无对话记录</span>
              <span class="empty-hint">开始你的第一次对话吧</span>
            </div>
          </div>
        </Transition>

        <!-- 聊天消息区域 -->
        <div
          class="chat-messages"
          ref="messageContainer"
          @scroll="handleScroll"
        >
          <!-- 欢迎消息 -->
          <div v-if="messages.length === 0" class="welcome-section">
            <div class="welcome-icon">
              <ArtSvgIcon icon="ri:sparkles-fill" size="40" class="text-white" />
            </div>
            <h4 class="welcome-title">嗨，我是你的 AI 助手</h4>
            <p class="welcome-desc">有什么我可以帮你的吗？</p>
            <div class="suggestion-list">
              <div
                v-for="(item, index) in suggestedQuestions"
                :key="index"
                class="suggestion-item"
                @click="sendSuggestion(item.text)"
              >
                <div class="suggestion-icon" :style="{ background: item.color }">
                  <ArtSvgIcon :icon="item.icon" size="16" class="text-white" />
                </div>
                <div class="suggestion-content">
                  <span class="suggestion-text">{{ item.text }}</span>
                </div>
                <ArtSvgIcon icon="ri:arrow-right-line" size="14" class="suggestion-arrow" />
              </div>
            </div>
          </div>

          <!-- 消息列表 -->
          <div v-else class="messages-list">
            <template v-for="(message, index) in messages" :key="message.id || index">
              <!-- 日期分割线 -->
              <div
                v-if="shouldShowDate(index)"
                class="date-divider"
              >
                <span>{{ formatDate(message.created_at) }}</span>
              </div>

              <!-- 用户消息 -->
              <div
                v-if="message.role === 'user'"
                class="message-item message-user"
              >
                <div class="message-content">
                  <div class="message-bubble">
                    <MarkdownRenderer :content="message.content" />
                  </div>
                  <div class="message-meta">
                    <span class="message-time">{{ formatTime(message.created_at) }}</span>
                  </div>
                </div>
              </div>

              <!-- AI 消息 -->
              <div
                v-else-if="message.role === 'assistant'"
                class="message-item message-ai"
              >
                <div class="message-avatar ai-avatar">
                  <ArtSvgIcon icon="ri:sparkles-fill" class="text-white" size="14" />
                </div>
                <div class="message-content">
                  <div class="message-bubble">
                    <div v-if="message.isLoading && !message.content" class="typing-indicator">
                      <span class="typing-dot"></span>
                      <span class="typing-dot"></span>
                      <span class="typing-dot"></span>
                    </div>
                    <MarkdownRenderer v-else :content="message.content || ''" />
                  </div>
                  <div class="message-meta" v-if="!message.isLoading">
                    <span class="message-time">{{ formatTime(message.created_at) }}</span>
                    <div class="action-buttons">
                      <ElTooltip content="复制" placement="top">
                        <button class="message-action" @click="copyMessage(message.content)">
                          <ArtSvgIcon icon="ri:file-copy-line" size="14" />
                        </button>
                      </ElTooltip>
                      <ElTooltip content="重新生成" placement="top">
                        <button class="message-action" @click="regenerateMessage">
                          <ArtSvgIcon icon="ri:refresh-line" size="14" />
                        </button>
                      </ElTooltip>
                      <ElTooltip content="点赞" placement="top">
                        <button class="message-action" @click="thumbUpMessage">
                          <ArtSvgIcon icon="ri:thumb-up-line" size="14" />
                        </button>
                      </ElTooltip>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- 滚动到底部按钮 -->
          <Transition name="fade">
            <button
              v-show="showScrollButton"
              class="scroll-to-bottom"
              @click="scrollToBottom"
            >
              <ArtSvgIcon icon="ri:arrow-down-line" size="16" />
            </button>
          </Transition>
        </div>

        <!-- 底部输入区域 -->
        <div class="chat-input-area">
          <div class="input-card">
            <ElInput
              v-model="messageText"
              type="textarea"
              :autosize="{ minRows: 1, maxRows: 6 }"
              placeholder="输入消息... (Shift + Enter 换行)"
              resize="none"
              @keydown.enter.prevent="handleEnter"
              class="chat-input"
            />
            <div class="input-bottom">
              <div class="input-tools">
                <ElTooltip content="附件" placement="top">
                  <button class="tool-btn">
                    <ArtSvgIcon icon="ri:attachment-2" size="16" />
                  </button>
                </ElTooltip>
                <ElTooltip content="图片" placement="top">
                  <button class="tool-btn">
                    <ArtSvgIcon icon="ri:image-add-line" size="16" />
                  </button>
                </ElTooltip>
                <ElTooltip content="清空对话" placement="top">
                  <button class="tool-btn" @click="clearMessages">
                    <ArtSvgIcon icon="ri:eraser-line" size="16" />
                  </button>
                </ElTooltip>
              </div>
              <button
                class="send-btn"
                :class="{ disabled: !messageText.trim() || isSending }"
                @click="sendMessage"
                :disabled="!messageText.trim() || isSending"
              >
                <ArtSvgIcon
                  :icon="isSending ? 'ri:loader-4-line' : 'ri:send-plane-fill'"
                  size="16"
                  :class="{ 'animate-spin': isSending }"
                />
                <span>{{ isSending ? '发送中' : '发送' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 助手选择弹窗 -->
      <ElDialog
        v-model="showAgentSelector"
        title="选择智能助手"
        width="480px"
        :close-on-click-modal="true"
        class="agent-selector-dialog"
      >
        <div class="agent-list">
          <div
            v-for="agent in agentList"
            :key="agent.id"
            class="agent-item"
            :class="{ active: currentAgent?.id === agent.id }"
            @click="selectAgent(agent)"
          >
            <div class="agent-avatar" :style="{ background: agent.color || '#667eea' }">
              <ArtSvgIcon :icon="agent.icon || 'ri:robot-2-line'" size="20" class="text-white" />
            </div>
            <div class="agent-info">
              <div class="agent-name">{{ agent.name }}</div>
              <div class="agent-desc">{{ agent.description || '智能 AI 助手' }}</div>
            </div>
            <ElIcon v-if="currentAgent?.id === agent.id" class="check-icon">
              <Check />
            </ElIcon>
          </div>
        </div>
      </ElDialog>
    </div>
  </ElDrawer>
</template>

<script setup lang="ts">
  import './chat-drawer.scss'
  import { Check } from '@element-plus/icons-vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { mittBus } from '@/utils/sys'
  import MarkdownRenderer from '@/components/core/common/markdown-renderer/index.vue'
  import {
    fetchGetConversations,
    fetchCreateConversation,
    fetchDeleteConversation,
    fetchGetMessages,
    fetchSendMessageStream,
    fetchGetPublicAiAgents,
  } from '@/api/ai-manage'

  defineOptions({ name: 'ArtChatWindow' })

  // 类型定义
  interface Conversation {
    id: number
    title: string
    created_at: string
  }

  interface Message {
    id: number
    role: 'user' | 'assistant'
    content: string
    created_at: string
    isLoading?: boolean
  }

  interface Agent {
    id: number
    name: string
    description: string
    icon?: string
    color?: string
  }

  // 常量
  const MOBILE_BREAKPOINT = 640
  const SCROLL_DELAY = 100

  // 响应式布局
  const { width } = useWindowSize()
  const isMobile = computed(() => width.value < MOBILE_BREAKPOINT)

  // 组件状态
  const isDrawerVisible = ref(false)
  const showConversationList = ref(false)
  const showAgentSelector = ref(false)
  const showScrollButton = ref(false)
  const isSending = ref(false)

  // 数据状态
  const messageText = ref('')
  const messageContainer = ref<HTMLElement | null>(null)
  const conversationList = ref<Conversation[]>([])
  const messages = ref<Message[]>([])
  const agentList = ref<Agent[]>([])
  const currentConversation = ref<Conversation | null>(null)
  const currentAgent = ref<Agent | null>(null)
  const userAvatar = ref('')

  // 建议问题（带图标和颜色）
  const suggestedQuestions = [
    { text: '介绍一下系统主要功能', icon: 'ri:rocket-line', color: '#667eea' },
    { text: '帮我生成一段示例代码', icon: 'ri:code-s-slash-line', color: '#10b981' },
    { text: '解释一下 AI Agent 的工作原理', icon: 'ri:question-line', color: '#f59e0b' },
    { text: '推荐一些提升效率的技巧', icon: 'ri:lightbulb-line', color: '#ec4899' },
  ]

  // 工具函数
  const truncate = (text: string, length: number): string => {
    if (!text) return ''
    return text.length > length ? text.substring(0, length) + '...' : text
  }

  const formatTime = (dateStr?: string): string => {
    if (!dateStr) return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    const date = new Date(dateStr)
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }

  const formatDate = (dateStr?: string): string => {
    if (!dateStr) return '今天'
    const date = new Date(dateStr)
    const today = new Date()
    if (date.toDateString() === today.toDateString()) return '今天'
    const yesterday = new Date(today)
    yesterday.setDate(yesterday.getDate() - 1)
    if (date.toDateString() === yesterday.toDateString()) return '昨天'
    return date.toLocaleDateString('zh-CN', { month: 'long', day: 'numeric' })
  }

  const formatRelativeDate = (dateStr?: string): string => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    const now = Date.now()
    const diff = now - date.getTime()
    if (diff < 60_000) return '刚刚'
    if (diff < 3_600_000) return `${Math.floor(diff / 60_000)}分钟前`
    if (diff < 86_400_000) return `${Math.floor(diff / 3_600_000)}小时前`
    if (diff < 604_800_000) return `${Math.floor(diff / 86_400_000)}天前`
    return date.toLocaleDateString('zh-CN', { month: '2-digit', day: '2-digit' })
  }

  const isDifferentDay = (prev?: string, curr?: string): boolean => {
    if (!prev || !curr) return true
    return new Date(prev).toDateString() !== new Date(curr).toDateString()
  }

  // 是否显示日期分割线
  const shouldShowDate = (index: number): boolean => {
    if (index === 0) return !!messages.value[0]?.created_at
    const prev = messages.value[index - 1]?.created_at
    const curr = messages.value[index]?.created_at
    if (!prev || !curr) return false
    return isDifferentDay(prev, curr)
  }

  // 滚动处理
  const scrollToBottom = (smooth = true): void => {
    nextTick(() => {
      setTimeout(() => {
        if (messageContainer.value) {
          messageContainer.value.scrollTo({
            top: messageContainer.value.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
          })
        }
      }, SCROLL_DELAY)
    })
  }

  const handleScroll = (e: Event): void => {
    const target = e.target as HTMLElement
    showScrollButton.value = target.scrollTop < target.scrollHeight - target.clientHeight - 100
  }

  // 复制消息
  const copyMessage = async (content: string): Promise<void> => {
    try {
      await navigator.clipboard.writeText(content)
      ElMessage.success('已复制到剪贴板')
    } catch {
      ElMessage.error('复制失败')
    }
  }

  // 重新生成
  const regenerateMessage = (): void => {
    // 找到最后一条用户消息
    const lastUserMessageIndex = [...messages.value].reverse().findIndex(m => m.role === 'user')
    if (lastUserMessageIndex === -1) return

    const actualIndex = messages.value.length - 1 - lastUserMessageIndex
    const userMessage = messages.value[actualIndex]

    // 移除包括这条之后的所有消息
    messages.value = messages.value.slice(0, actualIndex)

    // 重新发送
    sendUserMessage(userMessage.content)
  }

  // 点赞消息
  const thumbUpMessage = (): void => {
    ElMessage.success('感谢你的反馈 👍')
  }

  // 清空消息
  const clearMessages = (): void => {
    if (messages.value.length === 0) return
    ElMessageBox.confirm('确定要清空当前对话吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }).then(() => {
      messages.value = []
      currentConversation.value = null
      ElMessage.success('已清空对话')
    }).catch(() => {})
  }

  // 删除对话
  const deleteConversation = async (item: Conversation): Promise<void> => {
    try {
      await ElMessageBox.confirm(`确定要删除对话「${item.title}」吗？`, '提示', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'warning'
      })
      await fetchDeleteConversation(item.id)
      conversationList.value = conversationList.value.filter(c => c.id !== item.id)
      // 如果删除的是当前对话，清空消息
      if (currentConversation.value?.id === item.id) {
        currentConversation.value = null
        messages.value = []
      }
      ElMessage.success('已删除')
    } catch {
      // 取消或失败
    }
  }

  // 发送建议问题
  const sendSuggestion = (question: string): void => {
    messageText.value = question
    sendMessage()
  }

  // 处理 Enter 键
  const handleEnter = (e: KeyboardEvent): void => {
    if (e.shiftKey) {
      return
    }
    sendMessage()
  }

  // 加载对话列表
  const loadConversations = async (): Promise<void> => {
    try {
      const res: any = await fetchGetConversations({ limit: 50 })
      // 兼容多种返回格式
      const list = res?.list || res?.data || (Array.isArray(res) ? res : [])
      conversationList.value = list
    } catch (e) {
      console.error('加载对话列表失败', e)
    }
  }

  // 加载助手列表
  const loadAgents = async (): Promise<void> => {
    try {
      const res = await fetchGetPublicAiAgents({ limit: 20 })
      agentList.value = (res as any).list || [
        { id: 0, name: '通用助手', description: '智能回答各种问题', icon: 'ri:robot-2-line', color: '#409eff' }
      ]
      if (agentList.value.length > 0 && !currentAgent.value) {
        currentAgent.value = agentList.value[0]
      }
    } catch {
      agentList.value = [
        { id: 0, name: '通用助手', description: '智能回答各种问题', icon: 'ri:robot-2-line', color: '#409eff' }
      ]
      currentAgent.value = agentList.value[0]
    }
  }

  // 选择对话
  const selectConversation = async (conversation: Conversation): Promise<void> => {
    currentConversation.value = conversation
    showConversationList.value = false
    messages.value = []
    try {
      const res: any = await fetchGetMessages(conversation.id)
      // 后端直接返回数组
      const rawList = Array.isArray(res) ? res : (res?.list || res?.data || [])

      // 过滤出仅 user 和 assistant 的消息，并标准化字段
      const filtered = rawList
        .filter((m: any) => m.role === 'user' || m.role === 'assistant')
        .filter((m: any) => m.content && String(m.content).trim() !== '')
        .map((m: any, idx: number) => ({
          id: m.id || Date.now() + idx,
          role: m.role,
          content: String(m.content || ''),
          created_at: m.created_at || m.create_time || m.createdAt || new Date().toISOString(),
          isLoading: false,
        }))

      messages.value = filtered
    } catch (e) {
      console.error('加载消息失败', e)
      ElMessage.error('加载历史消息失败')
    }
    nextTick(() => {
      scrollToBottom(false)
    })
  }

  // 选择助手
  const selectAgent = (agent: Agent): void => {
    currentAgent.value = agent
    showAgentSelector.value = false
    // 如果有当前对话，切换助手时创建新对话
    if (messages.value.length > 0) {
      createNewConversation()
    }
  }

  // 创建新对话
  const createNewConversation = async (): Promise<void> => {
    messages.value = []
    currentConversation.value = null
    showConversationList.value = false
    scrollToBottom()
  }

  // 发送用户消息
  const sendUserMessage = async (content: string): Promise<void> => {
    if (!content.trim()) return

    isSending.value = true

    // 添加用户消息
    const userMessage: Message = {
      id: Date.now(),
      role: 'user',
      content: content,
      created_at: new Date().toISOString(),
    }
    messages.value.push(userMessage)
    scrollToBottom()

    // 添加 AI 加载状态消息
    const aiLoadingMessage: Message = {
      id: Date.now() + 1,
      role: 'assistant',
      content: '',
      created_at: new Date().toISOString(),
      isLoading: true,
    }
    messages.value.push(aiLoadingMessage)
    scrollToBottom()

    try {
      // 调用流式接口
      const response = await fetchSendMessageStream({
        conversation_id: currentConversation.value?.id,
        agent_id: currentAgent.value?.id || 0,
        content: content,
      })

      if (!response.ok) {
        throw new Error('请求失败')
      }

      const reader = response.body?.getReader()
      const decoder = new TextDecoder()
      let aiResponse = ''
      let buffer = ''
      let receivedConvId: number | null = null

      if (reader) {
        let done = false
        while (!done) {
          const { value, done: isDone } = await reader.read()
          done = isDone

          if (value) {
            buffer += decoder.decode(value, { stream: !done })

            // 按行解析 SSE 数据
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

                // 后端格式：{ type: 'content' | 'tool_call_start' | 'done' | 'error', data: ... }
                if (data.type === 'content' && data.data) {
                  aiResponse += data.data
                  const lastMessage = messages.value[messages.value.length - 1]
                  if (lastMessage && lastMessage.role === 'assistant') {
                    lastMessage.content = aiResponse
                    lastMessage.isLoading = false
                  }
                  scrollToBottom()
                } else if (data.type === 'done') {
                  // 完成事件
                  if (data.conversation_id) {
                    receivedConvId = Number(data.conversation_id)
                  }
                } else if (data.type === 'error') {
                  throw new Error(data.message || data.data || 'AI 返回错误')
                } else if (data.type === 'tool_call_start' || data.type === 'tool_call') {
                  // 工具调用提示（可选展示）
                  // 这里暂不显示，让回复继续
                } else if (data.conversation_id && !receivedConvId) {
                  // 兼容：旧格式可能直接附带 conversation_id
                  receivedConvId = Number(data.conversation_id)
                }
              } catch (parseError) {
                // 单条 SSE 解析失败不影响整体
                console.warn('SSE 数据解析失败:', dataStr, parseError)
              }
            }
          }
        }
      }

      // 流结束：移除加载状态，更新会话
      const lastMessage = messages.value[messages.value.length - 1]
      if (lastMessage && lastMessage.role === 'assistant') {
        lastMessage.isLoading = false
        // 如果没有任何内容，显示错误提示
        if (!lastMessage.content) {
          lastMessage.content = '(无回复内容)'
        }
      }

      // 如果是新建会话，记录新的 conversation_id
      if (receivedConvId && !currentConversation.value) {
        currentConversation.value = {
          id: receivedConvId,
          title: content.substring(0, 30),
          created_at: new Date().toISOString(),
        }
        // 刷新对话列表
        loadConversations()
      }

    } catch (error: any) {
      console.error('发送消息失败:', error)
      // 移除加载消息
      const lastMessage = messages.value[messages.value.length - 1]
      if (lastMessage && lastMessage.role === 'assistant' && !lastMessage.content) {
        messages.value.pop()
      } else if (lastMessage && lastMessage.role === 'assistant') {
        lastMessage.isLoading = false
      }
      ElMessage.error(error?.message || '消息发送失败，请重试')
    } finally {
      isSending.value = false
    }
  }

  // 发送消息
  const sendMessage = (): void => {
    const text = messageText.value.trim()
    if (!text) return

    messageText.value = ''
    sendUserMessage(text)
  }

  // 聊天窗口控制方法
  const openChat = (): void => {
    isDrawerVisible.value = true
    nextTick(() => {
      scrollToBottom(false)
    })
    // 加载数据
    if (conversationList.value.length === 0) {
      loadConversations()
    }
    if (agentList.value.length === 0) {
      loadAgents()
    }
  }

  const closeChat = (): void => {
    isDrawerVisible.value = false
  }

  // 生命周期
  onMounted(() => {
    mittBus.on('openChat', openChat)
  })

  onUnmounted(() => {
    mittBus.off('openChat', openChat)
  })
</script>

<style scoped lang="scss">
  // ============ 容器 ============
  .chat-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: var(--el-bg-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow:
      0 8px 32px rgba(0, 0, 0, 0.08),
      0 2px 8px rgba(0, 0, 0, 0.04);
    position: relative;
  }

  @media (max-width: 640px) {
    .chat-container {
      border-radius: 0;
      box-shadow: none;
    }
  }

  // ============ 头部 ============
  .chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: var(--el-bg-color);
    border-bottom: 1px solid var(--el-border-color-lighter);
    flex-shrink: 0;
    gap: 8px;
  }

  .chat-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex: 1;
  }

  .chat-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    flex-shrink: 0;
  }

  .chat-header-info {
    min-width: 0;
    flex: 1;

    .chat-title {
      margin: 0;
      font-size: 14px;
      font-weight: 600;
      color: var(--el-text-color-primary);
      line-height: 1.4;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .chat-subtitle {
      display: block;
      font-size: 11px;
      color: var(--el-text-color-secondary);
      line-height: 1.4;
      margin-top: 2px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }

  .chat-header-right {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
  }

  // ============ 通用图标按钮 ============
  .icon-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--el-text-color-regular);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;

    &:hover {
      background: var(--el-fill-color-light);
      color: var(--el-color-primary);
    }
  }

  .icon-btn-sm {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--el-text-color-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;

    &:hover {
      background: var(--el-fill-color);
      color: var(--el-text-color-primary);
    }
  }

  // ============ 历史对话侧边栏 ============
  .conversation-sidebar {
    position: absolute;
    top: 65px;
    left: 0;
    bottom: 0;
    width: 260px;
    background: var(--el-bg-color);
    border-right: 1px solid var(--el-border-color-lighter);
    z-index: 10;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 12px rgba(0, 0, 0, 0.04);
  }

  .sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-bottom: 1px solid var(--el-border-color-lighter);

    .sidebar-header-left {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .sidebar-icon {
      font-size: 16px;
      color: var(--el-color-primary);
    }

    .sidebar-title {
      font-size: 13px;
      font-weight: 600;
      color: var(--el-text-color-primary);
    }
  }

  .conversation-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;

    &::-webkit-scrollbar {
      width: 4px;
    }

    &::-webkit-scrollbar-thumb {
      background: var(--el-border-color-lighter);
      border-radius: 2px;

      &:hover {
        background: var(--el-border-color);
      }
    }
  }

  .conversation-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    margin-bottom: 2px;
    position: relative;

    &:hover {
      background: var(--el-fill-color-light);

      .item-delete {
        opacity: 1;
      }
    }

    &.active {
      background: var(--el-color-primary-light-9);

      .conversation-icon {
        color: var(--el-color-primary);
      }

      .conversation-title {
        color: var(--el-color-primary);
        font-weight: 500;
      }
    }

    .conversation-icon {
      font-size: 16px;
      color: var(--el-text-color-secondary);
      flex-shrink: 0;
    }

    .item-content {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .conversation-title {
      font-size: 13px;
      color: var(--el-text-color-primary);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      line-height: 1.4;
    }

    .conversation-time {
      font-size: 11px;
      color: var(--el-text-color-secondary);
      line-height: 1;
    }

    .item-delete {
      width: 22px;
      height: 22px;
      border: none;
      background: transparent;
      color: var(--el-text-color-secondary);
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: all 0.15s ease;
      padding: 0;

      &:hover {
        background: var(--el-color-danger-light-9);
        color: var(--el-color-danger);
      }
    }
  }

  .empty-conversations {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 40px 20px;

    .empty-icon {
      color: var(--el-text-color-placeholder);
      opacity: 0.5;
      margin-bottom: 4px;
    }

    .empty-text {
      font-size: 13px;
      font-weight: 500;
      color: var(--el-text-color-regular);
    }

    .empty-hint {
      font-size: 11px;
      color: var(--el-text-color-secondary);
    }
  }

  // ============ 消息区域 ============
  .chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px 16px;
    position: relative;
    background: var(--el-bg-color);

    &::-webkit-scrollbar {
      width: 4px;
    }

    &::-webkit-scrollbar-thumb {
      background: var(--el-border-color-lighter);
      border-radius: 2px;

      &:hover {
        background: var(--el-border-color);
      }
    }
  }

  // ============ 欢迎区域 ============
  .welcome-section {
    text-align: center;
    padding: 32px 8px;
  }

  .welcome-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
    position: relative;

    &::before {
      content: '';
      position: absolute;
      inset: -6px;
      border-radius: 26px;
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
      z-index: -1;
      filter: blur(12px);
    }
  }

  .welcome-title {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    letter-spacing: -0.01em;
  }

  .welcome-desc {
    margin: 0 0 24px;
    font-size: 13px;
    color: var(--el-text-color-secondary);
  }

  .suggestion-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .suggestion-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;

    &:hover {
      border-color: var(--el-color-primary-light-5);
      background: var(--el-color-primary-light-9);
      transform: translateX(2px);
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.08);

      .suggestion-arrow {
        color: var(--el-color-primary);
        transform: translateX(2px);
      }
    }

    .suggestion-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }

    .suggestion-content {
      flex: 1;
      min-width: 0;
    }

    .suggestion-text {
      font-size: 13px;
      color: var(--el-text-color-primary);
      line-height: 1.4;
    }

    .suggestion-arrow {
      color: var(--el-text-color-secondary);
      flex-shrink: 0;
      transition: all 0.2s ease;
    }
  }

  // ============ 消息列表 ============
  .messages-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .date-divider {
    text-align: center;
    margin: 16px 0 8px;
    position: relative;

    span {
      display: inline-block;
      padding: 2px 12px;
      background: var(--el-fill-color-light);
      color: var(--el-text-color-secondary);
      font-size: 11px;
      border-radius: 12px;
    }
  }

  .message-item {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
    animation: messageIn 0.3s ease;
  }

  @keyframes messageIn {
    from {
      opacity: 0;
      transform: translateY(8px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .message-avatar {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 8px;
  }

  .ai-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
  }

  .message-content {
    flex: 1;
    min-width: 0;
    max-width: calc(100% - 42px);
  }

  .message-bubble {
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13.5px;
    line-height: 1.65;
    word-break: break-word;
    max-width: 100%;
  }

  .message-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
    min-height: 20px;
  }

  .message-time {
    font-size: 11px;
    color: var(--el-text-color-secondary);
  }

  // 用户消息
  .message-user {
    flex-direction: row-reverse;

    .message-content {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      max-width: calc(100% - 12px);
    }

    .message-bubble {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-bottom-right-radius: 4px;
      box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);

      :deep(p) {
        color: white;
      }

      :deep(code) {
        background: rgba(255, 255, 255, 0.2);
        color: white;
      }
    }
  }

  // AI 消息
  .message-ai {
    .message-bubble {
      background: var(--el-fill-color-light);
      color: var(--el-text-color-regular);
      border-bottom-left-radius: 4px;
    }

    .action-buttons {
      display: flex;
      gap: 2px;
      opacity: 0;
      transition: opacity 0.2s;
    }

    &:hover .action-buttons {
      opacity: 1;
    }
  }

  .message-action {
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    border-radius: 4px;
    color: var(--el-text-color-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    padding: 0;

    &:hover {
      background: var(--el-fill-color);
      color: var(--el-color-primary);
    }
  }

  // ============ 打字动画 ============
  .typing-indicator {
    display: inline-flex;
    gap: 4px;
    padding: 2px 0;
    align-items: center;

    .typing-dot {
      width: 7px;
      height: 7px;
      background: var(--el-color-primary);
      border-radius: 50%;
      animation: typing 1.4s infinite ease-in-out;
      opacity: 0.5;

      &:nth-child(1) { animation-delay: 0s; }
      &:nth-child(2) { animation-delay: 0.2s; }
      &:nth-child(3) { animation-delay: 0.4s; }
    }
  }

  @keyframes typing {
    0%, 60%, 100% {
      transform: translateY(0);
      opacity: 0.5;
    }
    30% {
      transform: translateY(-6px);
      opacity: 1;
    }
  }

  // ============ 滚动到底部按钮 ============
  .scroll-to-bottom {
    position: sticky;
    bottom: 12px;
    left: 50%;
    transform: translateX(calc(100% + 50px));
    margin-left: 50%;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--el-bg-color);
    border: 1px solid var(--el-border-color-lighter);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: all 0.2s ease;
    color: var(--el-text-color-regular);
    padding: 0;

    &:hover {
      background: var(--el-color-primary);
      color: white;
      border-color: var(--el-color-primary);
    }
  }

  // ============ 输入区域 ============
  .chat-input-area {
    padding: 12px 16px 16px;
    background: var(--el-bg-color);
    border-top: 1px solid var(--el-border-color-lighter);
    flex-shrink: 0;
  }

  .input-card {
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 12px;
    transition: all 0.2s ease;
    overflow: hidden;

    &:focus-within {
      border-color: var(--el-color-primary-light-5);
      background: var(--el-bg-color);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.08);
    }
  }

  .chat-input {
    :deep(.el-textarea__inner) {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      padding: 12px 14px 0 !important;
      font-size: 13.5px;
      line-height: 1.6;
      resize: none;

      &:focus {
        box-shadow: none !important;
      }
    }
  }

  .input-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 8px 8px;
  }

  .input-tools {
    display: flex;
    gap: 2px;
  }

  .tool-btn {
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    color: var(--el-text-color-secondary);
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    padding: 0;

    &:hover {
      background: var(--el-fill-color);
      color: var(--el-color-primary);
    }
  }

  .send-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    height: 30px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 12.5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);

    &:hover:not(.disabled) {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    &.disabled {
      background: var(--el-fill-color-dark);
      color: var(--el-text-color-placeholder);
      cursor: not-allowed;
      box-shadow: none;
    }
  }

  .animate-spin {
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }

  // ============ 助手选择弹窗 ============
  :deep(.agent-selector-dialog) {
    .el-dialog {
      border-radius: 16px;
      overflow: hidden;
    }

    .el-dialog__header {
      padding: 18px 20px 14px;
      border-bottom: 1px solid var(--el-border-color-lighter);
    }

    .el-dialog__body {
      padding: 16px 20px;
    }
  }

  .agent-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 480px;
    overflow-y: auto;
  }

  .agent-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;

    &:hover {
      background: var(--el-fill-color-light);
    }

    &.active {
      background: var(--el-color-primary-light-9);
      border-color: var(--el-color-primary-light-5);
    }

    .agent-avatar {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }

    .agent-info {
      flex: 1;
      min-width: 0;

      .agent-name {
        font-size: 13.5px;
        font-weight: 600;
        color: var(--el-text-color-primary);
        margin-bottom: 2px;
      }

      .agent-desc {
        font-size: 12px;
        color: var(--el-text-color-secondary);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }

    .check-icon {
      color: var(--el-color-primary);
      font-size: 18px;
      flex-shrink: 0;
    }
  }

  // ============ 过渡动画 ============
  .slide-left-enter-active,
  .slide-left-leave-active {
    transition: transform 0.25s ease, opacity 0.25s ease;
  }

  .slide-left-enter-from,
  .slide-left-leave-to {
    transform: translateX(-100%);
    opacity: 0;
  }

  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.2s ease;
  }

  .fade-enter-from,
  .fade-leave-to {
    opacity: 0;
  }
</style>

