<?php

// Drafts are private checkpoints. Only the existing publish handlers write live content.
const GNC_EDITOR_DRAFT_LIMIT = 96 * 1024 * 1024;

function editorDraftLoad(PDO $db, int $userId, string $key): ?array {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT draft_id, revision FROM content_editor_drafts WHERE user_id=? AND page_key=?' . ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId, hash('sha256', $key)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $db->commit(); return null; }
        $chunks = $db->prepare('SELECT payload FROM content_editor_draft_chunks WHERE draft_id=? ORDER BY chunk_no');
        $chunks->execute([$row['draft_id']]);
        $payload = '';
        while (($chunk = $chunks->fetchColumn()) !== false) $payload .= $chunk;
        $db->commit();
        return ['revision' => (int)$row['revision'], 'snapshot' => json_decode($payload, true, 64, JSON_THROW_ON_ERROR)];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function editorDraftSave(PDO $db, int $userId, string $key, int $revision, string $payload): int {
    if (strlen($payload) > GNC_EDITOR_DRAFT_LIMIT) throw new LengthException('This draft is too large. Save fewer media changes at a time.');
    $decoded = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || ($decoded['version'] ?? null) !== 1 || !is_array($decoded['state'] ?? null) || !is_array($decoded['preview'] ?? null)) {
        throw new InvalidArgumentException('Invalid draft. Reload the editor and try again.');
    }
    unset($decoded);
    $hash = hash('sha256', $key);
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT draft_id, revision FROM content_editor_drafts WHERE user_id=? AND page_key=?' . ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId, $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)($row['revision'] ?? 0) !== $revision) throw new RuntimeException('This draft was changed in another tab. Reopen the editor before saving again.', 409);
        if ($row) {
            $id = (int)$row['draft_id'];
            $db->prepare('UPDATE content_editor_drafts SET revision=?, updated_at=CURRENT_TIMESTAMP WHERE draft_id=?')->execute([$revision + 1, $id]);
            $db->prepare('DELETE FROM content_editor_draft_chunks WHERE draft_id=?')->execute([$id]);
        } else {
            $db->prepare('INSERT INTO content_editor_drafts (user_id,page_key,revision) VALUES (?,?,?)')->execute([$userId, $hash, 1]);
            $id = (int)$db->lastInsertId();
        }
        // Small chunks also work on hosts with a small MySQL max_allowed_packet.
        $insert = $db->prepare('INSERT INTO content_editor_draft_chunks (draft_id,chunk_no,payload) VALUES (?,?,?)');
        $length = strlen($payload);
        for ($offset = 0, $index = 0; $offset < $length; $offset += 262144, $index++) $insert->execute([$id, $index, substr($payload, $offset, 262144)]);
        $db->commit();
        return $revision + 1;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function editorDraftDelete(PDO $db, int $userId, string $key, int $revision): void {
    // Revision protects a newer draft saved from a different tab.
    $db->prepare('DELETE FROM content_editor_drafts WHERE user_id=? AND page_key=? AND revision=?')->execute([$userId, hash('sha256', $key), $revision]);
}
