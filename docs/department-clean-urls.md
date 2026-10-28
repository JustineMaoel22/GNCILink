# Department URLs

Department details now use `/pages/academics/college/college-department/cased` instead of `college-department.php?slug=cased` or `college-department?slug=cased`. Existing GET/HEAD links redirect temporarily (302) to the clean path; other parameters, including editor preview, remain intact. POST requests retain their original behavior. The identifier in the clean path takes precedence over a conflicting query parameter.

The existing department template, authorization, published/archived rules, and database content remain unchanged. Analytics and text placements normalize the clean URL to the existing department identity. The visual editor recognizes clean department paths, including Graduate School. Legacy Basic Education and Graduate School entry points remain available.

Deploy together:

- `.htaccess`
- `pages/academics/college/college-department.php`
- `pages/academics/college/college-dept.php`
- `pages/academics/college/program.php`
- `components/department-page.php`
- `components/navbar.php`
- `components/page-analytics.php`
- `components/recommendation-catalog.php`
- `includes/text-layout.php`
- `assets/js/content-edit.js`

No database migration is needed. Local Apache checks verified clean department and program pages render through the footer, old department links redirect while preserving preview parameters, conflicting query slugs do not change the chosen department, and missing departments return 404. PHP/JavaScript syntax checks and the department/program/text-layout regression tests also passed. Production hosting still requires a deployment check.

Routing accepts `localhost` and `127.0.0.1` with optional ports in addition to the main production domain. Run `python tests/local-clean-urls-test.py` against the Docker service on port 8080 to verify actual local URLs, rather than overriding the Host header to the production domain. Subdomain routing remains excluded.
