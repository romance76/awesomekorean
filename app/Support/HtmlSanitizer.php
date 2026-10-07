<?php

namespace App\Support;

/**
 * 회원/외부에서 들어온 HTML(구인 공고 본문, 정보 글 본문 등)을 화이트리스트 방식으로 정리한다.
 * 허용하지 않은 태그(script/iframe/style/form …)는 통째로 지우거나 껍데기만 벗기고,
 * onclick 같은 이벤트 속성·javascript: 주소·style 속성은 모두 제거한다. (저장될 때와 보여줄 때 모두 사용)
 */
class HtmlSanitizer
{
    /** 허용 태그 → 허용 속성 */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [], 'sub' => [], 'sup' => [], 'small' => [], 'mark' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'pre' => [], 'code' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan'],
        'figure' => [], 'figcaption' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    /** 내용까지 통째로 버릴 태그 */
    private const DROP = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'link', 'meta', 'base', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'noscript', 'template', 'audio', 'video', 'source', 'canvas'];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') return '';
        // 태그가 하나도 없으면 그대로(줄바꿈만 화면에서 처리)
        if (!preg_match('/<[a-z!\/]/i', $html)) return $html;

        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if (!$root) return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) $out .= $doc->saveHTML($child);
        return $out;
    }

    private static function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) { $node->removeChild($child); continue; }
            if (!($child instanceof \DOMElement)) continue;

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) { $node->removeChild($child); continue; }

            self::walk($child);   // 먼저 자식부터 정리

            if (!array_key_exists($tag, self::ALLOWED)) {   // 허용 안 한 태그는 껍데기만 벗기고 내용은 남긴다
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            $okAttrs = self::ALLOWED[$tag];
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $okAttrs, true)) { $child->removeAttribute($attr->name); continue; }
                if (in_array($name, ['href', 'src'], true) && !self::safeUrl($attr->value, $name === 'src')) $child->removeAttribute($attr->name);
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener noreferrer nofollow ugc');
                $child->setAttribute('target', '_blank');
            }
        }
    }

    private static function safeUrl(string $url, bool $isSrc): bool
    {
        $u = preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5));   // 숨은 공백/제어문자로 우회하는 것 방지
        if ($u === '') return false;
        if (preg_match('#^(https?:)?//#i', $u) || str_starts_with($u, '/') || str_starts_with($u, '#')) return !preg_match('#^//\s*$#', $u);
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $u, $m)) {
            $scheme = strtolower($m[1]);
            return $isSrc ? in_array($scheme, ['http', 'https'], true) : in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
        }
        return true;   // 상대 경로
    }
}
