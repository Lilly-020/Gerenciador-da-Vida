<?php

namespace App\Support;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use DOMDocument;
use DOMElement;
use DOMNode;

class QuadroRichText
{
    /** @return array{html: string, text: string, file_ids: list<string>, mention_ids: list<int>} */
    public function sanitize(string $html, Quadro $board, QuadroCartao $card): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $files = collect($card->attachments ?? [])->keyBy('id');
        $ids = [];
        $mentionIds = [];
        $people = collect();
        if (str_contains($html, 'data-mention-id')) {
            $board->loadMissing(['user', 'membros.user']);
            $people = collect([$board->user])->merge($board->membros->where('status', 'accepted')->pluck('user'))->keyBy('id');
        }
        $render = function (DOMNode $node, int $depth = 0) use (&$render, &$ids, $files, $board, $card, $people, &$mentionIds): string {
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
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'video', 'audio'], true)) {
                return '';
            }
            if ($tag === 'span' && $node->hasAttribute('data-mention-id')) {
                $person = $people->get((int) $node->getAttribute('data-mention-id'));
                if ($person) {
                    $mentionIds[] = $person->id;

                    return '<span data-mention-id="'.$person->id.'" class="quadro-mention" contenteditable="false">@'.e($person->name).'</span>';
                }
            }
            if ($tag === 'img' || $tag === 'attachment') {
                $id = $node->getAttribute('data-file-id');
                $file = $files->get($id);
                if (! $file) {
                    return '';
                }
                $ids[] = $id;
                $url = route('quadros.arquivos.show', [$board, $card, $id], absolute: false);
                if ($tag === 'img' && in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                    return '<img data-file-id="'.e($id).'" src="'.e($url).'?preview=1" alt="'.e($file['name']).'" loading="lazy">';
                }

                return '<p><a data-file-id="'.e($id).'" href="'.e($url).'">'.e($file['name']).'</a></p>';
            }
            if ($tag === 'a' && $node->hasAttribute('data-file-id')) {
                $file = $files->get($node->getAttribute('data-file-id'));
                if ($file) {
                    $ids[] = $file['id'];

                    return '<a data-file-id="'.e($file['id']).'" href="'.e(route('quadros.arquivos.show', [$board, $card, $file['id']], absolute: false)).'">'.e($file['name']).'</a>';
                }
            }
            $children = '';
            foreach ($node->childNodes as $child) {
                $children .= $render($child, $depth + 1);
            }
            if ($tag === 'a') {
                $url = trim($node->getAttribute('href'));
                if (preg_match('/^(https?:\/\/|mailto:)[^\x00-\x20]+$/i', $url)) {
                    return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer nofollow">'.$children.'</a>';
                }

                return $children;
            }
            if ($tag === 'br') {
                return '<br>';
            }
            if (! in_array($tag, ['p', 'div', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'h2', 'h3'], true)) {
                return $children;
            }

            return '<'.$tag.'>'.$children.'</'.$tag.'>';
        };
        $clean = '';
        foreach ($document->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $node) {
            $clean .= $render($node);
        }
        $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '</div>', '<br>', '</li>'], "\n", $clean)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return ['html' => $clean, 'text' => $text, 'file_ids' => array_values(array_unique($ids)), 'mention_ids' => array_values(array_unique($mentionIds))];
    }
}
