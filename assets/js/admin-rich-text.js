(function (window, document) {
    'use strict';

    // Used by both saved and unsaved previews. Keep only the editor's safe formats.
    function previewHtml(html) {
        const template = document.createElement('template');
        template.innerHTML = html;
        const allowed = new Set(['P', 'BR', 'H1', 'H2', 'H3', 'H4', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'STRIKE', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A', 'SPAN', 'DIV', 'SUB', 'SUP']);
        for (const node of [...template.content.querySelectorAll('*')].reverse()) {
            if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'MATH', 'TEMPLATE'].includes(node.tagName)) { node.remove(); continue; }
            if (!allowed.has(node.tagName)) { node.replaceWith(...node.childNodes); continue; }
            const href = node.tagName === 'A' ? node.getAttribute('href') : null;
            const alignment = node.style.textAlign || [...node.classList].find(value => /^ql-align-(center|right|justify)$/.test(value))?.slice(9);
            for (const attribute of [...node.attributes]) node.removeAttribute(attribute.name);
            if (['P', 'H1', 'H2', 'H3', 'H4', 'LI', 'BLOCKQUOTE'].includes(node.tagName) && ['left', 'center', 'right', 'justify'].includes(alignment)) node.style.textAlign = alignment;
            if (href && /^(https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i.test(href.trim()) && !/[\x00-\x20\x7f]/.test(href.trim())) {
                node.setAttribute('href', href.trim());
                node.setAttribute('target', '_blank');
                node.setAttribute('rel', 'noopener noreferrer');
            }
        }
        return template.innerHTML;
    }

    window.GncRichText = { previewHtml };
    window.initQuill = function (containerId, hiddenInputId, options = {}) {
        const quill = new Quill('#' + containerId, {
            theme: 'snow',
            placeholder: options.placeholder || 'Write content here…',
            modules: { toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'blockquote'], [{ align: [] }], ['clean']
            ] }
        });
        const input = document.getElementById(hiddenInputId);
        const sync = () => { input.value = quill.root.innerHTML; };
        quill.on('text-change', sync);
        input.form?.addEventListener('formdata', event => {
            sync();
            event.formData.set(input.name, input.value);
        });
        // Loading via the clipboard builds Quill's document model synchronously,
        // including inline alignment, instead of mutating its managed DOM.
        quill.loadHtml = html => {
            quill.setContents(quill.clipboard.convert(html || ''), 'api');
            quill.history.clear();
            sync();
        };
        const toolbar = quill.getModule('toolbar').container;
        toolbar.addEventListener('pointerdown', event => {
            if (event.pointerType === 'mouse' && event.button === 0 && event.target.closest('button')) event.preventDefault();
        });
        const names = { bold: 'Bold', italic: 'Italic', underline: 'Underline', strike: 'Strikethrough', list: 'List', link: 'Insert or edit link', blockquote: 'Blockquote', clean: 'Clear formatting' };
        toolbar.querySelectorAll('button').forEach(button => {
            const format = [...button.classList].find(value => value.startsWith('ql-'))?.slice(3);
            const label = format === 'list' ? (button.value === 'ordered' ? 'Numbered list' : 'Bulleted list') : names[format];
            if (label) { button.setAttribute('aria-label', label); button.title = label; }
        });
        quill.on('editor-change', () => {
            toolbar.querySelectorAll('button:not(.ql-clean)').forEach(button => button.setAttribute('aria-pressed', String(button.classList.contains('ql-active'))));
        });
        sync();
        return quill;
    };
})(window, document);
