<?php

namespace app\common\support;

/**
 * 富文本 HTML 清洗器。
 *
 * 用途：通知 / 公告等"管理员录入富文本，前端 v-html 渲染"的字段，**必须**在写入数据库前
 * 经过本类清洗，避免存储型 XSS。
 *
 * 设计：
 *  - 白名单标签（{@see self::ALLOWED_TAGS}）—— 不在表内的标签连同内容剥光（exception：script/style 内容也剥）；
 *  - 白名单属性（{@see self::ALLOWED_ATTRS}）—— 在表内的 *元素* 才允许这些属性；
 *  - 强制移除所有 on* 事件属性；
 *  - href/src 协议白名单：仅 http(s)/mailto/tel/data:image，明确拦截 javascript: vbscript: file:；
 *  - style 内禁用 expression() / url(javascript:)。
 *
 * 不引入新依赖（避免 ezyang/htmlpurifier 这种重型包）：用 DOMDocument loadHTML +
 * iconv 给中文做编码补偿，然后做白名单过滤；对 99% 的富文本编辑器场景足够。
 */
class HtmlSanitizer
{
    /** 允许的标签集合（不区分大小写） */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'span', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'mark', 'small',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    ];

    /** 允许的全局属性（任意白名单标签都可用）；data-* 走前缀匹配 */
    private const ALLOWED_GLOBAL_ATTRS_LITERAL = ['class', 'style', 'title', 'id'];

    /** 按标签维度允许的额外属性 */
    private const ALLOWED_ATTRS = [
        'a'   => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'td'  => ['colspan', 'rowspan', 'align', 'valign'],
        'th'  => ['colspan', 'rowspan', 'align', 'valign'],
        'col' => ['span'],
        'ol'  => ['start', 'type'],
    ];

    /** href / src 允许的协议前缀（小写比较） */
    private const ALLOWED_URL_SCHEMES = ['http:', 'https:', 'mailto:', 'tel:', 'data:image/'];

    /**
     * 清洗 HTML 字符串。
     *
     * 输入为空 / null 直接返回空串。失败兜底返回 strip_tags 后的纯文本，
     * 永远不向上抛异常（业务侧调用 sanitize() 不应被异常打断）。
     */
    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        // 提前消去 script / style 块（含内容），避免 DOMDocument 解析后还能命中
        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)\b[^>]*/?>#is', '', $html) ?? $html;

        // 用 DOMDocument 解析；prefix UTF-8 编码声明，避免中文乱码
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        try {
            $wrapped = '<?xml encoding="UTF-8"?><div id="__sanitizer_root__">' . $html . '</div>';
            $loaded = $dom->loadHTML(
                $wrapped,
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
            );
            if (!$loaded) {
                return self::fallback($html);
            }
        } catch (\Throwable) {
            return self::fallback($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }

        $root = $dom->getElementById('__sanitizer_root__');
        if (!$root) {
            return self::fallback($html);
        }

        self::walk($root);

        // 输出 root 的 innerHTML
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    /**
     * 仅去除 HTML 标签，得到纯文本（用于截断、搜索索引等场景）。
     */
    public static function toPlainText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $stripped = strip_tags($html);
        // 把多余空白合并
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * 递归遍历 DOM 节点，剔除非白名单标签与属性。
     */
    private static function walk(\DOMNode $node): void
    {
        // 先快照子节点：边遍历边删，原集合下标会乱
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    // 非白名单标签：把内容提升到当前层级，剥掉标签外壳
                    self::unwrap($child);
                    continue;
                }

                // 处理属性
                self::sanitizeAttributes($child, $tag);

                // 递归处理子节点
                self::walk($child);
            } elseif ($child instanceof \DOMComment) {
                // 移除注释（防 <!--[if IE]> 一类的 IE conditional 攻击面）
                $child->parentNode?->removeChild($child);
            }
        }
    }

    /**
     * 把元素的内容提升到父节点（即"剥壳保留内容"）。
     */
    private static function unwrap(\DOMElement $el): void
    {
        $parent = $el->parentNode;
        if (!$parent) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    /**
     * 清理元素属性：剥离非白名单 + 协议白名单校验 + style 内禁用条目。
     */
    private static function sanitizeAttributes(\DOMElement $el, string $tag): void
    {
        // 先快照属性名（边遍历边删会跳过）
        $names = [];
        foreach ($el->attributes as $attr) {
            $names[] = $attr->nodeName;
        }

        $tagSpecific = self::ALLOWED_ATTRS[$tag] ?? [];

        foreach ($names as $name) {
            $lower = strtolower($name);

            // 一律剥 on* 事件
            if (str_starts_with($lower, 'on')) {
                $el->removeAttribute($name);
                continue;
            }

            // data-* 全局放行
            if (str_starts_with($lower, 'data-')) {
                continue;
            }

            // 全局属性
            if (in_array($lower, self::ALLOWED_GLOBAL_ATTRS_LITERAL, true)) {
                if ($lower === 'style') {
                    $cleaned = self::cleanStyle((string) $el->getAttribute('style'));
                    if ($cleaned === '') {
                        $el->removeAttribute('style');
                    } else {
                        $el->setAttribute('style', $cleaned);
                    }
                }
                continue;
            }

            // 标签维度白名单
            if (in_array($lower, $tagSpecific, true)) {
                // href/src 协议校验
                if (in_array($lower, ['href', 'src'], true)) {
                    if (!self::isSafeUrl((string) $el->getAttribute($name))) {
                        $el->removeAttribute($name);
                    }
                }
                continue;
            }

            // 其余一律剥掉
            $el->removeAttribute($name);
        }

        // <a target="_blank"> 强制带上 rel="noopener noreferrer"，防 reverse tabnabbing
        if ($tag === 'a' && strtolower((string) $el->getAttribute('target')) === '_blank') {
            $rel = (string) $el->getAttribute('rel');
            if (!str_contains($rel, 'noopener')) {
                $el->setAttribute('rel', trim($rel . ' noopener noreferrer'));
            }
        }
    }

    /**
     * style 属性清洗：禁用 expression()、url(javascript:)、@import 等。
     */
    private static function cleanStyle(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }

        // 黑名单 token：命中任意一条就整段丢弃，避免被"半合法"绕过
        $blacklist = [
            'expression(', 'javascript:', 'vbscript:', '@import',
        ];
        $lower = strtolower($style);
        foreach ($blacklist as $bad) {
            if (str_contains($lower, $bad)) {
                return '';
            }
        }
        // url() 仅允许 http/https/data:image
        if (preg_match_all('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', $style, $m)) {
            foreach ($m[2] as $url) {
                if (!self::isSafeUrl($url)) {
                    return '';
                }
            }
        }
        return $style;
    }

    /**
     * 判断 URL 是否在协议白名单内（兼容相对路径与片段）。
     */
    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }
        // 相对路径 / 锚点
        if ($url[0] === '/' || $url[0] === '#' || $url[0] === '?') {
            return true;
        }
        $lower = strtolower($url);
        foreach (self::ALLOWED_URL_SCHEMES as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return true;
            }
        }
        // 没有显式 scheme 也视为相对路径
        if (!preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url)) {
            return true;
        }
        return false;
    }

    /**
     * 解析失败兜底：返回纯文本，宁可丢格式也不留 XSS 风险。
     */
    private static function fallback(string $html): string
    {
        return self::toPlainText($html);
    }
}
