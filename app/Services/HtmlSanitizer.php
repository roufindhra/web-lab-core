<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's', 'code', 'pre',
        'h1', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li', 'blockquote',
        'a', 'img', 'hr',
    ];

    // isinya dibuang bersama tag-nya, bukan sekadar di-unwrap
    private const DROP_CONTENT_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'noscript'];

    /**
     * Bersihkan HTML dari editor rich text: buang tag/atribut berbahaya
     * (script, event handler, javascript: URI) sebelum disimpan dirender publik.
     */
    public function clean(string $html): string
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('__root');

        $this->prune($root);

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $doc->saveHTML($child);
        }

        return trim($inner);
    }

    private function prune(\DOMNode $node): void
    {
        $child = $node->firstChild;
        while ($child !== null) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, self::DROP_CONTENT_TAGS, true)) {
                    $node->removeChild($child);
                    $child = $next;

                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // isi teksnya dipertahankan, tag-nya dibuang
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    $child = $next;

                    continue;
                }

                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    $value = strtolower(trim((string) $attr->value));
                    $isLink = in_array($name, ['href', 'src'], true);
                    $isSafeUrl = ! preg_match('/^\s*(javascript|data|vbscript):/i', $attr->value);

                    if ($isLink ? ! $isSafeUrl : ! in_array($name, ['href', 'src', 'alt', 'title'], true)) {
                        $child->removeAttribute($attr->name);
                    }
                }
            }

            if ($child !== null) {
                $this->prune($child);
            }
            $child = $next;
        }
    }
}
