<?php
/** Completeness is advisory after explicit confirmation. Callers must still
 * enforce authorization, CSRF, data types, limits, IDs and upload validation. */
function contentPublishAllowsIncomplete(): bool {
    return ($_POST['allow_incomplete'] ?? null) === '1';
}
