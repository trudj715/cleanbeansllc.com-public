<?php
/**
 * Allowlist HTML sanitizer for content written in the admin editor.
 * Keeps formatting, links, images and tables; removes scripts, event handlers,
 * styles and anything else not explicitly allowed.
 */
declare(strict_types=1);

const SANITIZE_TAGS = [
    'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
    'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
    'a' => ['href', 'title', 'target'], 'img' => ['src', 'alt', 'width', 'height'],
    'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    'hr' => [], 'span' => [], 'div' => [],
];

function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('__root');
    if (!$root) {
        return '';
    }
    sanitize_node($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function sanitize_node(DOMNode $node): void
{
    // Iterate over a static copy because we modify the tree.
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMComment) {
            $node->removeChild($child);
            continue;
        }
        if (!($child instanceof DOMElement)) {
            continue;
        }
        $tag = strtolower($child->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base'], true)) {
            $node->removeChild($child);
            continue;
        }
        if (!array_key_exists($tag, SANITIZE_TAGS)) {
            // Unknown tag: keep its text/children, drop the tag itself.
            sanitize_node($child);
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }
        $allowed = SANITIZE_TAGS[$tag];
        foreach (iterator_to_array($child->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allowed, true)) {
                $child->removeAttribute($attr->name);
                continue;
            }
            if (in_array($name, ['href', 'src'], true) && !safe_url($attr->value, $name === 'src')) {
                $child->removeAttribute($attr->name);
            }
        }
        if ($tag === 'a') {
            if ($child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            } else {
                $child->removeAttribute('target');
            }
        }
        if ($tag === 'img') {
            if (!$child->hasAttribute('src')) {
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('loading', 'lazy');
        }
        sanitize_node($child);
    }
}

function safe_url(string $u, bool $isImage): bool
{
    $u = trim(html_entity_decode($u, ENT_QUOTES, 'UTF-8'));
    if ($u === '' || str_starts_with($u, '//')) {
        return false;                       // empty or protocol-relative
    }
    if ($u[0] === '/' || $u[0] === '#') {
        return true;                        // same-site path or in-page anchor
    }
    $scheme = strtolower((string)parse_url($u, PHP_URL_SCHEME));
    $ok = $isImage ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];
    return in_array($scheme, $ok, true);
}

/** Plain text from HTML (for meta descriptions, excerpts). */
function plain_text(string $html, int $max = 0): string
{
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ' ', $html)), ENT_QUOTES, 'UTF-8')));
    if ($max > 0 && mb_strlen($t) > $max) {
        $t = rtrim(mb_substr($t, 0, $max - 1)) . '…';
    }
    return $t;
}
