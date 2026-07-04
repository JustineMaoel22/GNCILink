
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>
/**
 * GNC Admin – Global JS Utilities
 */

// ── Confirm Delete Helper ────────────────────────────────────────
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return new Promise(resolve => {
        if (confirm(message)) resolve(true);
        else resolve(false);
    });
}

// ── Generic AJAX Form Submitter ──────────────────────────────────
async function submitForm(url, data, method = 'POST') {
    try {
        const body = new URLSearchParams({ ...data, csrf_token: CSRF_TOKEN });
        const res  = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });
        return await res.json();
    } catch (err) {
        console.error('Request failed:', err);
        return { error: 'Network error. Please try again.' };
    }
}

// ── Quill Rich Editor Initializer ───────────────────────────────
function initQuill(containerId, hiddenInputId, options = {}) {
    const quill = new Quill('#' + containerId, {
        theme: 'snow',
        placeholder: options.placeholder || 'Write content here…',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'blockquote'],
                [{ align: [] }],
                ['clean']
            ]
        }
    });

    // Sync to hidden input on form submit
    quill.on('text-change', () => {
        document.getElementById(hiddenInputId).value = quill.root.innerHTML;
    });

    return quill;
}

// ── Table Search Filter ──────────────────────────────────────────
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', () => {
        const term = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
}
</script>

<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>