<?php
/** Department targeting is optional until the additive migration is deployed. */
function eventDepartmentsReady(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { $ready = (bool)getDB()->query("SHOW COLUMNS FROM events LIKE 'dept_id'")->fetch(); }
    catch (Throwable $e) { $ready = false; }
    return $ready;
}

function normalizeEventDepartment($value): ?int {
    if ($value === null || $value === '') return null;
    if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value < 1) {
        throw new InvalidArgumentException('Choose a valid department.');
    }
    if (!eventDepartmentsReady()) throw new InvalidArgumentException('Department targeting needs the Events department migration.');
    $stmt = getDB()->prepare('SELECT dept_id FROM departments WHERE dept_id = ?');
    $stmt->execute([(int)$value]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose a valid department.');
    return (int)$value;
}

function validateEventProgramDepartment(?int $department, string $program): void {
    if ($department === null || $program === 'ALL') return;
    $stmt = getDB()->prepare('SELECT 1 FROM programs WHERE dept_id = ? AND program_code = ?');
    $stmt->execute([$department, $program]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose a program in the selected department, or leave Program on all programs.');
}
