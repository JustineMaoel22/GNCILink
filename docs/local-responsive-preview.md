# Local VS Code responsive previews

The Docker Compose web service mounts `config/apache-local-preview.conf` into Apache and enables the required rewrite/headers modules at startup. The override allows framing by the site itself and VS Code app/webview origins. Unrelated websites remain excluded by `frame-ancestors`.

After changing the Compose configuration, apply it with:

```sh
docker compose up -d --no-deps web
```

Open Responsive Viewer in VS Code, enter `http://localhost:8080/departments/cased` (or another local page), and select Retry if a previous policy error is visible.

The hosted `.htaccess` continues to send `X-Frame-Options: SAMEORIGIN` and `frame-ancestors 'self'`. cPanel does not load this Docker-only Apache include. This configuration does not enable embedding of the hosted site in external responsive checkers.

Verification: the local CASED page returned HTTP 200, Apache passed its configuration check, and the installed Responsive Viewer extension's URL/policy check accepted the local page. Interactive rendering inside VS Code must be checked in the extension.
