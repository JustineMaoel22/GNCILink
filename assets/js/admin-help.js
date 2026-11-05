(() => {
    'use strict';
    const page = document.querySelector('.help-page');
    const search = document.getElementById('help-search');
    const form = document.getElementById('help-search-form');
    const results = document.getElementById('help-search-results');
    const list = document.getElementById('help-result-list');
    const articles = [...document.querySelectorAll('[data-help-article]')];
    const links = [...document.querySelectorAll('[data-help-topic]')];
    const storageKey = `gnci-help-topic:${page.dataset.helpRole}`;
    const index = articles.map(article => ({
        id: article.dataset.helpArticle,
        title: article.querySelector('h2').textContent,
        summary: article.querySelector('.help-summary').textContent,
        text: (article.textContent + ' ' + article.dataset.helpKeywords).toLocaleLowerCase()
    }));
    const valid = id => index.some(topic => topic.id === id);
    let selected = articles.find(article => !article.hidden)?.dataset.helpArticle || 'getting-started';
    let timer;
    const remember = () => { try { sessionStorage.setItem(storageKey, selected); } catch (_) {} };
    const setUrl = push => {
        const url = new URL(location.href);
        url.searchParams.set('topic', selected);
        if (search.value.trim()) url.searchParams.set('q', search.value.trim());
        else url.searchParams.delete('q');
        url.hash = '';
        history[push ? 'pushState' : 'replaceState'](null, '', url);
    };
    function render() {
        const query = search.value.trim().toLocaleLowerCase();
        document.getElementById('help-topic-select').value = selected;
        results.hidden = !query;
        articles.forEach(article => { article.hidden = Boolean(query) || article.dataset.helpArticle !== selected; });
        links.forEach(link => {
            if (!query && link.dataset.helpTopic === selected) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        if (!query) return;
        const stop = new Set(['how','can','i','to','do','the','a','an','my','is','why','does','what','with','on','in','of','and','it','me','you','for','we']);
        const words = (query.match(/[\p{L}\p{N}]+/gu) || []).filter(word => !stop.has(word));
        const matches = words.length ? index.filter(topic => words.every(word => topic.text.includes(word))).map(topic => ({
            ...topic, score: words.reduce((score, word) => score + (topic.title.toLocaleLowerCase().includes(word) ? 6 : topic.summary.toLocaleLowerCase().includes(word) ? 2 : 1), 0)
        })).sort((a, b) => b.score - a.score) : [];
        const fragment = document.createDocumentFragment();
        matches.forEach(topic => {
            const link = document.createElement('a');
            link.className = 'help-result';
            link.dataset.helpResult = topic.id;
            const url = new URL(location.href);
            url.search = new URLSearchParams({topic: topic.id}).toString();
            url.hash = 'help-content';
            link.href = url.pathname + url.search + url.hash;
            const title = document.createElement('strong'); title.textContent = topic.title;
            const description = document.createElement('span'); description.textContent = topic.summary;
            const action = document.createElement('span'); action.className = 'help-result-action'; action.textContent = 'Read guide →';
            link.append(title, description, action);
            fragment.append(link);
        });
        list.replaceChildren(fragment);
        document.getElementById('help-no-results').hidden = matches.length !== 0;
        document.getElementById('help-result-count').textContent = `${matches.length} matching ${matches.length === 1 ? 'topic' : 'topics'}`;
    }
    function select(id, focus = true) {
        if (!valid(id)) return;
        clearTimeout(timer);
        selected = id;
        search.value = '';
        remember();setUrl(true);render();
        if (focus) {
            const heading = document.getElementById(`help-${id}`).querySelector('h2');
            heading.focus({preventScroll: true});
            document.getElementById('help-content').scrollIntoView({block: 'start', behavior: 'instant'});
        }
    }
    page.addEventListener('click', event => {
        const link = event.target.closest('[data-help-topic], [data-help-result], #help-clear-search');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        select(link.dataset.helpTopic || link.dataset.helpResult || selected);
    });
    document.getElementById('help-topic-select').addEventListener('change', event => select(event.target.value));
    page.classList.add('help-enhanced');
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {render();setUrl(false);}, 120);
    });
    form.addEventListener('submit', event => {
        event.preventDefault();clearTimeout(timer);render();setUrl(false);
        if (search.value.trim()) document.getElementById('help-results-heading').focus();
    });
    window.addEventListener('popstate', () => {
        clearTimeout(timer);
        const url = new URL(location.href);
        selected = valid(url.searchParams.get('topic')) ? url.searchParams.get('topic') : 'getting-started';
        search.value = url.searchParams.get('q') || '';
        remember();render();
    });
    const url = new URL(location.href);
    if (valid(url.searchParams.get('topic'))) selected = url.searchParams.get('topic');
    else if (!url.search) {
        try { const saved = sessionStorage.getItem(storageKey); if (valid(saved)) selected = saved; } catch (_) {}
    }
    remember();render();
})();
