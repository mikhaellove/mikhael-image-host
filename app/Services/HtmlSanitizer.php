<?php

namespace App\Services;

class HtmlSanitizer
{
    /**
     * Sanitize HTML to prevent XSS attacks
     * Allows only safe tags and attributes
     */
    public static function sanitize(string $html): string
    {
        // Allowed tags
        $allowedTags = [
            'p', 'br', 'strong', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'a', 'img', 'blockquote', 'code', 'pre', 'div', 'span'
        ];

        // Allowed attributes per tag
        $allowedAttributes = [
            'a' => ['href', 'title'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            'div' => ['class'],
            'span' => ['class'],
        ];

        // Load HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Remove XML encoding tag
        foreach ($dom->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $dom->removeChild($item);
            }
        }

        // Process all elements
        self::sanitizeNode($dom->documentElement, $allowedTags, $allowedAttributes);

        return $dom->saveHTML();
    }

    /**
     * Sanitize caption HTML with a tighter whitelist than the general sanitizer.
     * Allowed: inline formatting + lists + links. No images, headings, divs, code blocks.
     */
    public static function sanitizeCaption(string $html): string
    {
        $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'b', 'i', 'ul', 'ol', 'li', 'a', 'div'];
        $allowedAttributes = [
            'a' => ['href', 'title'],
        ];

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($dom->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $dom->removeChild($item);
            }
        }

        self::sanitizeNode($dom->documentElement, $allowedTags, $allowedAttributes);

        return trim($dom->saveHTML());
    }

    /**
     * Render a caption for display. Supports both legacy plain-text captions
     * (preserves newlines via nl2br) and new HTML-formatted captions (sanitized).
     */
    public static function renderCaption(?string $caption): string
    {
        if ($caption === null || $caption === '') {
            return '';
        }

        // Detect if caption contains HTML formatting tags
        if (preg_match('/<\s*(p|br|strong|em|u|ul|ol|li|a|b|i|div)\b/i', $caption)) {
            return self::sanitizeCaption($caption);
        }

        // Legacy plain-text caption: escape and convert newlines
        return nl2br(htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'));
    }

    private static function sanitizeNode(?\DOMNode $node, array $allowedTags, array $allowedAttributes): void
    {
        if ($node === null) {
            return;
        }

        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($node->nodeName);

            // Remove disallowed tags
            if (!in_array($tagName, $allowedTags)) {
                $node->parentNode->removeChild($node);
                return;
            }

            // Remove disallowed attributes
            if ($node->hasAttributes()) {
                $attributesToRemove = [];

                foreach ($node->attributes as $attribute) {
                    $attrName = strtolower($attribute->name);
                    $attrValue = $attribute->value;

                    // Check if attribute is allowed for this tag
                    $allowed = isset($allowedAttributes[$tagName]) && in_array($attrName, $allowedAttributes[$tagName]);

                    // Additional security checks
                    if ($allowed) {
                        // Block javascript: and data: URLs
                        if (in_array($attrName, ['href', 'src'])) {
                            if (preg_match('/^(javascript|data|vbscript):/i', $attrValue)) {
                                $allowed = false;
                            }
                        }

                        // Block on* event handlers
                        if (str_starts_with($attrName, 'on')) {
                            $allowed = false;
                        }
                    }

                    if (!$allowed) {
                        $attributesToRemove[] = $attrName;
                    }
                }

                foreach ($attributesToRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }
        }

        // Process child nodes
        if ($node->hasChildNodes()) {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                self::sanitizeNode($child, $allowedTags, $allowedAttributes);
            }
        }
    }
}
