<?php
// Simple sanitization helpers
// Returns a trimmed string with tags stripped
function sanitize_text(string $str): string {
    return trim(strip_tags($str));
}
// Sanitizes url
function sanitize_url(string $url): string {
    return filter_var(trim($url), FILTER_SANITIZE_URL) ?: '';
}
// Sanitize HTML datetime-local input values
function sanitize_datetime_local($value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s'];
    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d\TH:i');
        }
    }

    return '';
}
// Sanitizes an array of tags by running sanitize_text on each
function sanitize_tags($tags) {
    if (!is_array($tags)) return [];
    return array_values(array_filter(array_map('sanitize_text', $tags)));
}

function sparkcms_allowed_html_tags(): array {
    return [
        'a',
        'abbr',
        'address',
        'article',
        'aside',
        'b',
        'blockquote',
        'br',
        'button',
        'caption',
        'code',
        'div',
        'em',
        'fieldset',
        'figcaption',
        'figure',
        'footer',
        'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'header',
        'hr',
        'i',
        'iframe',
        'img',
        'input',
        'label',
        'legend',
        'li',
        'main',
        'nav',
        'ol',
        'option',
        'p',
        'pre',
        'section',
        'select',
        'small',
        'source',
        'span',
        'strong',
        'sub',
        'sup',
        'table',
        'tbody',
        'td',
        'templateSetting',
        'textarea',
        'tfoot',
        'th',
        'thead',
        'tr',
        'u',
        'ul',
        'video',
    ];
}

function sparkcms_allowed_html_attributes(): array {
    return [
        'accept',
        'action',
        'allow',
        'allowfullscreen',
        'alt',
        'aria-describedby',
        'aria-hidden',
        'aria-label',
        'aria-live',
        'aria-pressed',
        'aria-required',
        'aria-selected',
        'aria-valuemax',
        'aria-valuemin',
        'aria-valuenow',
        'aria-valuetext',
        'autocomplete',
        'autofocus',
        'autoplay',
        'checked',
        'class',
        'cols',
        'colspan',
        'controls',
        'data',
        'disabled',
        'for',
        'height',
        'href',
        'id',
        'loading',
        'loop',
        'max',
        'maxlength',
        'method',
        'min',
        'multiple',
        'muted',
        'name',
        'placeholder',
        'poster',
        'rel',
        'required',
        'role',
        'rows',
        'rowspan',
        'selected',
        'src',
        'step',
        'style',
        'target',
        'title',
        'type',
        'value',
        'width',
    ];
}

function sparkcms_is_safe_url(string $url, string $tag, string $attr): bool {
    $url = trim($url);
    if ($url === '') {
        return true;
    }

    if (str_starts_with($url, '#')) {
        return true;
    }

    $lower = strtolower($url);
    if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:')) {
        return false;
    }

    if (str_starts_with($lower, 'data:')) {
        if ($tag === 'img' && $attr === 'src') {
            return (bool) preg_match('/^data:image\\/(png|jpe?g|gif|webp);base64,/i', $url);
        }
        return false;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null) {
        return true;
    }

    $scheme = strtolower($scheme);
    return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
}

function sparkcms_sanitize_style(string $style): string {
    $style = trim($style);
    if ($style === '') {
        return '';
    }
    if (preg_match('/expression\\s*\\(/i', $style)) {
        return '';
    }
    if (preg_match('/url\\s*\\(\\s*[\'"]?\\s*javascript:/i', $style)) {
        return '';
    }
    if (preg_match('/@import/i', $style)) {
        return '';
    }
    return $style;
}

function sanitize_html(string $html): string {
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = sparkcms_allowed_html_tags();
    $allowedAttrs = sparkcms_allowed_html_attributes();

    if (!class_exists('DOMDocument')) {
        $tags = '<' . implode('><', $allowedTags) . '>';
        return strip_tags($html, $tags);
    }

    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $wrapped = '<div>' . $html . '</div>';
    $options = 0;
    if (defined('LIBXML_HTML_NOIMPLIED')) {
        $options |= LIBXML_HTML_NOIMPLIED;
    }
    if (defined('LIBXML_HTML_NODEFDTD')) {
        $options |= LIBXML_HTML_NODEFDTD;
    }
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, $options);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $dom->getElementsByTagName('div')->item(0);
    if (!$root) {
        return '';
    }

    $disallowedStrip = ['script', 'style'];

    $sanitizeNode = function (DOMNode $node) use (&$sanitizeNode, $allowedTags, $allowedAttrs, $disallowedStrip) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($node->nodeName);
            if (!in_array($tag, $allowedTags, true)) {
                $parent = $node->parentNode;
                if ($parent) {
                    if (!in_array($tag, $disallowedStrip, true)) {
                        while ($node->firstChild) {
                            $parent->insertBefore($node->firstChild, $node);
                        }
                    }
                    $parent->removeChild($node);
                }
                return;
            }

            if ($node->hasAttributes()) {
                $attrs = [];
                foreach ($node->attributes as $attr) {
                    $attrs[] = $attr;
                }
                foreach ($attrs as $attr) {
                    $name = strtolower($attr->name);
                    $value = $attr->value;

                    if (str_starts_with($name, 'on') || $name === 'srcdoc') {
                        $node->removeAttributeNode($attr);
                        continue;
                    }

                    $allowed = in_array($name, $allowedAttrs, true)
                        || str_starts_with($name, 'data-')
                        || str_starts_with($name, 'aria-');

                    if (!$allowed) {
                        $node->removeAttributeNode($attr);
                        continue;
                    }

                    if (($name === 'href' || $name === 'src') && !sparkcms_is_safe_url($value, $tag, $name)) {
                        $node->removeAttributeNode($attr);
                        continue;
                    }

                    if ($name === 'style') {
                        $clean = sparkcms_sanitize_style($value);
                        if ($clean === '') {
                            $node->removeAttributeNode($attr);
                        } else {
                            $node->setAttribute('style', $clean);
                        }
                    }
                }
            }
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $sanitizeNode($child);
        }
    };

    $sanitizeNode($root);

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $dom->saveHTML($child);
    }

    return $output;
}

if (!defined('SPARKCMS_DEFAULT_ROBOTS_DIRECTIVE')) {
    define('SPARKCMS_DEFAULT_ROBOTS_DIRECTIVE', 'index,follow');
}

function sparkcms_default_robots_directive(): string {
    return SPARKCMS_DEFAULT_ROBOTS_DIRECTIVE;
}

function sanitize_robots_directive($value): string {
    $normalized = strtolower(trim((string)$value));
    if ($normalized === '') {
        return sparkcms_default_robots_directive();
    }

    $normalized = str_replace([';', '|'], ',', $normalized);
    $parts = preg_split('/[\s,]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

    $indexDirective = 'index';
    $followDirective = 'follow';

    foreach ($parts as $part) {
        if ($part === 'index' || $part === 'noindex') {
            $indexDirective = $part;
        }
        if ($part === 'follow' || $part === 'nofollow') {
            $followDirective = $part;
        }
    }

    $directive = $indexDirective . ',' . $followDirective;
    $allowed = [
        'index,follow',
        'index,nofollow',
        'noindex,follow',
        'noindex,nofollow',
    ];

    if (!in_array($directive, $allowed, true)) {
        return sparkcms_default_robots_directive();
    }

    return $directive;
}
?>
