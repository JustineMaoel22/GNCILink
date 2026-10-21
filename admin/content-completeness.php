<?php
/** Advisory validation of a schema describing current (including unsaved) edits. */
function contentMissingInformation(array $fields): array {
    if (count($fields) > 2000) throw new InvalidArgumentException('Too many content fields.');
    $missing = [];
    foreach ($fields as $field) {
        if (!is_array($field) || !is_string($field['id'] ?? null) || !is_string($field['label'] ?? null)
            || !is_string($field['value'] ?? null) || !is_string($field['section'] ?? null)) throw new InvalidArgumentException('Invalid content field.');
        if (!empty($field['optional']) || (isset($field['applicable']) && !$field['applicable'])) continue;
        $value = html_entity_decode(strip_tags($field['value']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $value);
        if ($value !== '') continue;
        $missing[] = ['id' => mb_substr($field['id'], 0, 100), 'section' => mb_substr($field['section'], 0, 255), 'label' => mb_substr($field['label'], 0, 255)];
    }
    return $missing;
}
