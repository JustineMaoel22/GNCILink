(function () {
    'use strict';
    const config = document.getElementById('news-audience-config');
    if (!config) return;
    const options = JSON.parse(config.textContent);
    const departments = new GncMultiSelect(document.getElementById('news-dept-multiselect'), {
        name: 'dept_ids[]', allLabel: 'Everyone (All departments)', items: options.departments
    });
    const programs = new GncMultiSelect(document.getElementById('news-program-multiselect'), {
        name: 'programs[]', allLabel: 'Everyone (All academic programs)', items: options.programs
    });
    const summary = () => { document.getElementById('news-audience-help').textContent = GncAdminFormAudience.summary(departments, programs); };
    departments.config.onChange = programs.config.onChange = summary;
    programs.bindDepartments(departments);
    GncAdminFormAudience.enhance([
        ['news-dept-multiselect', 'news-dept-label', 'Search departments'],
        ['news-program-multiselect', 'news-program-label', 'Search programs']
    ]);
    window.GncNewsAudience = {
        set: function (audience = {}) {
            departments.setSelected(audience.dept_ids || []);
            programs.setSelected(audience.programs || []);
            summary();
        }
    };
    GncNewsAudience.set();
})();
