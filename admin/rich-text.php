<?php
/** Shared safe rich-text storage and rendering, independent of database helpers. */
function richTextLooksLikeHtml(string $s): bool {
    return (bool)preg_match('#<(p|br|h[2-4]|strong|em|u|ul|ol|li|a|blockquote)[\s>/]#i', $s);
}

/**
 * Clean HTML from the rich-text editor down to a safe allow-list:
 * paragraphs, headings (h2-h4), bold, italic, underline, lists, links,
 * block quotes and text alignment. Everything else (scripts, styles,
 * event handlers, unknown tags, odd link types) is removed. Always run
 * this on the SERVER; never trust what the browser sends.
 */
function richTextSanitize(string $html): string {
    $html = trim($html);
    if ($html === '') return '';
    if (strlen($html) > 200000) $html = substr($html, 0, 200000);

    $doc  = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="rt-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) return '';

    $dropWithContent = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title', 'meta', 'link',
                        'noscript', 'template', 'form', 'input', 'textarea', 'select', 'button', 'audio', 'video', 'img'];
    $aligned = ['p', 'h2', 'h3', 'h4', 'blockquote', 'li'];

    $align = function (DOMElement $el): string {
        $a = '';
        if (preg_match('/text-align\s*:\s*(left|center|right|justify)\b/i', (string)$el->getAttribute('style'), $m)) {
            $a = strtolower($m[1]);
        } elseif (in_array(strtolower((string)$el->getAttribute('align')), ['left', 'center', 'right', 'justify'], true)) {
            $a = strtolower($el->getAttribute('align'));
        }
        return ($a !== '' && $a !== 'left') ? ' style="text-align:' . $a . '"' : '';
    };

    $walk = function (DOMNode $node) use (&$walk, $dropWithContent, $aligned, $align): string {
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c->nodeType === XML_TEXT_NODE) {
                $out .= htmlspecialchars((string)$c->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if ($c->nodeType !== XML_ELEMENT_NODE) continue;
            $tag = strtolower($c->nodeName);
            if (in_array($tag, $dropWithContent, true)) continue;

            if ($tag === 'br') { $out .= '<br>'; continue; }
            $inner = $walk($c);

            if ($tag === 'b') $tag = 'strong';
            if ($tag === 'i') $tag = 'em';
            if ($tag === 'div') $tag = 'p';        // browsers add <div> for new lines

            if ($tag === 'a') {
                $href = trim((string)$c->getAttribute('href'));
                $href = preg_replace('/[\x00-\x1F\x7F\s]+/', '', $href);
                if ($href === '' || !preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $href)) { $out .= $inner; continue; }
                $ext = preg_match('#^https?://#i', $href);
                $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
                      . ($ext ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $inner . '</a>';
                continue;
            }
            if (in_array($tag, ['p', 'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'li', 'strong', 'em', 'u'], true)) {
                $attr = in_array($tag, $aligned, true) ? $align($c) : '';
                $class = trim($c->getAttribute('class'));
                if (($tag === 'p' && $class === 'dept-offers-label') || ($tag === 'ul' && $class === 'dept-offers-list')) $attr .= ' class="' . $class . '"';
                $out .= '<' . $tag . $attr . '>' . $inner . '</' . $tag . '>';
                continue;
            }
            $out .= $inner;                          // unknown tag: keep its text, drop the tag
        }
        return $out;
    };

    $clean = $walk($root);
    // tidy: empty paragraphs/formatting and runs of <br>
    $clean = preg_replace('#<p(?: style="[^"]*")?>(?:\s|<br>|&nbsp;)*</p>#i', '', $clean);
    $clean = preg_replace('#<(strong|em|u)>\s*</\1>#i', '', $clean);
    return trim($clean);
}

/**
 * What gets stored in page_sections. Text with no formatting stays PLAIN
 * TEXT (paragraphs separated by a blank line), exactly as before, so pages
 * that print it as text keep working. Only text that really uses formatting
 * (bold, lists, headings, links, alignment...) is stored as clean HTML.
 */
function richTextToStorage(string $html): string {
    $clean = richTextSanitize($html);
    if ($clean === '') return '';
    if (!preg_match('#<(?!/?(?:p|br)\b)[a-z]#i', $clean) && strpos($clean, 'style=') === false) {
        $t = preg_replace('#<br\s*/?>#i', "\n", $clean);
        $t = preg_replace('#</p>\s*<p>#i', "\n\n", $t);
        $t = preg_replace('#</?p>#i', '', $t);
        return trim(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return $clean;
}
/** Preserve legacy plain text; sanitize formatting submitted by every inline editor. */
function richTextNormalize(string $value): string {
    return preg_match('/<\/?[a-z][^>]*>/i',$value)?richTextToStorage($value):trim(str_replace("\r\n","\n",$value));
}

/** Stored text (plain or HTML) -> safe HTML for the editor. */
function sectionToEditorHtml(?string $stored): string {
    $s = (string)$stored;
    if (trim($s) === '') return '';
    if (richTextLooksLikeHtml($s)) return richTextSanitize($s);
    $paras = preg_split('/\R{2,}/', trim($s)) ?: [];
    return implode('', array_map(function ($p) {
        return '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES, 'UTF-8'), false) . '</p>';
    }, $paras));
}

/**
 * Use this on PUBLIC pages wherever a section's text is printed:
 *   echo renderSectionContent(getSectionContent('vision-mission', 'vision'));
 * Plain text is escaped (line breaks kept); formatted text is printed as
 * sanitised HTML, so bold, lists, links etc. show up on the website.
 */
function renderSectionContent(?string $stored): string {
    $s = (string)$stored;
    if (trim($s) === '') return '';
    if (richTextLooksLikeHtml($s)) return richTextSanitize($s);
    return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
}

