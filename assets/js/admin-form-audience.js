(function () {
    'use strict';
    window.GncAdminFormAudience = {
        summary: function (departments, programs) {
            const ids = departments.getSelected(), codes = programs.getSelected();
            if (!ids.length) return codes.length ? 'Only selected programs (' + codes.length + ').' : 'School-wide audience (existing default).';
            return ids.map(id => {
                const department = departments.config.items.find(item => String(item.value) === id);
                const available = programs.config.items.filter(item => String(item.departmentId) === id);
                const selected = available.filter(item => codes.includes(String(item.value)));
                return (department?.label || id) + ': ' + (!selected.length || selected.length === available.length
                    ? 'whole department' : selected.map(item => item.badge || item.label).join(', '));
            }).join('; ') + '. Other departments are excluded.';
        },
        enhance: function (pairs) {
            const el = id => document.getElementById(id);
            pairs.forEach(([id, label, placeholder]) => {
                const wrap = el(id), instance = wrap._gncMsInstance;
                if (!instance || wrap.dataset.adminAudienceEnhanced) return;
                wrap.dataset.adminAudienceEnhanced = 'true';
                instance.syncCheckboxesState();
                instance.triggerEl.setAttribute('aria-labelledby', label);
                // This is a disclosure containing native checkbox controls, rather than an ARIA listbox.
                instance.triggerEl.setAttribute('role', 'group');
                instance.triggerEl.tabIndex = -1;
                instance.triggerEl.removeAttribute('aria-expanded');
                const toggle = document.createElement('button');
                toggle.type = 'button'; toggle.className = 'evt-audience-toggle'; toggle.textContent = 'Choose';
                toggle.setAttribute('aria-label', 'Choose ' + el(label).textContent.toLowerCase());
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-controls', id + '-options');
                instance.triggerEl.appendChild(toggle);
                toggle.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') event.stopPropagation();
                    if (event.key === 'ArrowDown') { event.preventDefault(); event.stopPropagation(); instance.openDropdown(); }
                });
                instance.dropdownEl.id = id + '-options';
                instance.dropdownEl.setAttribute('role', 'group');
                instance.dropdownEl.setAttribute('aria-labelledby', label);
                instance.searchInput.placeholder = placeholder;
                instance.searchInput.setAttribute('aria-label', placeholder.replace('…', ''));
                instance.deselectAllBtn.textContent = 'Clear selection';
                instance.selectAllBtn.textContent = 'Select all';
                instance.searchInput.addEventListener('input', function () {
                    instance.selectAllBtn.textContent = instance.searchInput.value.trim() ? 'Select all matches' : 'Select all';
                });
                instance.triggerEl.addEventListener('keydown', function (event) {
                    if (event.target.closest('.gnc-ms-tag-remove')) event.stopImmediatePropagation();
                }, true);
                wrap.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && instance.isOpen) {
                        event.preventDefault(); event.stopPropagation();
                        instance.closeDropdown(); toggle.focus();
                    }
                });
                // Keep hidden checkboxes out of the tab order and accessibility tree.
                const close = instance.closeDropdown.bind(instance), open = instance.openDropdown.bind(instance);
                instance.closeDropdown = function () { close(); instance.dropdownEl.inert = true; toggle.setAttribute('aria-expanded', 'false'); instance.triggerEl.removeAttribute('aria-expanded'); };
                instance.openDropdown = function () { instance.dropdownEl.inert = false; instance.selectAllBtn.textContent = 'Select all'; open(); toggle.setAttribute('aria-expanded', 'true'); instance.triggerEl.removeAttribute('aria-expanded'); };
                instance.dropdownEl.inert = true;
                wrap.addEventListener('focusout', function () {
                    setTimeout(() => { if (!wrap.contains(document.activeElement)) instance.closeDropdown(); }, 0);
                });

            });
        }
    };
})();
