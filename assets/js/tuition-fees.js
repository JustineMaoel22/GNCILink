(function () {
    'use strict';
    const data = document.getElementById('feeDocumentsData');
    if (!data) return;
    const categories = JSON.parse(data.textContent);
    const $ = id => document.getElementById(id);
    const links = Array.from(document.querySelectorAll('[data-fee-category]'));
    let selected = new URLSearchParams(location.search).get('category') || 'college';
    let selectedDocument = 0;
    if (!categories[selected]) selected = 'college';
    function show(key, documentIndex = 0, updateHistory = true) {
        if (!categories[key]) return;
        if (!Number.isInteger(documentIndex) || documentIndex < 0 || !categories[key].documents[documentIndex]) documentIndex = 0;
        selected = key;
        selectedDocument = documentIndex;
        const category = categories[key], doc = category.documents[documentIndex];
        const select = $('feeDocumentSelect');
        select.replaceChildren();
        category.documents.forEach((record, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = record.title;
            select.append(option);
        });
        select.value = String(documentIndex);
        $('feeDocumentSelector').hidden = category.documents.length < 2;
        links.forEach(link => {
            const active = link.dataset.feeCategory === key;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
        $('feeDocumentTitle').textContent = doc ? doc.title : category.label + ' — Tuition and Fees';
        $('feeDocumentMeta').textContent = doc ? 'PDF Document · ' + doc.size : 'Tuition and fee schedules';
        $('feeEmptyCategory').textContent = category.label;
        $('feeEmpty').hidden = !!doc;
        $('feePdfFrame').hidden = !doc;
        $('feeDownload').hidden = !doc;
        $('feeViewerNote').hidden = !doc;
        if (doc) {
            if ($('feePdfFrame').getAttribute('src') !== doc.url + '#view=FitH') {
                // Give each PDF a fresh native viewer and avoid iframe navigation history
                // taking over Back/Forward between document selections.
                const frame = $('feePdfFrame');
                const nextFrame = frame.cloneNode(false);
                nextFrame.src = doc.url + '#view=FitH';
                frame.replaceWith(nextFrame);
            }
            $('feeDownload').href = doc.url;
            $('feeOpenPdf').href = doc.url;
        } else {
            $('feePdfFrame').removeAttribute('src');
            $('feeDownload').removeAttribute('href');
            $('feeOpenPdf').removeAttribute('href');
        }
        if (updateHistory) {
            const url = new URL(location.href);
            url.searchParams.set('category', key);
            if (documentIndex > 0) url.searchParams.set('document', documentIndex);
            else url.searchParams.delete('document');
            history.pushState(null, '', url);
        }
        filter();
        document.dispatchEvent(new CustomEvent('tuition:changed'));
    }
    links.forEach(link => link.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault(); show(link.dataset.feeCategory);
    }));
    function filter() {
        const term = $('feeSearch').value.trim().toLocaleLowerCase();
        let matches = 0;
        links.forEach(link => {
            const category = categories[link.dataset.feeCategory];
            const match = !term || category.label.toLocaleLowerCase().includes(term)
                || category.documents.some(doc => doc.title.toLocaleLowerCase().includes(term));
            link.hidden = !match;
            if (match) matches++;
        });
        const results = $('feeDocumentList'); results.replaceChildren();
        Object.entries(categories).forEach(([key, category]) => {
            if (!term && key !== selected) return;
            category.documents.forEach((doc, index) => {
                if (term && !(category.label + ' ' + doc.title).toLocaleLowerCase().includes(term)) return;
                const button = document.createElement('button'); button.type = 'button';
                button.textContent = doc.title; button.className = 'fee-document-choice';
                if (key === selected && index === selectedDocument) button.setAttribute('aria-current', 'true');
                button.addEventListener('click', () => show(key, index)); results.append(button);
            });
        });
        results.hidden = !results.children.length;
        $('feeSearchStatus').hidden = matches > 0 || results.children.length > 0;
    }
    $('feeSearch').addEventListener('input', filter);
    $('feeDocumentSelect').addEventListener('change', event => show(selected, Number(event.target.value)));
    window.addEventListener('popstate', () => {
        const key = new URLSearchParams(location.search).get('category');
        show(categories[key] ? key : 'college', Number(new URLSearchParams(location.search).get('document') || 0), false);
    });
    show(selected, Number(new URLSearchParams(location.search).get('document') || 0), false);
    window.GNCTuitionFees = {
        categories,
        show: (key, index = 0) => show(key, index, false),
        selection: () => ({category:selected, index:selectedDocument}),
        refresh: () => show(selected, Math.min(selectedDocument, Math.max(0,categories[selected].documents.length-1)), false),
    };
})();
