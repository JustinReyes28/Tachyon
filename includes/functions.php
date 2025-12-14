<?php
/**
 * Tachyon Helper Functions
 */

/**
 * Generate a cache-busted asset URL using file modification timestamp.
 * Appends ?v=TIMESTAMP to the asset path to force browser cache invalidation
 * when the file changes.
 * 
 * @param string $path Relative path to the asset from the project root (e.g., 'style.css')
 * @return string The asset path with version query string
 */
function asset_url($path)
{
    $file_path = __DIR__ . '/../' . $path;

    // Suppress errors and use fallback if filemtime fails
    $mtime = @filemtime($file_path);

    if ($mtime !== false) {
        return $path . '?v=' . $mtime;
    }

    // Fallback: use current date as version (updates daily)
    return $path . '?v=' . date('Ymd');
}

/**
 * Sanitize HTML content using DOMDocument
 * Allows only whitelisted tags and attributes to prevent XSS.
 * 
 * @param string $html The input HTML string
 * @return string Sanitized HTML string
 */
function sanitize_html($html)
{
    if (empty($html))
        return '';

    // Suppress libxml errors for malformed HTML fragments
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    // utf-8 hack: loadHTML expects full document, so we wrap it
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);

    // 1. Remove scripts, styles (optional), objects, iframes, etc.
    $dangerous_tags = ['script', 'iframe', 'object', 'embed', 'form', 'style', 'link', 'meta'];
    foreach ($dangerous_tags as $tag) {
        $nodes = $xpath->query("//{$tag}");
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    // 2. Allowed tags whitelist
    $allowed_tags = [
        'p',
        'br',
        'strong',
        'em',
        'u',
        's',
        'a',
        'ul',
        'ol',
        'li',
        'h1',
        'h2',
        'h3',
        'blockquote',
        'pre',
        'code',
        'img',
        'div',
        'span'
    ];

    // Get all elements
    $elements = $xpath->query('//*');

    // We need to collect nodes to remove or strip to avoid modifying the list while iterating
    $to_strip = [];

    foreach ($elements as $element) {
        if (!in_array(strtolower($element->nodeName), $allowed_tags)) {
            // If tag is not allowed, mark for stripping (keep content, remove tag)
            $to_strip[] = $element;
            continue;
        }

        // 3. Attribute Whitelist
        $allowed_attributes = [];
        if ($element->nodeName === 'a') {
            $allowed_attributes = ['href', 'target', 'rel'];
        } elseif ($element->nodeName === 'img') {
            $allowed_attributes = ['src', 'alt', 'width', 'height'];
        }
        // Common safe attributes could be added here if needed (e.g. class, id - generally risky for style injection)

        // Iterate attributes backwards to safely remove
        if ($element->hasAttributes()) {
            $attributes_to_remove = [];
            foreach ($element->attributes as $attr) {
                $attrName = strtolower($attr->name);

                // Remove all on* events (onclick, onload, etc.)
                if (strpos($attrName, 'on') === 0) {
                    $attributes_to_remove[] = $attrName;
                    continue;
                }

                // If not in specific whitelist for this tag, remove it
                if (!in_array($attrName, $allowed_attributes)) {
                    $attributes_to_remove[] = $attrName;
                    continue;
                }

                // 4. Validate URI schemes (href, src)
                if ($attrName === 'href' || $attrName === 'src') {
                    $value = trim($attr->value);
                    // Allow http, https, mailto, tel, and data (for images)
                    if (preg_match('/^\s*javascript:/i', $value)) {
                        $attributes_to_remove[] = $attrName;
                    }
                }
            }

            foreach ($attributes_to_remove as $attrName) {
                $element->removeAttribute($attrName);
            }

            // Force rel="noopener noreferrer" on links with target="_blank"
            if ($element->nodeName === 'a' && $element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    // Strip disallowed tags but keep content
    foreach ($to_strip as $element) {
        $fragment = $dom->createDocumentFragment();
        while ($element->childNodes->length > 0) {
            $fragment->appendChild($element->childNodes->item(0));
        }
        $element->parentNode->replaceChild($fragment, $element);
    }

    $clean_html = $dom->saveHTML();

    // Remove the XML encoding wrapper we added
    $clean_html = str_replace('<?xml encoding="utf-8" ?>', '', $clean_html);

    libxml_clear_errors();
    return trim($clean_html);
}

/**
 * Convert HTML content to plain text while preserving line breaks.
 * Converts block-level HTML elements to line breaks before stripping tags.
 * 
 * @param string $html The input HTML string
 * @return string Plain text with line breaks preserved
 */
function html_to_plain_text($html)
{
    if (empty($html)) {
        return '';
    }

    // Replace block-level elements with line breaks
    $html = preg_replace('/<\/p>/i', "\n", $html);
    $html = preg_replace('/<p[^>]*>/i', '', $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<\/div>/i', "\n", $html);
    $html = preg_replace('/<div[^>]*>/i', '', $html);
    $html = preg_replace('/<\/li>/i', "\n", $html);
    $html = preg_replace('/<\/h[1-6]>/i', "\n", $html);
    $html = preg_replace('/<\/blockquote>/i', "\n", $html);

    // Strip remaining HTML tags
    $text = strip_tags($html);

    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Normalize whitespace (collapse multiple spaces but preserve line breaks)
    $text = preg_replace('/[^\S\n]+/', ' ', $text);

    // Remove excessive line breaks (more than 2 consecutive)
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
}
?>