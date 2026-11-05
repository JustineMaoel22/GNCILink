(() => {
    'use strict';
    const data = JSON.parse(document.getElementById('fb-page-data').textContent);
    const root = document.querySelector('.facebook-management');
    const feedback = document.getElementById('fb-feedback');
    let busy = false;
    let categoryId = '';
    function announce(message, failed = false) {
        feedback.textContent = message;
        feedback.className = failed ? 'fb-feedback error' : 'fb-feedback';
        feedback.hidden = false;
    }
    function show(dialog, trigger) {
        dialog._trigger = trigger;
        dialog.showModal();
    }
    root.querySelectorAll('dialog').forEach(dialog => {
        dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            if (dialog._trigger?.isConnected) dialog._trigger.focus({preventScroll: true});
            else { const heading = root.querySelector('.fb-results h2'); heading.tabIndex = -1; heading.focus({preventScroll: true}); }
        });
    });
    function renderPins() {
        const count = data.pinned.length;
        document.getElementById('fb-slot-count').textContent = `${count} / ${data.limit} used`;
        const meter = root.querySelector('.fb-slot-meter');
        meter.setAttribute('aria-valuenow', count);
        [...meter.children].forEach((slot, i) => slot.classList.toggle('used', i < count));
        document.getElementById('fb-slot-help').textContent = count >= data.limit
            ? 'Slideshow full. Unpin an item below to make room for another post.'
            : `${data.limit - count} slots available. Pin a post to feature it on the homepage.`;
        root.querySelector('#fb-slideshow-manager summary span').textContent = `(${count} items)`;
        const list = document.getElementById('fb-pinned-list');
        list.replaceChildren();
        data.pinned.forEach(item => {
            const li = document.createElement('li');
            const content = document.createElement('div');
            const source = document.createElement('small');
            source.textContent = {facebook: 'Facebook', news: 'News', announcement: 'Announcement'}[item.source];
            const title = document.createElement('strong');
            title.textContent = item.title || 'Post without a caption';
            content.append(source, title); li.append(content);
            if (data.canManage) {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'btn btn-outline-secondary'; button.textContent = 'Unpin';
                button.dataset.unpinSource = item.source; button.dataset.unpinId = item.item_id;
                li.append(button);
            }
            list.append(li);
        });
        document.getElementById('fb-no-pins').hidden = count > 0;
        root.querySelectorAll('[data-post-id]').forEach(row => {
            const post = data.posts[row.dataset.postId];
            const pinned = data.pinned.some(item => item.source === 'facebook' && String(item.item_id) === row.dataset.postId);
            post.is_pinned = pinned ? 1 : 0;
            row.classList.toggle('is-pinned', pinned);
            const badge = row.querySelector('.fb-pin-status');
            badge.classList.toggle('pinned', pinned); badge.textContent = pinned ? 'Pinned' : 'Not pinned';
            const button = row.querySelector('[data-pin]');
            if (button) {
                button.querySelector('span').textContent = pinned ? 'Unpin' : 'Pin';
                button.disabled = busy || (!pinned && count >= data.limit);
                button.title = !pinned && count >= data.limit ? 'Slideshow full. Unpin an item in Manage slideshow first.' : (pinned ? 'Remove from slideshow' : 'Pin to homepage slideshow');
            }
        });
    }
    async function update(values, button) {
        if (busy) return false;
        busy = true;
        const content = [...button.childNodes];
        button.textContent = 'Saving…'; button.disabled = true;
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch('/admin/action/facebook-posts-handler.php', {
                method: 'POST', headers: {'Accept': 'application/json'},
                body: new URLSearchParams({...values, csrf_token: document.querySelector('meta[name="cms-csrf"]').content})
            });
            let result;
            try { result = await response.json(); } catch { throw new Error('Your session may have expired. Reload the page and try again.'); }
            if (!response.ok || !result.success) throw new Error(result.error || 'The change could not be saved. Please try again.');
            data.pinned = result.pinned;
            if (result.post && data.posts[values.item_id]) {
                Object.assign(data.posts[values.item_id], result.post);
                const row = [...root.querySelectorAll('[data-post-id]')].find(r => r.dataset.postId === values.item_id);
                row.querySelector('.fb-category').textContent = result.post.category_name || 'Uncategorized';
                row.querySelector('.fb-category-mode').textContent = Number(result.post.category_locked) ? 'Set manually' : 'Automatic';
            }
            announce(result.message);
            // A changed category or pin may no longer match the current filters.
            // Refresh only the cached results, retaining the form and scroll position.
            if (new URLSearchParams(location.search).get('category') || new URLSearchParams(location.search).get('pinned')) {
                try {
                    const page = await fetch(location.href, {headers: {'Accept': 'text/html'}});
                    const doc = new DOMParser().parseFromString(await page.text(), 'text/html');
                    const freshData = doc.getElementById('fb-page-data');
                    if (!page.ok || !freshData) throw new Error('Results unavailable');
                    const fresh = JSON.parse(freshData.textContent);
                    data.posts = fresh.posts; data.pinned = fresh.pinned;
                    for (const selector of ['.fb-results', '#fb-results', '.fb-pagination']) root.querySelector(selector).replaceWith(doc.querySelector(selector));
                } catch {
                    announce(`${result.message} Reload the page to update the filtered results.`);
                }
            }
            return true;
        } catch (error) {
            announce(error.message, true);
            const inline = document.querySelector('#fb-category-dialog[open] .fb-form-error');
            if (inline) { inline.textContent = error.message; inline.hidden = false; }
            return false;
        } finally {
            busy = false; button.replaceChildren(...content); button.disabled = false;
            root.removeAttribute('aria-busy'); renderPins();
        }
    }
    root.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button || busy) return;
        if (button.dataset.preview) {
            const post = data.posts[button.dataset.preview];
            document.getElementById('fb-preview-title').textContent = post.title;
            document.getElementById('fb-preview-caption').textContent = post.message || 'This post has no caption.';
            document.getElementById('fb-preview-meta').textContent = `${post.created_time} · ${post.category_name || 'Uncategorized'}`;
            document.getElementById('fb-preview-link').href = post.url;
            const images = document.getElementById('fb-preview-images'); images.replaceChildren();
            if (post.image_path) { const image = document.createElement('img'); image.src = post.image_path; image.alt = 'Saved Facebook post image'; images.append(image); }
            show(document.getElementById('fb-preview'), button);
        } else if (button.dataset.editCategory) {
            categoryId = button.dataset.editCategory;
            const post = data.posts[categoryId];
            document.getElementById('fb-category-post').textContent = post.title;
            document.getElementById('fb-category-choice').value = post.category_id || 'none';
            document.querySelector('.fb-form-error').hidden = true;
            show(document.getElementById('fb-category-dialog'), button);
        } else if (button.dataset.pin) {
            const id = button.dataset.pin;
            update({action: Number(data.posts[id].is_pinned) ? 'unpin' : 'pin', source: 'facebook', item_id: id}, button);
        } else if (button.dataset.unpinId) {
            update({action: 'unpin', source: button.dataset.unpinSource, item_id: button.dataset.unpinId}, button);
        }
    });
    document.getElementById('fb-category-form')?.addEventListener('submit', async event => {
        event.preventDefault();
        const ok = await update({action: 'category', source: 'facebook', item_id: categoryId, category_choice: document.getElementById('fb-category-choice').value}, event.submitter || event.currentTarget.querySelector('button[type="submit"]'));
        if (ok) document.getElementById('fb-category-dialog').close();
    });
})();

