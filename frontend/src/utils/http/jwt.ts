/**
 * JWT 工具
 *
 * 仅用于在前端解析 Token 的 payload（不做签名校验，校验由后端完成），
 * 主要用途是读取 `exp` 过期时间，配合 Token 自动刷新逻辑使用。
 *
 * @module utils/http/jwt
 */

/** JWT 载荷中关心的字段 */
interface JwtPayload {
  /** 过期时间（秒级时间戳） */
  exp?: number
  /** 签发时间（秒级时间戳） */
  iat?: number
  [key: string]: unknown
}

/** Base64Url 解码 */
function base64UrlDecode(input: string): string {
  const base64 = input.replace(/-/g, '+').replace(/_/g, '/')
  const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=')
  try {
    // 处理 UTF-8 字符
    return decodeURIComponent(
      atob(padded)
        .split('')
        .map((c) => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
        .join('')
    )
  } catch {
    return atob(padded)
  }
}

/**
 * 解析 JWT 载荷，失败返回 null。
 * @param token JWT 字符串
 */
export function parseJwt(token: string): JwtPayload | null {
  if (!token) return null
  const parts = token.split('.')
  if (parts.length !== 3) return null
  try {
    return JSON.parse(base64UrlDecode(parts[1])) as JwtPayload
  } catch {
    return null
  }
}

/**
 * 获取 Token 过期时间（毫秒级时间戳）。无法解析时返回 0。
 * @param token JWT 字符串
 */
export function getTokenExpireTime(token: string): number {
  const payload = parseJwt(token)
  return payload?.exp ? payload.exp * 1000 : 0
}
