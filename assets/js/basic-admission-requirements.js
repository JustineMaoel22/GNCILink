(function () {
    'use strict';
    const root = document.querySelector('[data-basic-admission]');
    if (!root) return;
    function bindTabs(scope, buttonAttribute, panelAttribute) {
        function activate(tab, focus) {
            const group = tab.closest('[data-basic-school-panel]') || scope;
            const tabs = Array.from(group.querySelectorAll('[' + buttonAttribute + ']'));
            const panels = Array.from(group.querySelectorAll('[' + panelAttribute + ']'));
            tabs.forEach(item => {
                const active = item === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', String(active));
                item.tabIndex = active ? 0 : -1;
            });
            panels.forEach(panel => {
                const active = panel.getAttribute(panelAttribute) === tab.getAttribute(buttonAttribute);
                panel.hidden = !active;
                if (panel.classList.contains('req-panel')) panel.classList.toggle('is-active', active);
            });
            if (focus) tab.focus();
        }
        scope.addEventListener('click', event => {
            const tab = event.target.closest('[' + buttonAttribute + ']');
            if (tab && scope.contains(tab)) activate(tab, false);
        });
        scope.addEventListener('keydown', event => {
                if (event.target.closest('[data-ve-admission-label][contenteditable]:not([contenteditable="false"])')) return;
                const tab = event.target.closest('[' + buttonAttribute + ']');
                if (!tab || !scope.contains(tab)) return;
                const group = tab.closest('[data-basic-school-panel]') || scope;
                const tabs = Array.from(group.querySelectorAll('[' + buttonAttribute + ']'));
                const index = tabs.indexOf(tab);
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                if (next !== undefined) { event.preventDefault(); activate(tabs[next], true); }
        });
    }
    bindTabs(root, 'data-basic-school', 'data-basic-school-panel');
    bindTabs(root, 'data-basic-applicant', 'data-basic-applicant-panel');
})();
