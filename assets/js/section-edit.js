const csrfToken = window.SLIDE_EDITOR_CONFIG.csrfToken;
const AJAX_URL  = window.SLIDE_EDITOR_CONFIG.ajaxUrl;

const slideList   = document.getElementById('slide-list');
const form        = document.getElementById('slide-form');
const formAlert   = document.getElementById('form-alert');
const editorEmpty = document.getElementById('editor-empty');

let currentMediaPath = null; // path of the media already saved on the server for the selected slide
let currentMediaType = 'image';
let objectUrlToRevoke = null;

/* ---------- Empty state / editor toggle ---------- */
function showEmptyState() {
    editorEmpty.style.display = 'flex';
    form.style.display = 'none';
}
function showEditor() {
    editorEmpty.style.display = 'none';
    form.style.display = 'block';
}

/* ---------- Helpers ---------- */
function showAlert(message, type = 'danger') {
    formAlert.className = `alert alert-${type} mt-3 mb-0`;
    formAlert.textContent = message;
}
function clearAlert() {
    formAlert.className = 'alert d-none mt-3 mb-0';
}

function setActiveListItem(id) {
    document.querySelectorAll('.slide-item').forEach(el => {
        el.classList.toggle('selected', el.dataset.id == id);
    });
}

function resetForm() {
    if (objectUrlToRevoke) { URL.revokeObjectURL(objectUrlToRevoke); objectUrlToRevoke = null; }

    form.reset();
    document.getElementById('f-slide-id').value = 0;
    document.getElementById('f-btn1-toggle').checked = false;
    document.getElementById('f-btn2-toggle').checked = false;
    document.getElementById('btn1-fields').style.display = 'none';
    document.getElementById('btn2-fields').style.display = 'none';
    document.getElementById('f-mobile').checked = true;
    document.getElementById('f-mobile-label').textContent = 'Show';
    document.getElementById('f-status').value = 'published';
    currentMediaPath = null;
    currentMediaType = 'image';
    document.getElementById('media-filename').textContent = 'No file selected';
    document.getElementById('media-meta').textContent = 'Image or video';
    updatePreview();
    updateCounters();
    clearAlert();
}

function loadSlideIntoForm(slide) {
    if (objectUrlToRevoke) { URL.revokeObjectURL(objectUrlToRevoke); objectUrlToRevoke = null; }

    document.getElementById('f-slide-id').value = slide.slide_id;
    document.getElementById('f-title').value = (slide.title || '').replace(/\|/g, ' ');
    document.getElementById('f-subtitle').value = slide.subtitle || '';

    const hasBtn1 = !!slide.btn1_text;
    document.getElementById('f-btn1-toggle').checked = hasBtn1;
    document.getElementById('btn1-fields').style.display = hasBtn1 ? 'block' : 'none';
    document.getElementById('f-btn1-text').value = slide.btn1_text || '';
    document.getElementById('f-btn1-link').value = slide.btn1_link || '';

    const hasBtn2 = !!slide.btn2_text;
    document.getElementById('f-btn2-toggle').checked = hasBtn2;
    document.getElementById('btn2-fields').style.display = hasBtn2 ? 'block' : 'none';
    document.getElementById('f-btn2-text').value = slide.btn2_text || '';
    document.getElementById('f-btn2-link').value = slide.btn2_link || '';

    document.getElementById('f-status').value = slide.status || 'published';
    document.getElementById('f-mobile').checked = !!Number(slide.show_on_mobile);
    document.getElementById('f-mobile-label').textContent = document.getElementById('f-mobile').checked ? 'Show' : 'Hide';

    currentMediaPath = slide.media_path;
    currentMediaType = slide.media_type;
    document.getElementById('media-filename').textContent = slide.media_path.split('/').pop();
    document.getElementById('media-meta').textContent = slide.media_type === 'video' ? 'Video' : 'Image';

    updatePreview();
    updateCounters();
    clearAlert();
    setActiveListItem(slide.slide_id);
}

async function fetchSlide(id) {
    const res = await fetch(`${AJAX_URL}?action=get&id=${id}`);
    const data = await res.json();
    if (data.error) { showAlert(data.error); return; }
    loadSlideIntoForm(data.slide);
    showEditor();
}

/* ---------- Live preview ---------- */
function updatePreview() {
    const title = document.getElementById('f-title').value;
    const sub   = document.getElementById('f-subtitle').value;
    const btn1  = document.getElementById('f-btn1-toggle').checked ? document.getElementById('f-btn1-text').value : '';
    const btn2  = document.getElementById('f-btn2-toggle').checked ? document.getElementById('f-btn2-text').value : '';

    document.getElementById('mini-title').textContent = title;
    document.getElementById('mini-sub').textContent = sub;
    document.getElementById('mini-btn1').textContent = btn1;
    document.getElementById('mini-btn2').textContent = btn2;

    const bg  = document.getElementById('mini-hero-bg');
    const vid = document.getElementById('mini-hero-video');

    if (currentMediaType === 'video' && currentMediaPath) {
        bg.style.display = 'none';
        vid.style.display = 'block';
        vid.src = currentMediaPath;
        vid.play().catch(() => {});
    } else {
        vid.style.display = 'none';
        bg.style.display = 'block';
        bg.style.backgroundImage = currentMediaPath ? `url('${currentMediaPath}')` : 'none';
    }
}

function updateCounters() {
    document.querySelectorAll('.counter').forEach(c => {
        const input = c.closest('.col-md-6, .col-md-3').querySelector('input');
        if (!input) return;
        const max = parseInt(c.dataset.max, 10);
        const len = input.value.length;
        c.textContent = `${len} / ${max}`;
        c.classList.toggle('over-limit', len >= max);
    });
}

['f-title', 'f-subtitle'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { updatePreview(); updateCounters(); });
});
['f-btn1-text'].forEach(id => document.getElementById(id).addEventListener('input', updatePreview));
['f-btn2-text'].forEach(id => document.getElementById(id).addEventListener('input', updatePreview));

document.getElementById('f-btn1-toggle').addEventListener('change', e => {
    document.getElementById('btn1-fields').style.display = e.target.checked ? 'block' : 'none';
    if (!e.target.checked) { document.getElementById('f-btn1-text').value = ''; document.getElementById('f-btn1-link').value = ''; }
    updatePreview();
});
document.getElementById('f-btn2-toggle').addEventListener('change', e => {
    document.getElementById('btn2-fields').style.display = e.target.checked ? 'block' : 'none';
    if (!e.target.checked) { document.getElementById('f-btn2-text').value = ''; document.getElementById('f-btn2-link').value = ''; }
    updatePreview();
});
document.getElementById('f-mobile').addEventListener('change', e => {
    document.getElementById('f-mobile-label').textContent = e.target.checked ? 'Show' : 'Hide';
});

/* ---------- Media upload preview ---------- */
document.getElementById('btn-upload').addEventListener('click', () => document.getElementById('f-media').click());
document.getElementById('f-media').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;

    if (objectUrlToRevoke) URL.revokeObjectURL(objectUrlToRevoke);
    const url = URL.createObjectURL(file);
    objectUrlToRevoke = url;

    currentMediaType = file.type.startsWith('video') ? 'video' : 'image';
    currentMediaPath = url;
    document.getElementById('media-filename').textContent = file.name;
    document.getElementById('media-meta').textContent = currentMediaType === 'video' ? 'Video (new upload)' : 'Image (new upload)';
    updatePreview();
});

/* ---------- Select slide from list ---------- */
slideList.addEventListener('click', e => {
    if (e.target.closest('.slide-item-menu')) return; // reserved for future per-item menu
    const item = e.target.closest('.slide-item');
    if (!item) return;
    fetchSlide(item.dataset.id);
});

/* ---------- Add new slide ---------- */
document.getElementById('btn-add-slide').addEventListener('click', () => {
    setActiveListItem(-1);
    resetForm();
    showEditor();
    document.getElementById('f-title').focus();
});
document.getElementById('btn-cancel').addEventListener('click', () => {
    const id = document.getElementById('f-slide-id').value;
    if (id && id !== '0') {
        fetchSlide(id);
    } else {
        resetForm();
        setActiveListItem(-1);
        showEmptyState();
    }
});

/* ---------- Save (create/update) ---------- */
form.addEventListener('submit', async e => {
    e.preventDefault();
    clearAlert();

    const id = document.getElementById('f-slide-id').value;
    const hasNewFile = document.getElementById('f-media').files.length > 0;
    if (id === '0' && !hasNewFile) {
        showAlert('Please upload a background image or video for the new slide.');
        return;
    }

    const fd = new FormData(form);
    fd.set('action', 'save');
    fd.set('show_on_mobile', document.getElementById('f-mobile').checked ? '1' : '0');
    if (!document.getElementById('f-btn1-toggle').checked) { fd.set('btn1_text', ''); fd.set('btn1_link', ''); }
    if (!document.getElementById('f-btn2-toggle').checked) { fd.set('btn2_text', ''); fd.set('btn2_link', ''); }

    const btnSave = document.getElementById('btn-save');
    btnSave.disabled = true; btnSave.textContent = 'Saving…';

    try {
        const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.error) { showAlert(data.error); return; }
        showAlert('Slide saved successfully.', 'success');
        await refreshList(data.slide_id);
    } catch (err) {
        showAlert('Network error. Please try again.');
    } finally {
        btnSave.disabled = false; btnSave.textContent = 'Save Changes';
    }
});

/* ---------- Delete ---------- */
document.getElementById('btn-remove-slide').addEventListener('click', async () => {
    const id = document.getElementById('f-slide-id').value;
    if (!id || id === '0') { showAlert('Select a slide to remove first.'); return; }
    if (!confirm('Remove this slide? This cannot be undone.')) return;

    const fd = new FormData();
    fd.set('action', 'delete');
    fd.set('csrf_token', csrfToken);
    fd.set('slide_id', id);

    const res = await fetch(AJAX_URL, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.error) { showAlert(data.error); return; }
    await refreshList(null);
    resetForm();
    showEmptyState();
});

/* ---------- Refresh the left-hand list from the server ---------- */
async function refreshList(selectId) {
    const res = await fetch(`${AJAX_URL}?action=list`);
    const data = await res.json();
    if (data.error) return;

    slideList.innerHTML = '';
    if (data.slides.length === 0) {
        slideList.innerHTML = '<div class="empty-state"><i class="bi bi-images"></i><p>No slides yet. Click "Add New Slide" to create one.</p></div>';
        return;
    }

    data.slides.forEach((s, i) => {
        const div = document.createElement('div');
        div.className = 'slide-item';
        div.draggable = true;
        div.dataset.id = s.slide_id;
        div.innerHTML = `
            <span class="slide-drag-handle"><i class="bi bi-grip-vertical"></i></span>
            <span class="slide-number">${i + 1}</span>
            <div class="slide-thumb">${s.media_type === 'video'
                ? '<i class="bi bi-camera-reels-fill"></i>'
                : `<img src="${s.media_path}" alt="">`}</div>
            <div class="slide-item-info">
                <div class="slide-item-title">${(s.title ? s.title.replace(/\|/g, ' ') : '(No title)')}</div>
                <div class="slide-item-sub">${s.subtitle || ''}</div>
                <span class="badge-status ${s.status}">${s.status.charAt(0).toUpperCase() + s.status.slice(1)}</span>
            </div>
            <button type="button" class="slide-item-menu" title="Options"><i class="bi bi-three-dots-vertical"></i></button>
        `;
        slideList.appendChild(div);
    });

    attachDragEvents();
    if (selectId) setActiveListItem(selectId);
}

/* ---------- Drag & drop reordering ---------- */
let draggedEl = null;

function attachDragEvents() {
    document.querySelectorAll('.slide-item').forEach(item => {
        item.addEventListener('dragstart', () => {
            draggedEl = item;
            item.classList.add('dragging');
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            draggedEl = null;
            saveOrder();
        });
        item.addEventListener('dragover', e => {
            e.preventDefault();
            const after = getDragAfterElement(slideList, e.clientY);
            if (!draggedEl) return;
            if (after == null) slideList.appendChild(draggedEl);
            else slideList.insertBefore(draggedEl, after);
        });
    });
}

function getDragAfterElement(container, y) {
    const items = [...container.querySelectorAll('.slide-item:not(.dragging)')];
    return items.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }
        return closest;
    }, { offset: -Infinity }).element;
}

async function saveOrder() {
    const ids = [...slideList.querySelectorAll('.slide-item')].map(el => el.dataset.id);
    if (ids.length === 0) return;

    slideList.querySelectorAll('.slide-item').forEach((el, i) => {
        el.querySelector('.slide-number').textContent = i + 1;
    });

    const fd = new FormData();
    fd.set('action', 'reorder');
    fd.set('csrf_token', csrfToken);
    fd.set('order', JSON.stringify(ids));
    await fetch(AJAX_URL, { method: 'POST', body: fd });
}

/* ---------- Init ---------- */
attachDragEvents();
updateCounters();
showEmptyState();