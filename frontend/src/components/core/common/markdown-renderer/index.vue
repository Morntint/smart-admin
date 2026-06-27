<!-- Markdown 渲染组件 -->
<template>
  <div class="markdown-renderer" v-html="renderedHtml"></div>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { marked } from 'marked'

  const props = defineProps<{
    content: string | undefined | null
  }>()

  // 配置 marked
  marked.setOptions({
    breaks: true,
    gfm: true,
  })

  const renderedHtml = computed(() => {
    const content = props.content
    if (!content || typeof content !== 'string') return ''
    try {
      return marked.parse(content) as string
    } catch (e) {
      console.warn('Markdown 渲染失败', e)
      // 出错时返回纯文本（带换行处理）
      return content.replace(/\n/g, '<br>')
    }
  })
</script>

<style scoped lang="scss">
  .markdown-renderer {
    font-size: 14px;
    line-height: 1.7;
    color: var(--el-text-color-regular);
    word-break: break-word;

    :deep(h1),
    :deep(h2),
    :deep(h3),
    :deep(h4),
    :deep(h5),
    :deep(h6) {
      margin: 16px 0 8px;
      font-weight: 600;
      color: var(--el-text-color-primary);
      line-height: 1.4;
    }

    :deep(h1) {
      font-size: 22px;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--el-border-color-lighter);
    }

    :deep(h2) {
      font-size: 18px;
      padding-bottom: 6px;
      border-bottom: 1px solid var(--el-border-color-lighter);
    }

    :deep(h3) {
      font-size: 16px;
    }

    :deep(h4) {
      font-size: 15px;
    }

    :deep(p) {
      margin: 0 0 12px;

      &:last-child {
        margin-bottom: 0;
      }
    }

    :deep(ul),
    :deep(ol) {
      margin: 8px 0;
      padding-left: 24px;

      li {
        margin: 4px 0;
        line-height: 1.7;
      }
    }

    :deep(ul) {
      list-style-type: disc;
    }

    :deep(ol) {
      list-style-type: decimal;
    }

    :deep(blockquote) {
      margin: 12px 0;
      padding: 10px 16px;
      border-left: 4px solid var(--el-color-primary);
      background: var(--el-fill-color-lighter);
      border-radius: 0 8px 8px 0;
      color: var(--el-text-color-secondary);

      p {
        margin: 0;
      }
    }

    :deep(a) {
      color: var(--el-color-primary);
      text-decoration: none;
      transition: opacity 0.2s;

      &:hover {
        opacity: 0.8;
        text-decoration: underline;
      }
    }

    :deep(code) {
      padding: 2px 6px;
      background: var(--el-fill-color-light);
      border-radius: 4px;
      font-size: 13px;
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      color: var(--el-color-danger);
    }

    :deep(pre) {
      margin: 12px 0;
      padding: 14px 16px;
      background: #1e1e1e;
      border-radius: 8px;
      overflow-x: auto;
      position: relative;

      code {
        padding: 0;
        background: transparent;
        color: #d4d4d4;
        font-size: 13px;
        line-height: 1.6;
      }
    }

    :deep(table) {
      width: 100%;
      margin: 12px 0;
      border-collapse: collapse;
      border: 1px solid var(--el-border-color-lighter);
      border-radius: 8px;
      overflow: hidden;

      th,
      td {
        padding: 10px 14px;
        border: 1px solid var(--el-border-color-lighter);
        text-align: left;
      }

      th {
        background: var(--el-fill-color-lighter);
        font-weight: 600;
        color: var(--el-text-color-primary);
      }

      tr:nth-child(even) {
        background: var(--el-fill-color-light);
      }
    }

    :deep(hr) {
      margin: 16px 0;
      border: none;
      border-top: 1px solid var(--el-border-color-lighter);
    }

    :deep(img) {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
      margin: 8px 0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    :deep(.hljs) {
      background: transparent;
      padding: 0;
    }

    :deep(.hljs-keyword),
    :deep(.hljs-attribute),
    :deep(.hljs-selector-tag),
    :deep(.hljs-meta-keyword),
    :deep(.hljs-doctag),
    :deep(.hljs-name) {
      color: #569cd6;
    }

    :deep(.hljs-string),
    :deep(.hljs-selector-id),
    :deep(.hljs-selector-class),
    :deep(.hljs-quote),
    :deep(.hljs-template-tag),
    :deep(.hljs-variable),
    :deep(.hljs-template-variable) {
      color: #ce9178;
    }

    :deep(.hljs-title),
    :deep(.hljs-attr) {
      color: #9cdcfe;
    }

    :deep(.hljs-comment) {
      color: #6a9955;
    }

    :deep(.hljs-keyword) {
      color: #569cd6;
    }

    :deep(.hljs-number),
    :deep(.hljs-literal),
    :deep(.hljs-type),
    :deep(.hljs-params) {
      color: #b5cea8;
    }

    :deep(.hljs-built_in),
    :deep(.hljs-builtin-name) {
      color: #4ec9b0;
    }

    :deep(.hljs-function) {
      color: #dcdcaa;
    }
  }
</style>
