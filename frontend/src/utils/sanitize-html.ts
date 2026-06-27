/**
 * 富文本 HTML 清洗工具（前端兜底防御）。
 *
 * 与后端 {@link app/common/support/HtmlSanitizer.php} 互为保险：
 * 后端在写入数据库时已做白名单清洗，但仍可能存在：
 *  - 旧数据未清洗
 *  - 第三方接口直接拼接 HTML
 *  - 攻击者绕过后端验证
 * 因此前端 v-html 渲染前再走一次 strip，遵循「假设输入不可信」原则。
 *
 * 使用方式：
 *   import { sanitizeHtml } from '@/utils/sanitize-html'
 *   <div v-html="sanitizeHtml(content)" />
 */

/** 黑名单标签：连同内部内容一起剥光 */
const FORBIDDEN_TAGS = new Set([
  'script',
  'style',
  'iframe',
  'object',
  'embed',
  'link',
  'meta',
  'base',
  'form'
])

/** 允许的 URL 协议（href/src/poster 等属性） */
const SAFE_URL_RE = /^(https?:|mailto:|tel:|data:image\/|#|\/|\?)/i

/**
 * 检查 URL 是否安全（不含 javascript:/vbscript:/file: 等危险协议）。
 */
const isSafeUrl = (url: string): boolean => {
  const trimmed = url.trim()
  if (trimmed === '') return true
  if (SAFE_URL_RE.test(trimmed)) return true
  // 没有显式 scheme（没有 ":"）视作相对路径，放行
  return !/^[a-z][a-z0-9+.-]*:/i.test(trimmed)
}

/**
 * 递归清洗 DOM 节点：
 *  1. 黑名单标签整段移除；
 *  2. 所有 on* 属性剥光；
 *  3. href/src 协议白名单；
 *  4. style 内禁止 javascript:/expression()/@import。
 */
const sanitizeNode = (node: Element): void => {
  // 先快照子节点，避免遍历时修改集合
  const children = Array.from(node.children)
  for (const child of children) {
    const tag = child.tagName.toLowerCase()

    if (FORBIDDEN_TAGS.has(tag)) {
      child.remove()
      continue
    }

    // 清属性
    for (const attr of Array.from(child.attributes)) {
      const name = attr.name.toLowerCase()
      const value = attr.value

      // 1. on* 事件统统剥
      if (name.startsWith('on')) {
        child.removeAttribute(attr.name)
        continue
      }

      // 2. style 内禁危险表达式
      if (name === 'style') {
        const lower = value.toLowerCase()
        if (
          lower.includes('javascript:') ||
          lower.includes('vbscript:') ||
          lower.includes('expression(') ||
          lower.includes('@import')
        ) {
          child.removeAttribute('style')
        }
        continue
      }

      // 3. href/src/poster 走 URL 协议白名单
      if (['href', 'src', 'poster', 'action', 'formaction'].includes(name)) {
        if (!isSafeUrl(value)) {
          child.removeAttribute(attr.name)
        }
        continue
      }
    }

    // <a target="_blank"> 强制带 rel="noopener noreferrer" 防反向 tabnabbing
    if (tag === 'a' && child.getAttribute('target') === '_blank') {
      const rel = child.getAttribute('rel') || ''
      if (!rel.includes('noopener')) {
        child.setAttribute('rel', (rel + ' noopener noreferrer').trim())
      }
    }

    sanitizeNode(child)
  }
}

/**
 * 清洗富文本字符串，返回安全的 HTML。
 *
 * 解析失败兜底返回纯文本（永不抛错，永不向 DOM 输出未经清洗的内容）。
 */
export const sanitizeHtml = (html: string | null | undefined): string => {
  if (html == null || html === '') return ''
  try {
    // 用 DOMParser 解析；不会执行 script，但仍要主动剥
    const doc = new DOMParser().parseFromString(html, 'text/html')
    sanitizeNode(doc.body)
    return doc.body.innerHTML
  } catch {
    // 解析异常 → 退化为纯文本
    return stripTags(html)
  }
}

/**
 * 简单纯文本提取（剥所有标签 + 折叠空白）。
 */
export const stripTags = (html: string | null | undefined): string => {
  if (html == null || html === '') return ''
  return html
    .replace(/<[^>]+>/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}
