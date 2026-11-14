/**
 * GNC Multi-Audience Select Component
 *
 * Provides responsive multi-selection with:
 * - Search bar with instant real-time filtering
 * - Checkboxes beside each item
 * - Select All & Deselect All quick buttons
 * - Removable tags in the trigger box
 * - Form submission via hidden or standard checkboxes
 */

(function (window, document) {
    'use strict';
    let nextOptionGroupId = 0;

    class GncMultiSelect {
        constructor(wrapperEl, config) {
            this.config = Object.assign({
                name: 'items[]',
                allLabel: 'Everyone (All)',
                allValue: 'ALL',
                placeholder: 'Select...',
                items: [], // Array of { value, label, badge, search }
                initialValues: [],
                onChange: null
            }, config || {});

            this.selected = new Set();
            this.searchTerm = '';
            this.isOpen = false;
            this.optionIdPrefix = 'gnc-ms-option-' + (++nextOptionGroupId);

            if (!wrapperEl) {
                return;
            }
            this.wrapper = wrapperEl;
            this.container = wrapperEl;
            this.wrapper._gncMsInstance = this;

            this.init();
        }

        init() {
            if (!this.wrapper) return;
            this.wrapper.classList.add('gnc-ms-wrap');
            this.renderDOM();
            this.bindEvents();

            if (this.config.initialValues && this.config.initialValues.length) {
                this.setSelected(this.config.initialValues, false);
            } else {
                this.updateTags();
            }
        }

        renderDOM() {
            if (!this.wrapper) return;
            this.wrapper.innerHTML = `
                <div class="gnc-ms-trigger" tabindex="0" role="combobox" aria-expanded="false">
                    <div class="gnc-ms-tags-area"></div>
                    <span class="gnc-ms-arrow"><i class="bi bi-chevron-down"></i></span>
                </div>
                <div class="gnc-ms-dropdown" role="listbox">
                    <div class="gnc-ms-header">
                        <div class="gnc-ms-search-wrap">
                            <i class="bi bi-search"></i>
                            <input type="search" class="form-control form-control-sm gnc-ms-search-input" placeholder="Search..." autocomplete="off">
                            <button type="button" class="gnc-ms-search-clear d-none" aria-label="Clear search">&times;</button>
                        </div>
                        <div class="gnc-ms-toolbar">
                            <button type="button" class="gnc-ms-btn-action gnc-ms-btn-select-all"><i class="bi bi-check-all"></i> Select All</button>
                            <button type="button" class="gnc-ms-btn-action gnc-ms-btn-deselect"><i class="bi bi-x-lg"></i> Deselect All</button>
                        </div>
                    </div>
                    <div class="gnc-ms-list"></div>
                    <div class="gnc-ms-no-results d-none">No matches found.</div>
                </div>
            `;

            this.triggerEl = this.wrapper.querySelector('.gnc-ms-trigger');
            this.tagsAreaEl = this.wrapper.querySelector('.gnc-ms-tags-area');
            this.dropdownEl = this.wrapper.querySelector('.gnc-ms-dropdown');
            this.searchInput = this.wrapper.querySelector('.gnc-ms-search-input');
            this.searchClearBtn = this.wrapper.querySelector('.gnc-ms-search-clear');
            this.selectAllBtn = this.wrapper.querySelector('.gnc-ms-btn-select-all');
            this.deselectAllBtn = this.wrapper.querySelector('.gnc-ms-btn-deselect');
            this.listEl = this.wrapper.querySelector('.gnc-ms-list');
            this.noResultsEl = this.wrapper.querySelector('.gnc-ms-no-results');

            this.renderOptions();
        }

        renderOptions() {
            if (!this.listEl) return;
            let html = '';

            // "All" option at top
            html += `
                <label class="gnc-ms-option gnc-ms-all-option" for="${this.optionIdPrefix}-all" data-value="${this.escapeHtml(this.config.allValue)}"${this.config.hideAllOption ? ' style="display:none"' : ''}>
                    <input type="checkbox" id="${this.optionIdPrefix}-all" class="form-check-input gnc-ms-check-all" value="${this.escapeHtml(this.config.allValue)}">
                    <span class="gnc-ms-option-text"><strong>${this.escapeHtml(this.config.allLabel)}</strong></span>
                </label>
                <div class="gnc-ms-divider"${this.config.hideAllOption ? ' style="display:none"' : ''}></div>
            `;

            (this.config.items || []).forEach((item, index) => {
                const val = String(item.value);
                const badge = item.badge ? `<span class="gnc-ms-option-badge">${this.escapeHtml(item.badge)}</span>` : '';
                const searchStr = (item.search || (item.badge ? item.badge + ' ' : '') + item.label).toLowerCase();
                html += `
                    <label class="gnc-ms-option gnc-ms-item-option" for="${this.optionIdPrefix}-${index}" data-value="${this.escapeHtml(val)}" data-search="${this.escapeHtml(searchStr)}">
                        <input type="checkbox" id="${this.optionIdPrefix}-${index}" class="form-check-input gnc-ms-checkbox" name="${this.escapeHtml(this.config.name)}" value="${this.escapeHtml(val)}">
                        ${badge}
                        <span class="gnc-ms-option-text">${this.escapeHtml(item.label)}</span>
                    </label>
                `;
            });

            this.listEl.innerHTML = html;
            this.allCheckbox = this.listEl.querySelector('.gnc-ms-check-all');
            this.itemCheckboxes = this.listEl.querySelectorAll('.gnc-ms-checkbox');
            this.syncCheckboxesState();
        }

        bindEvents() {
            if (!this.triggerEl) return;

            // Keep focus inside the dropdown throughout a mouse press on a row.
            // Otherwise the admin focusout handler can close it before click fires.
            this.listEl.addEventListener('pointerdown', (event) => {
                if (event.pointerType !== 'mouse' || event.button !== 0 || event.target.closest('input')) return;
                const option = event.target.closest('.gnc-ms-option');
                const checkbox = option && option.querySelector('input[type="checkbox"]');
                if (!checkbox || checkbox.disabled) return;
                event.preventDefault();
                checkbox.focus({ preventScroll: true });
            });

            // Handle the row explicitly rather than relying on label activation.
            // Checkbox clicks retain their native behavior and must not toggle twice.
            this.listEl.addEventListener('click', (event) => {
                if (event.target.closest('input')) return;
                const option = event.target.closest('.gnc-ms-option');
                if (!option || !this.listEl.contains(option)) return;
                const checkbox = option.querySelector('input[type="checkbox"]');
                if (!checkbox || checkbox.disabled) return;
                event.preventDefault();
                checkbox.focus({ preventScroll: true });
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('input', { bubbles: true }));
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });

            // Toggle dropdown
            this.triggerEl.addEventListener('click', (e) => {
                if (e.target.closest('.gnc-ms-tag-remove')) return;
                this.toggleDropdown();
            });

            // Keyboard navigation on trigger
            this.triggerEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.openDropdown();
                } else if (e.key === 'Escape') {
                    this.closeDropdown();
                }
            });

            // Delegate so replacing program options keeps the All control working.
            if (this.listEl) {
                this.listEl.addEventListener('change', (e) => {
                    if (!e.target.matches('.gnc-ms-check-all')) return;
                    if (this.allCheckbox.checked) {
                        this.deselectAll();
                    } else if (this.selected.size === 0) {
                        this.allCheckbox.checked = true;
                    }
                });
            }

            // Individual checkboxes click
            if (this.listEl) {
                this.listEl.addEventListener('change', (e) => {
                    const cb = e.target.closest('.gnc-ms-checkbox');
                    if (!cb) return;

                    const val = cb.value;
                    if (cb.checked) {
                        this.selected.add(val);
                    } else {
                        this.selected.delete(val);
                    }

                    this.syncCheckboxesState();
                    this.updateTags();
                    this.fireChange();
                });
            }

            // Search input
            if (this.searchInput) {
                this.searchInput.addEventListener('input', () => {
                    this.filterOptions(this.searchInput.value);
                });
            }

            if (this.searchClearBtn) {
                this.searchClearBtn.addEventListener('click', () => {
                    if (this.searchInput) {
                        this.searchInput.value = '';
                        this.filterOptions('');
                        this.searchInput.focus();
                    }
                });
            }

            // Select All visible
            if (this.selectAllBtn) {
                this.selectAllBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.selectAllVisible();
                });
            }

            // Deselect All
            if (this.deselectAllBtn) {
                this.deselectAllBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.deselectAll();
                });
            }

            // Remove tag click
            if (this.tagsAreaEl) {
                this.tagsAreaEl.addEventListener('click', (e) => {
                    const btn = e.target.closest('.gnc-ms-tag-remove');
                    if (!btn) return;
                    e.stopPropagation();
                    const val = btn.dataset.value;
                    if (val) {
                        this.selected.delete(val);
                        this.syncCheckboxesState();
                        this.updateTags();
                        this.fireChange();
                    }
                });
            }

            // Click outside to close
            this.outsideClick = (e) => {
                if (this.wrapper && !this.wrapper.contains(e.target)) {
                    this.closeDropdown();
                }
            };
            document.addEventListener('click', this.outsideClick);
        }

        toggleDropdown() {
            if (this.isOpen) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        }

        openDropdown() {
            if (!this.wrapper) return;
            // Close any other open dropdowns
            document.querySelectorAll('.gnc-ms-wrap.is-open').forEach(el => {
                if (el !== this.wrapper && el._gncMsInstance) {
                    el._gncMsInstance.closeDropdown();
                }
            });

            this.isOpen = true;
            this.wrapper.classList.add('is-open');
            if (this.triggerEl) this.triggerEl.setAttribute('aria-expanded', 'true');
            if (this.searchInput) {
                this.searchInput.value = '';
                this.filterOptions('');
                setTimeout(() => this.searchInput.focus(), 50);
            }
        }

        closeDropdown() {
            if (!this.isOpen || !this.wrapper) return;
            this.isOpen = false;
            this.wrapper.classList.remove('is-open');
            if (this.triggerEl) this.triggerEl.setAttribute('aria-expanded', 'false');
        }

        filterOptions(query) {
            if (!this.listEl) return;
            this.searchTerm = (query || '').trim().toLowerCase();
            if (this.searchClearBtn) {
                this.searchClearBtn.classList.toggle('d-none', this.searchTerm === '');
            }

            let matchCount = 0;
            const options = this.listEl.querySelectorAll('.gnc-ms-item-option');
            options.forEach(opt => {
                const searchData = opt.dataset.search || '';
                const matches = !this.searchTerm || searchData.includes(this.searchTerm);
                opt.style.display = matches ? 'flex' : 'none';
                if (matches) matchCount++;
            });

            const allOpt = this.listEl.querySelector('.gnc-ms-all-option');
            if (allOpt) {
                allOpt.style.display = this.searchTerm || this.config.hideAllOption ? 'none' : 'flex';
            }

            if (this.noResultsEl) {
                this.noResultsEl.classList.toggle('d-none', matchCount > 0 || !this.searchTerm);
            }
        }

        selectAllVisible() {
            if (!this.listEl) return;
            const visibleOptions = this.listEl.querySelectorAll('.gnc-ms-item-option');
            visibleOptions.forEach(opt => {
                if (opt.style.display !== 'none') {
                    const cb = opt.querySelector('.gnc-ms-checkbox');
                    if (cb) {
                        this.selected.add(cb.value);
                    }
                }
            });

            this.syncCheckboxesState();
            this.updateTags();
            this.fireChange();
        }

        deselectAll() {
            this.selected.clear();
            this.syncCheckboxesState();
            this.updateTags();
            this.fireChange();
        }

        syncCheckboxesState() {
            if (!this.allCheckbox || !this.itemCheckboxes) return;
            const hasSelections = this.selected.size > 0;
            this.allCheckbox.checked = !hasSelections;
            this.allCheckbox.closest('.gnc-ms-option').classList.toggle('is-checked', !hasSelections);

            this.itemCheckboxes.forEach(cb => {
                const isChecked = this.selected.has(cb.value);
                cb.checked = isChecked;
                const opt = cb.closest('.gnc-ms-option');
                if (opt) opt.classList.toggle('is-checked', isChecked);
            });
        }

        updateTags() {
            if (!this.tagsAreaEl) return;
            if (this.selected.size === 0) {
                this.tagsAreaEl.innerHTML = `
                    <span class="gnc-ms-tag gnc-ms-tag-all">
                        <i class="bi bi-people me-1"></i>${this.escapeHtml(this.config.allLabel)}
                    </span>
                `;
                return;
            }

            const itemMap = new Map();
            (this.config.items || []).forEach(it => itemMap.set(String(it.value), it));

            let html = '';
            this.selected.forEach(val => {
                const item = itemMap.get(String(val));
                if (!item) return;

                const badge = item.badge ? `<span class="gnc-ms-tag-code">${this.escapeHtml(item.badge)}</span>` : '';
                const labelText = item.badge ? this.escapeHtml(item.label) : this.escapeHtml(item.label);
                const titleAttr = item.badge ? `${item.badge} — ${item.label}` : item.label;

                html += `
                    <span class="gnc-ms-tag" title="${this.escapeHtml(titleAttr)}">
                        ${badge}
                        <span>${labelText}</span>
                        <button type="button" class="gnc-ms-tag-remove" data-value="${this.escapeHtml(val)}" aria-label="Remove">&times;</button>
                    </span>
                `;
            });

            this.tagsAreaEl.innerHTML = html;
        }

        bindDepartments(departmentSelect) {
            const allItems = this.config.items.slice();
            const unrestrictedLabel = this.config.allLabel;
            this.config.restrictToItems = true;
            const previousOnChange = departmentSelect.config.onChange;
            const previousProgramOnChange = this.config.onChange;
            let previousDepartments = departmentSelect.getSelected();
            const refresh = (selectNewDepartments = false) => {
                const ids = departmentSelect.getSelected();
                this.config.allLabel = ids.length ? 'Whole selected departments' : unrestrictedLabel;
                this.config.hideAllOption = ids.length > 0;
                this.config.items = allItems.filter(item => !ids.length || ids.includes(String(item.departmentId)));
                const values = new Set(this.getSelected());
                if (selectNewDepartments) {
                    if (!ids.length) values.clear();
                    const added = ids.filter(id => !previousDepartments.includes(id));
                    this.config.items.forEach(item => {
                        if (added.includes(String(item.departmentId))) values.add(String(item.value));
                    });
                }
                this.renderOptions();
                this.setSelected([...values], false);
                this.filterOptions(this.searchInput.value);
                previousDepartments = ids;
            };
            departmentSelect.config.onChange = (values) => {
                refresh(true);
                if (typeof previousOnChange === 'function') previousOnChange(values);
            };
            this.config.onChange = (values) => {
                const ids = departmentSelect.getSelected();
                const selectedPrograms = new Set(values);
                const availableDepartments = new Set(departmentSelect.config.items.map(item => String(item.value)));
                const owners = allItems.filter(item => selectedPrograms.has(String(item.value)))
                    .map(item => String(item.departmentId)).filter(id => availableDepartments.has(id));
                const nextIds = [...new Set([...ids, ...owners])];
                if (nextIds.length !== ids.length) {
                    departmentSelect.setSelected(nextIds, false);
                    // Update the department baseline before notifying it, so adding an
                    // owner keeps just the chosen program rather than selecting its siblings.
                    refresh(false);
                    departmentSelect.fireChange();
                }
                if (typeof previousProgramOnChange === 'function') previousProgramOnChange(this.getSelected());
            };
            refresh();
        }

        setSelected(values, triggerChange = true) {
            this.selected.clear();
            const allowed = this.config.restrictToItems ? new Set(this.config.items.map(item => String(item.value))) : null;
            if (Array.isArray(values)) {
                values.forEach(v => {
                    const str = String(v);
                    if (str !== '' && str !== this.config.allValue && (!allowed || allowed.has(str))) {
                        this.selected.add(str);
                    }
                });
            } else if (values && values !== this.config.allValue && (!allowed || allowed.has(String(values)))) {
                this.selected.add(String(values));
            }

            this.syncCheckboxesState();
            this.updateTags();
            if (triggerChange) this.fireChange();
        }

        destroy() {
            document.removeEventListener('click', this.outsideClick);
            if (this.wrapper) this.wrapper._gncMsInstance = null;
        }

        getSelected() {
            return Array.from(this.selected);
        }

        fireChange() {
            if (typeof this.config.onChange === 'function') {
                this.config.onChange(this.getSelected());
            }
            if (this.wrapper) {
                this.wrapper.dispatchEvent(new CustomEvent('gnc-multiselect:change', {
                    bubbles: true,
                    detail: { selected: this.getSelected() }
                }));
            }
        }

        escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    }

    window.GncMultiSelect = GncMultiSelect;
})(window, document);
