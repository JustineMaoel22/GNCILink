<?php
/** Read-only backend diagnostic. Never prints credentials or publishes/connects a Page. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/facebook-publishing.php';
try {
    facebookValidatePage();
    echo "PASS: Meta verified the configured Page token, app, permissions and Page identity. No post was sent and no connection settings were changed.\n";
} catch (FacebookPublishingError $e) {
    fwrite(STDERR, 'CHECK FAILED: ' . $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "CHECK FAILED: Backend validation could not finish. No credentials are printed.\n");
    exit(1);
}
