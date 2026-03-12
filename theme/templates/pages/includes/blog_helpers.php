<?php
// Shared blog helper functions referenced by templates when rendered in isolation.

if (!function_exists('sparkcms_parse_blog_limit')) {
    function sparkcms_parse_blog_limit($value)
    {
        $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $limit ?: 6;
    }
}

if (!function_exists('sparkcms_normalize_blog_category')) {
    function sparkcms_normalize_blog_category($value)
    {
        return strtolower(trim((string) $value));
    }
}

if (!function_exists('sparkcms_is_blog_post_live')) {
    function sparkcms_is_blog_post_live($post)
    {
        if (!is_array($post)) {
            return false;
        }
        $status = strtolower((string) ($post['status'] ?? ''));
        if ($status === 'published') {
            return true;
        }
        if ($status === 'scheduled') {
            $publishDate = $post['publishDate'] ?? '';
            if ($publishDate === '') {
                return false;
            }
            $timestamp = strtotime($publishDate);
            if ($timestamp === false) {
                return false;
            }
            return $timestamp <= time();
        }
        return false;
    }
}

if (!function_exists('sparkcms_format_blog_date')) {
    function sparkcms_format_blog_date($value)
    {
        if (empty($value)) {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }
        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('sparkcms_resolve_blog_detail_url')) {
    function sparkcms_resolve_blog_detail_url($scriptBase, $prefix, $slug)
    {
        if (empty($slug)) {
            return '#';
        }
        $detail = trim((string) $prefix);
        if ($detail === '') {
            $detail = '/blogs';
        }
        if ($detail[0] !== '/') {
            $detail = '/' . $detail;
        }
        $base = rtrim((string) $scriptBase, '/');
        if ($base === '' || $base === '/') {
            return $detail . '/' . ltrim($slug, '/');
        }
        return $base . $detail . '/' . ltrim($slug, '/');
    }
}
