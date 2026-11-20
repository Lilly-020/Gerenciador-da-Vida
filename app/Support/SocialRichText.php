<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class SocialRichText
{
    private const UNDERLINE_COLORS = ['indigo', 'sky', 'emerald', 'amber', 'rose', 'white'];

    private const MARKER_COLORS = ['amber', 'sky', 'emerald', 'rose', 'indigo', 'orange'];

    /** @return array{content: string, content_html: string} */
    public function sanitize(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $clean = '';
        foreach ($document->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $node) {
            $clean .= $this->render($node, 0);
        }
        $text = html_entity_decode(strip_tags(preg_replace('/<br>|<\/(?:p|div|li|h[1-6]|blockquote)>/', "\n", $clean)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(str_replace("\u{00A0}", ' ', $text));

        return ['content' => $text, 'content_html' => $clean];
    }

    private function render(DOMNode $node, int $depth): string
    {
        if ($depth > 30) {
            return '';
        }
        if ($node->nodeType === XML_TEXT_NODE) {
            return e($node->textContent);
        }
        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'button', 'video', 'audio', 'img'], true)) {
            return '';
        }
        if ($tag === 'br') {
            return '<br>';
        }

        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $this->render($child, $depth + 1);
        }
        if ($tag === 'span' && preg_match('/(?:^|;)\s*background-color\s*:\s*(?:#fef08a|rgb\(254,\s*240,\s*138\))\s*(?:;|$)/i', $node->getAttribute('style'))) {
            $tag = 'mark';
        }
        if ($tag === 'u' && in_array($color = $node->getAttribute('data-underline-color'), self::UNDERLINE_COLORS, true)) {
            return '<u data-underline-color="'.$color.'">'.$children.'</u>';
        }
        if ($tag === 'mark' && in_array($color = $node->getAttribute('data-marker-color'), self::MARKER_COLORS, true)) {
            return '<mark data-marker-color="'.$color.'">'.$children.'</mark>';
        }

        return in_array($tag, ['p', 'div', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'mark', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)
            ? '<'.$tag.'>'.$children.'</'.$tag.'>'
            : $children;
    }
}
