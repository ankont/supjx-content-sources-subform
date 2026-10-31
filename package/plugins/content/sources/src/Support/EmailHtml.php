<?php
namespace SuperSoft\Plugin\Content\Sources\Support;

/** Email-only allowlist. Never processes site layouts, scripts, content events or CSS. */
final class EmailHtml
{
    public static function hasSourcesSyntax(string $html): bool
    {
        if (stripos($html, 'sources') === false) { return false; }
        for ($i = 0; $i < 16; $i++) {
            if (preg_match('/\{\s*sources\b/i', $html)) { return true; }
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $html) { return false; }
            $html = $decoded;
        }
        return true; // Excessively encoded extension-like input fails closed.
    }
    public static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function url(string $url, string $root, bool $image = false): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { return ''; }
        if (str_starts_with($url, '//')) { $url = (parse_url($root, PHP_URL_SCHEME) ?: 'https') . ':' . $url; }
        if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            if (str_starts_with($url, '/')) {
                $parts = parse_url($root); $url = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . $url;
            } else { $url = rtrim($root, '/') . '/' . $url; }
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, $image ? ['https', 'http'] : ['https', 'http', 'mailto', 'tel'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) { return ''; }
        if (in_array($scheme, ['http', 'https'], true) && !parse_url($url, PHP_URL_HOST)) { return ''; }
        return $url;
    }

    public static function clean(string $html, string $root): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body) { return ''; }
        $walk = static function (\DOMNode $node) use (&$walk, $root): string {
            if ($node instanceof \DOMText) { return self::escape($node->nodeValue ?? ''); }
            if (!$node instanceof \DOMElement) { return ''; }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'template', 'video', 'audio'], true)) { return ''; }
            $content = ''; foreach ($node->childNodes as $child) { $content .= $walk($child); }
            if (!in_array($tag, ['p', 'div', 'span', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'a', 'img'], true)) { return $content; }
            $attributes = '';
            if ($tag === 'a' || $tag === 'img') {
                $key = $tag === 'a' ? 'href' : 'src'; $url = self::url($node->getAttribute($key), $root, $tag === 'img');
                if ($url === '') { return $tag === 'img' ? '' : $content; }
                $attributes .= ' ' . $key . '="' . self::escape($url) . '"';
                if ($tag === 'img') { $attributes .= ' alt="' . self::escape($node->getAttribute('alt')) . '" style="display:block;max-width:100%;height:auto;border:0"'; }
            }
            if (in_array($tag, ['td', 'th'], true)) {
                foreach (['colspan', 'rowspan'] as $key) { $value = $node->getAttribute($key); if (ctype_digit($value) && (int) $value <= 20) { $attributes .= ' ' . $key . '="' . $value . '"'; } }
            }
            if (in_array($tag, ['br', 'hr', 'img'], true)) { return '<' . $tag . $attributes . '>'; }
            return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
        };
        $result = ''; foreach ($body->childNodes as $child) { $result .= $walk($child); }
        return $result;
    }
}
