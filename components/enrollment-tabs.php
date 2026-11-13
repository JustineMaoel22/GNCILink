<?php
/** Additional enrollment tabs use the existing page_sections publishing storage. */
function enrollmentTabIdentity(string $key): ?array {
    if (!preg_match('/^enrollment_tab_(level|college|basic|extra-[a-f0-9]{16})_([a-f0-9]{16})$/D', $key, $match)) return null;
    return ['parent' => $match[1], 'id' => 'extra-' . $match[2]];
}

function enrollmentExtraTabs(bool $lock = false): array {
    $query = getDB()->prepare("SELECT s.section_key,s.title,s.content FROM page_sections s JOIN pages p ON p.page_id=s.page_id WHERE p.slug=? AND s.status='published' ORDER BY s.display_order,s.section_id" . ($lock ? ' FOR UPDATE' : ''));
    $query->execute(['enrollment-procedures']);
    $tabs = []; $stepLists = [];
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (!preg_match('/^enrollment_tab_steps_([a-f0-9]{16})$/D', $row['section_key'], $match)) continue;
        $steps = json_decode($row['content'], true);
        if (is_array($steps) && array_is_list($steps)) $stepLists['extra-' . $match[1]] = $steps;
    }
    foreach ($rows as $row) {
        if (!enrollmentTabIdentity($row['section_key'])) continue;
        $identity = enrollmentTabIdentity($row['section_key']);
        $tabs[] = ['key' => $row['section_key'], 'title' => $row['title'], 'content' => sectionToEditorHtml($row['content']), 'steps' => $stepLists[$identity['id']] ?? []];
    }
    return $tabs;
}

function enrollmentTabsRevision(array $tabs): string { return hash('sha256', json_encode($tabs, JSON_THROW_ON_ERROR)); }

function enrollmentValidateTabs(array $tabs, array $existing): array {
    if (!array_is_list($tabs) || count($tabs) > 40) throw new InvalidArgumentException('You can add up to 40 enrollment tabs.');
    $clean = []; $seen = []; $levels = []; $known = array_column($existing, null, 'key');
    foreach ($tabs as $tab) {
        if (!is_array($tab) || !is_string($tab['key'] ?? null) || !($identity = enrollmentTabIdentity($tab['key'])) || isset($seen[$tab['key']]) || isset($seen[$identity['id']])) throw new InvalidArgumentException('Invalid or duplicate enrollment tab.');
        if (!is_string($tab['title'] ?? null) || trim($tab['title']) === '' || mb_strlen(trim($tab['title'])) > 150) throw new InvalidArgumentException('Enter a tab name of up to 150 characters.');
        if (!is_string($tab['content'] ?? null) || strlen($tab['content']) > 100000) throw new InvalidArgumentException('Invalid enrollment tab content.');
        $seen[$tab['key']] = $seen[$identity['id']] = true;
        if ($identity['parent'] === 'level') $levels[$identity['id']] = true;
        $steps = $tab['steps'] ?? [];
        if (!is_array($steps)) throw new InvalidArgumentException('Invalid enrollment steps.');
        $steps = basicEnrollmentValidateSteps($steps, $known[$tab['key']]['steps'] ?? []);
        $clean[] = ['key' => $tab['key'], 'title' => trim($tab['title']), 'content' => richTextToStorage($tab['content']), 'steps' => $steps];
    }
    foreach ($clean as $tab) {
        $parent = enrollmentTabIdentity($tab['key'])['parent'];
        if (str_starts_with($parent, 'extra-') && !isset($levels[$parent])) throw new InvalidArgumentException('The parent education-level tab is missing.');
    }
    foreach ($existing as $tab) if (!isset($seen[$tab['key']])) throw new InvalidArgumentException('Existing enrollment tabs must be preserved. Reload the editor.');
    return $clean;
}

/** Called inside the enrollment canvas transaction, after authorization and CSRF checks. */
function publishEnrollmentTabs(array $tabs, string $revision): void {
    if (!hasPermission('manage_page_content')) throw new InvalidArgumentException('You cannot publish enrollment tabs.');
    $db = getDB();
    $query = $db->prepare("SELECT page_id FROM pages WHERE slug=? AND status='active' FOR UPDATE");
    $query->execute(['enrollment-procedures']); $pageId = $query->fetchColumn();
    if (!$pageId) throw new InvalidArgumentException('Enrollment page not found.');
    $existing = enrollmentExtraTabs(true);
    if (!hash_equals(enrollmentTabsRevision($existing), $revision)) throw new InvalidArgumentException('Enrollment tabs changed in another window. Reload before publishing.');
    $clean = enrollmentValidateTabs($tabs, $existing);
    $known = array_column($existing, null, 'key');
    foreach ($clean as $index => $tab) {
        if (isset($known[$tab['key']])) {
            $db->prepare("UPDATE page_sections SET title=?,content=?,draft_content=NULL,status='published',review_status='none',review_note=NULL,display_order=?,updated_by=? WHERE page_id=? AND section_key=?")
                ->execute([$tab['title'], $tab['content'], 1000 + $index, $_SESSION['user_id'], $pageId, $tab['key']]);
        } else {
            $db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order,updated_by) VALUES (?,?,?,?,'published',?,?)")
                ->execute([$pageId, $tab['key'], $tab['title'], $tab['content'], 1000 + $index, $_SESSION['user_id']]);
        }
        $stepsKey = 'enrollment_tab_steps_' . substr(enrollmentTabIdentity($tab['key'])['id'], 6);
        $db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order,updated_by) VALUES (?,?,?,?,'published',?,?) ON DUPLICATE KEY UPDATE content=VALUES(content),status='published',updated_by=VALUES(updated_by)")
            ->execute([$pageId, $stepsKey, 'Enrollment tab steps', json_encode($tab['steps'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 2000 + $index, $_SESSION['user_id']]);
    }
    logActivity((int)$_SESSION['user_id'], 'PUBLISH', 'page_sections', null, 'Published enrollment tabs');
}

function enrollmentTabsFor(array $tabs, string $parent): array {
    return array_values(array_filter($tabs, static fn($tab) => enrollmentTabIdentity($tab['key'])['parent'] === $parent));
}

function enrollmentExtraTabButtons(array $tabs, string $parent): void {
    foreach (enrollmentTabsFor($tabs, $parent) as $tab) {
        $id = enrollmentTabIdentity($tab['key'])['id'];
        echo '<li class="nav-item" role="presentation"><button class="nav-link" id="' . ceAttr($id) . '-tab" data-bs-toggle="pill" data-bs-target="#' . ceAttr($id) . '-pane" type="button" role="tab" aria-controls="' . ceAttr($id) . '-pane" aria-selected="false"' . ceManageMark('data-ve-enrollment-tab-name="' . ceAttr($tab['key']) . '"') . '>' . ceAttr($tab['title']) . '</button></li>';
    }
}

function enrollmentExtraTabPanes(array $tabs, string $parent): void {
    foreach (enrollmentTabsFor($tabs, $parent) as $tab) {
        $id = enrollmentTabIdentity($tab['key'])['id'];
        echo '<div class="tab-pane fade" id="' . ceAttr($id) . '-pane" role="tabpanel" aria-labelledby="' . ceAttr($id) . '-tab" tabindex="0">';
        echo '<h2 class="' . ($parent === 'level' ? 'enrollment-level-title' : 'enrollment-pane-title') . '" data-enrollment-tab-heading="' . ceAttr($tab['key']) . '">' . ceAttr($tab['title']) . '</h2>';
        if ($parent === 'level') {
            echo '<ul class="nav nav-pills enrollment-tabs" role="tablist" aria-label="Applicant type" data-enrollment-tab-parent="' . ceAttr($id) . '">';
            echo '<li class="nav-item" role="presentation"><button class="nav-link active" id="' . ceAttr($id) . '-overview-tab" data-bs-toggle="pill" data-bs-target="#' . ceAttr($id) . '-overview-pane" type="button" role="tab" aria-controls="' . ceAttr($id) . '-overview-pane" aria-selected="true">Enrollment procedures</button></li>';
            enrollmentExtraTabButtons($tabs, $id);
            echo '</ul><div class="tab-content" data-enrollment-tab-content="' . ceAttr($id) . '"><div class="tab-pane fade show active" id="' . ceAttr($id) . '-overview-pane" role="tabpanel" aria-labelledby="' . ceAttr($id) . '-overview-tab" tabindex="0">';
        }
        echo '<div class="enrollment-custom-copy cms-rich"' . ceMark('data-ve-section="' . ceAttr($tab['key']) . '" data-ve-label="' . ceAttr($tab['title']) . ' enrollment procedures"') . '>' . renderSectionContent($tab['content']) . '</div>';
        echo '<div class="enrollment-steps-grid" data-enrollment-tab-step-grid="' . ceAttr($tab['key']) . '">';
        foreach ($tab['steps'] ?? [] as $index => $step) {
            echo '<div class="enrollment-step-card is-visible"><div class="enrollment-step-label">STEP ' . ($index + 1) . '</div><div class="enrollment-step-title">' . ceAttr($step['title']) . '</div>';
            if ($step['location'] !== '') echo '<div class="enrollment-step-sub">' . ceAttr($step['location']) . '</div>';
            if ($step['illustration'] !== '') echo '<div class="enrollment-step-icon"><img src="' . ceAttr($step['illustration']) . '" alt="' . ceAttr($step['title']) . '" width="100" height="100" loading="lazy"></div>';
            echo '<p class="enrollment-step-desc">' . nl2br(ceAttr($step['instruction'])) . '</p><div class="enrollment-step-bar enrollment-step-bar--' . ($index % 2 ? 'gold' : 'green') . '"></div></div>';
        }
        echo '</div>';
        if ($parent === 'level') {
            echo '</div>';
            enrollmentExtraTabPanes($tabs, $id);
            echo '</div>';
        }
        echo '</div>';
    }
}
