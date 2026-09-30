<?php
/**
 * GNC Admin Panel - Core Functions
 * Updated to use existing media_library schema with featured_image column
 */

// ============================================================
// SESSION MANAGEMENT
// ============================================================

/**
 * Initialize secure session
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        // Regenerate session ID for security
        if (!isset($_SESSION['_initiated'])) {
            session_regenerate_id(true);
            $_SESSION['_initiated'] = true;
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Get current logged-in user
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log('Get current user error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Destroy session and logout
 */
function destroySession() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'LOGOUT', null, null, 'User logged out');
    }
    $_SESSION = [];
    session_destroy();
}

// ============================================================
// AUTHENTICATION & PERMISSIONS
// ============================================================

/**
 * Check permission based on role
 */
function hasPermission(string $permission): bool {
    if (!isLoggedIn()) return false;
    
    $role = $_SESSION['user_role'];
    $permissions = getPermissions($role);
    return in_array($permission, $permissions);
}

/**
 * Get permissions for a role
 */
function getPermissions(string $role): array {
    $permissionMap = [
        'Super Admin' => [
            'manage_users', 'add_user', 'edit_user', 'delete_user', 'assign_roles',
            'manage_announcements', 'create_announcement', 'edit_announcement', 'delete_announcement', 'publish_announcement',
            'manage_news', 'create_news', 'edit_news', 'delete_news', 'publish_news',
            'manage_events', 'create_event', 'edit_event', 'delete_event', 'publish_event',
            'approve_content', 'reject_content', 'request_revision',
            'view_logs', 'manage_approvals',
            'manage_page_content'
        ],
        'Content Admin' => [
            'create_announcement', 'edit_announcement', 'delete_announcement', 'publish_announcement',
            'create_news', 'edit_news', 'delete_news', 'publish_news',
            'create_event', 'edit_event', 'delete_event', 'publish_event',
            'approve_content', 'reject_content', 'request_revision',
            'manage_approvals',
            'manage_page_content'
        ],
        'Content Editor' => [
            'create_announcement', 'edit_own_announcement', 'delete_own_announcement',
            'create_news', 'edit_own_news', 'delete_own_news',
            'create_event', 'edit_own_event', 'delete_own_event'
        ],
        'Office Staff' => [
            'create_announcement', 'edit_own_announcement', 'delete_own_announcement'
        ]
    ];
    
    return $permissionMap[$role] ?? [];
}

/**
 * Require specific permission
 */
function requirePermission(string $permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        die(json_encode(['error' => 'Access denied. You do not have permission to perform this action.']));
    }
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

// ============================================================
// ACTIVITY LOGGING
// ============================================================

/**
 * Log user activity
 */
function logActivity(int $userId, string $action, ?string $table = null, ?int $recordId = null, ?string $description = null): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $action, $table, $recordId, $description]);
    } catch (Exception $e) {
        error_log('Log activity error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get activity logs
 */
function getActivityLogs(int $limit = 50, int $offset = 0): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT al.*, u.first_name, u.last_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.user_id
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get activity logs error: ' . $e->getMessage());
        return [];
    }
}

// ============================================================
// PROGRAM CATEGORIES
// ============================================================

/**
 * Canonical list of academic program categories an announcement can be
 * tagged with. Keys are the stored codes (announcements.program column),
 * values are the full display names. 'ALL' is a real, selectable option
 * meaning "this announcement applies to every program" — it is not the
 * same thing as an empty/unfiltered list selection in the UI.
 */
function getProgramCategories(): array {
    return [
        'ALL'     => 'All Programs',
        'BEEd'    => 'Bachelor of Elementary Education',
        'BSEd'    => 'Bachelor of Secondary Education',
        'BAEL'    => 'Bachelor of Arts in English Language',
        'BSCE'    => 'Bachelor of Science in Civil Engineering',
        'BSAIS'   => 'Bachelor of Science in Accounting Information Systems',
        'BSA'     => 'Bachelor of Science in Accountancy',
        'BSBA-FM' => 'Bachelor of Science in Business Administration major in Financial Management',
        'BSCS'    => 'Bachelor of Science in Computer Science',
        'BSIT'    => 'Bachelor of Science in Information Technology',
        'BSHM'    => 'Bachelor of Science in Hospitality Management',
        'BSTM'    => 'Bachelor of Science in Tourism Management',
        'BSMLS'   => 'Bachelor of Science in Medical Laboratory Science',
        'BSPh'    => 'Bachelor of Science in Pharmacy',
        'BSN'     => 'Bachelor of Science in Nursing',
    ];
}

/**
 * Validate/normalize a submitted program code. Falls back to 'ALL' for
 * anything missing or not on the approved list, so a tampered or stale
 * form value can never write an arbitrary string into the column.
 */
function normalizeProgramCategory(?string $code): string {
    $valid = getProgramCategories();
    return isset($valid[$code]) ? $code : 'ALL';
}

/**
 * Full display name for a stored program code (for badges, detail pages, etc).
 */
function getProgramCategoryLabel(?string $code): string {
    $valid = getProgramCategories();
    return $valid[$code] ?? $valid['ALL'];
}

// ============================================================
// DEPARTMENT CATEGORY (announcements.dept_id)
// ============================================================

/**
 * Validate a submitted department id. Returns the id if it matches a real
 * row in `departments`, otherwise null (= "All Departments" / general).
 */
function normalizeAnnouncementDept($deptId): ?int {
    $deptId = (int)$deptId;
    if ($deptId <= 0) return null;
    try {
        $stmt = getDB()->prepare("SELECT dept_id FROM departments WHERE dept_id = ?");
        $stmt->execute([$deptId]);
        return $stmt->fetchColumn() ? $deptId : null;
    } catch (Exception $e) {
        return null;
    }
}

// ============================================================
// CONTENT MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Handle an uploaded announcement image ($_FILES['image']).
 * Validates type/size, stores it under /uploads/announcements/, 
 * inserts into media_library table, and returns the media_id.
 * Returns null if no file was uploaded / on failure.
 *
 * Throws an Exception with a user-friendly message on validation failure
 */
function uploadAnnouncementImage(?array $file): ?int {
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no image provided — that's fine, it's optional
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }

    // 10MB max
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new Exception('Image is too large. Maximum size is 10MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        throw new Exception('Invalid image type. Allowed: JPG, PNG, GIF, WEBP.');
    }

    $ext = $allowed[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    $uploadDir = __DIR__ . '/../uploads/announcements/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save the uploaded image.');
    }

    $filePath = '/uploads/announcements/' . $filename;

    // Insert into media_library table
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO media_library 
            (file_name, file_type, file_size, file_path, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $filename,
            $ext,
            $file['size'],
            $filePath,
            $_SESSION['user_id'] ?? null
        ]);

        return (int)$db->lastInsertId();
    } catch (Exception $e) {
        // Clean up the uploaded file if DB insert fails
        @unlink($destination);
        throw new Exception('Failed to store image in media library: ' . $e->getMessage());
    }
}

/**
 * Create announcement - Updated to use featured_image column
 */
function createAnnouncement(array $data): ?int {
    try {
        $db = getDB();
        
        // Validate required fields
        if (empty($data['title'])) {
            throw new Exception('Title is required.');
        }
        if (empty($data['content'])) {
            throw new Exception('Content is required.');
        }
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('User session not found.');
        }
        
        $slug = generateSlug($data['title']);
        
        $status = ($_SESSION['user_role'] === 'Super Admin' || $_SESSION['user_role'] === 'Content Admin') 
                ? $data['status'] ?? 'draft' 
                : 'pending';
        
        $program = normalizeProgramCategory($data['program'] ?? null);
        $deptId  = normalizeAnnouncementDept($data['dept_id'] ?? null);

        $stmt = $db->prepare("
            INSERT INTO announcements 
            (user_id, title, slug, content, category_id, program, dept_id, featured_image, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $data['title'],
            $slug,
            $data['content'],
            $data['category_id'] ?? null,
            $program,
            $deptId,
            $data['featured_image'] ?? null,  // Use featured_image instead of media_id
            $status
        ]);
        
        if (!$result) {
            throw new Exception('Database insert failed. Please check the error logs.');
        }
        
        $id = $db->lastInsertId();
        
        if (!$id) {
            throw new Exception('Failed to retrieve announcement ID after creation.');
        }
        
        logActivity($_SESSION['user_id'], 'CREATE', 'announcements', $id, 'Created announcement: ' . $data['title']);
        
        return $id;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        error_log('Create announcement error: ' . $errorMsg);
        error_log('Create announcement data: ' . json_encode($data));
        throw $e;
    }
}

/**
 * Update announcement - Updated to use featured_image column
 */
function updateAnnouncement(int $id, array $data): bool {
    try {
        $db = getDB();
        $announcement = getAnnouncementById($id);
        
        if (!$announcement) return false;
        
        // Check ownership for Content Editors
        if ($_SESSION['user_role'] === 'Content Editor' && $announcement['user_id'] !== $_SESSION['user_id']) {
            return false;
        }
        
        $slug = generateSlug($data['title']);
        $status = isset($data['status']) ? $data['status'] : $announcement['status'];

        // Handle featured_image - only update if new media was provided or removal was requested
        $featuredImage = $announcement['featured_image'];
        if (array_key_exists('featured_image', $data)) {
            $featuredImage = $data['featured_image']; // may be null (removed) or a new media_id
        }

        $program = array_key_exists('program', $data)
            ? normalizeProgramCategory($data['program'])
            : ($announcement['program'] ?? 'ALL');

        $deptId = array_key_exists('dept_id', $data)
            ? normalizeAnnouncementDept($data['dept_id'])
            : ($announcement['dept_id'] ?? null);
        
        $stmt = $db->prepare("
            UPDATE announcements
            SET title = ?, slug = ?, content = ?, category_id = ?, program = ?, dept_id = ?, featured_image = ?, status = ?, updated_at = NOW()
            WHERE announcement_id = ?
        ");
        
        $stmt->execute([
            $data['title'],
            $slug,
            $data['content'],
            $data['category_id'] ?? null,
            $program,
            $deptId,
            $featuredImage,
            $status,
            $id
        ]);
        
        logActivity($_SESSION['user_id'], 'UPDATE', 'announcements', $id, 'Updated announcement: ' . $data['title']);
        return true;
    } catch (Exception $e) {
        error_log('Update announcement error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get announcement by ID - Updated to join media_library using featured_image
 */
function getAnnouncementById(int $id): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT a.*, u.first_name, u.last_name, c.category_name, d.dept_name,
                m.file_path as image_path, m.media_id
            FROM announcements a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN categories c ON a.category_id = c.category_id
            LEFT JOIN departments d ON a.dept_id = d.dept_id
            LEFT JOIN media_library m ON a.featured_image = m.media_id
            WHERE a.announcement_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log('Get announcement error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get announcements with filters - Updated to join media_library
 */
function getAnnouncements(array $filters = [], int $limit = 20, int $offset = 0): array {
    try {
        $db = getDB();
        
        $query = "
            SELECT a.*, u.first_name, u.last_name, c.category_name, d.dept_name,
                   m.file_path as image_path, m.media_id
            FROM announcements a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN categories c ON a.category_id = c.category_id
            LEFT JOIN departments d ON a.dept_id = d.dept_id
            LEFT JOIN media_library m ON a.featured_image = m.media_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $query .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['program'])) {
            $query .= " AND a.program = ?";
            $params[] = $filters['program'];
        }
        
        if (isset($filters['search'])) {
            // Escape LIKE wildcard characters (% _ \) in the user-supplied
            // search term so they can't widen the match pattern beyond what
            // was intended. This is always bound as a parameter (never raw
            // SQL), so it's not an injection risk either way — but leaving
            // % / _ unescaped lets a "search" turn into an unintended
            // wildcard scan.
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            // Search everything visible in the table: title, content, category,
            // department, program and author name.
            $query .= " AND (a.title LIKE ? ESCAPE '\\\\' OR a.content LIKE ? ESCAPE '\\\\' OR c.category_name LIKE ? ESCAPE '\\\\' OR d.dept_name LIKE ? ESCAPE '\\\\' OR a.program LIKE ? ESCAPE '\\\\' OR CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) LIKE ? ESCAPE '\\\\')";
            for ($i = 0; $i < 6; $i++) {
                $params[] = '%' . $escapedSearch . '%';
            }
        }
        
        if ($_SESSION['user_role'] === 'Content Editor') {
            $query .= " AND a.user_id = ?";
            $params[] = $_SESSION['user_id'];
        }
        
        $query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get announcements error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Delete announcement
 */
function deleteAnnouncement(int $id): bool {
    try {
        $db = getDB();
        $announcement = getAnnouncementById($id);
        
        if (!$announcement) return false;
        
        // Check permissions
        $isOwner = $announcement['user_id'] == $_SESSION['user_id'];
        if (!hasPermission('delete_announcement') && !(hasPermission('delete_own_announcement') && $isOwner)) {
            return false;
        }
        
        $stmt = $db->prepare("DELETE FROM announcements WHERE announcement_id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            logActivity($_SESSION['user_id'], 'DELETE', 'announcements', $id, 'Deleted announcement: ' . $announcement['title']);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log('Delete announcement error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Homepage slideshow: how many items (announcements + news, combined)
 * may be pinned at once.
 */
const MAX_PINNED_ITEMS = 3;

/**
 * How many announcements + news items are currently pinned, combined.
 * Used to enforce the MAX_PINNED_ITEMS cap before allowing a new pin.
 */
function countPinnedItems(): int {
    try {
        $db = getDB();
        $annCount = (int)$db->query("SELECT COUNT(*) FROM announcements WHERE is_pinned = 1")->fetchColumn();
        $newsCount = (int)$db->query("SELECT COUNT(*) FROM news WHERE is_pinned = 1")->fetchColumn();
        $fbCount = (int)$db->query("SELECT COUNT(*) FROM fb_posts_cache WHERE is_pinned = 1")->fetchColumn();
        return $annCount + $newsCount + $fbCount;
    } catch (Exception $e) {
        error_log('Count pinned items error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Pin an announcement to the homepage slideshow. Throws if the
 * combined pin cap (MAX_PINNED_ITEMS) has already been reached.
 */
function pinAnnouncement(int $id): bool {
    try {
        $db  = getDB();
        $ann = getAnnouncementById($id);
        if (!$ann) return false;
        if ((int)$ann['is_pinned'] === 1) return true; // already pinned, nothing to do

        if (countPinnedItems() >= MAX_PINNED_ITEMS) {
            throw new Exception('You can only pin up to ' . MAX_PINNED_ITEMS . ' announcements/news items at a time. Unpin one first.');
        }

        $stmt = $db->prepare("UPDATE announcements SET is_pinned = 1, pinned_at = NOW() WHERE announcement_id = ?");
        $stmt->execute([$id]);
        logActivity($_SESSION['user_id'], 'PIN', 'announcements', $id, 'Pinned announcement: ' . $ann['title']);
        return true;
    } catch (Exception $e) {
        error_log('Pin announcement error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Unpin an announcement from the homepage slideshow.
 */
function unpinAnnouncement(int $id): bool {
    try {
        $db  = getDB();
        $ann = getAnnouncementById($id);
        if (!$ann) return false;

        $stmt = $db->prepare("UPDATE announcements SET is_pinned = 0, pinned_at = NULL WHERE announcement_id = ?");
        $stmt->execute([$id]);
        logActivity($_SESSION['user_id'], 'UNPIN', 'announcements', $id, 'Unpinned announcement: ' . $ann['title']);
        return true;
    } catch (Exception $e) {
        error_log('Unpin announcement error: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// NEWS MANAGEMENT FUNCTIONS
// ============================================================

/**
 * Canonical list of News categories. Unlike Announcements (which pull
 * from the shared `categories` table), News uses a fixed set — stored
 * directly in news.category as a string, validated against this list,
 * the same way announcements.program is validated by
 * normalizeProgramCategory().
 */
function getNewsCategories(): array {
    return [
        'School News'   => 'School News',
        'Academic'      => 'Academic',
        'Achievements'  => 'Achievements',
        'Student Life'  => 'Student Life',
        'Updates'       => 'Updates',
        'Others'        => 'Others',
    ];
}

/**
 * Validate/normalize a submitted News category. Falls back to 'Others'
 * for anything missing or not on the approved list, so a tampered or
 * stale form value can never write an arbitrary string into the column.
 */
function normalizeNewsCategory(?string $category): string {
    $valid = getNewsCategories();
    return isset($valid[$category]) ? $category : 'Others';
}

/**
 * Handle a single uploaded news image. Validates type/size, stores it
 * under /uploads/news/, inserts into media_library, and returns the
 * media_id. Returns null if no file was given / on failure.
 *
 * Mirrors uploadAnnouncementImage() so News stays on the same
 * media_library-backed pattern as every other upload in this project.
 */
function uploadNewsImage(?array $file): ?int {
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no image provided — that's fine, it's optional
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }

    // 10MB max
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new Exception('Image is too large. Maximum size is 10MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        throw new Exception('Invalid image type. Allowed: JPG, PNG, GIF, WEBP.');
    }

    $ext = $allowed[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    $uploadDir = __DIR__ . '/../uploads/news/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save the uploaded image.');
    }

    $filePath = '/uploads/news/' . $filename;

    // Insert into media_library table
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO media_library 
            (file_name, file_type, file_size, file_path, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $filename,
            $ext,
            $file['size'],
            $filePath,
            $_SESSION['user_id'] ?? null
        ]);

        return (int)$db->lastInsertId();
    } catch (Exception $e) {
        // Clean up the uploaded file if DB insert fails
        @unlink($destination);
        throw new Exception('Failed to store image in media library: ' . $e->getMessage());
    }
}

/**
 * Handle multiple uploaded news images at once — e.g. $_FILES['images']
 * from a `<input type="file" name="images[]" multiple>` field, still in
 * PHP's native "array of arrays per key" shape.
 *
 * Uploads each file via uploadNewsImage() (so every image gets the same
 * validation and the same media_library row), skipping empty slots.
 * Returns the ordered list of new media_ids. Throws on the first
 * invalid file, same as a single upload would.
 */
function uploadNewsImages(?array $files): array {
    if (empty($files) || empty($files['name'])) {
        return [];
    }

    $mediaIds = [];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue; // empty slot — browsers can send these, just skip
        }

        $singleFile = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];

        $mediaId = uploadNewsImage($singleFile);
        if ($mediaId !== null) {
            $mediaIds[] = $mediaId;
        }
    }

    return $mediaIds;
}

/**
 * Attach already-uploaded media to a news article's image gallery
 * (news_images), appending after whatever's already there.
 */
function addNewsImages(int $newsId, array $mediaIds): bool {
    if (empty($mediaIds)) return true;

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) FROM news_images WHERE news_id = ?");
        $stmt->execute([$newsId]);
        $nextOrder = (int)$stmt->fetchColumn() + 1;

        $stmt = $db->prepare("
            INSERT INTO news_images (news_id, media_id, display_order, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        foreach ($mediaIds as $mediaId) {
            $stmt->execute([$newsId, $mediaId, $nextOrder++]);
        }

        return true;
    } catch (Exception $e) {
        error_log('Add news images error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get every gallery image attached to a news article, in display order.
 */
function getNewsImages(int $newsId): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT ni.image_id, ni.media_id, ni.display_order, m.file_path
            FROM news_images ni
            LEFT JOIN media_library m ON ni.media_id = m.media_id
            WHERE ni.news_id = ?
            ORDER BY ni.display_order ASC, ni.image_id ASC
        ");
        $stmt->execute([$newsId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get news images error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Remove one gallery image from a news article (by news_images.image_id,
 * not media_id). Leaves the underlying media_library row/file alone —
 * same "don't clean up orphaned media" behavior as the rest of this file.
 */
function removeNewsImage(int $imageId, int $newsId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM news_images WHERE image_id = ? AND news_id = ?");
        return $stmt->execute([$imageId, $newsId]);
    } catch (Exception $e) {
        error_log('Remove news image error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create a news article.
 * $data keys: title, content, author (optional byline text), category
 *             (one of getNewsCategories()), featured_image (media_id or
 *             null), status
 */
function createNews(array $data): ?int {
    try {
        $db = getDB();

        // Validate required fields
        if (empty($data['title'])) {
            throw new Exception('Title is required.');
        }
        if (empty($data['content'])) {
            throw new Exception('Content is required.');
        }
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('User session not found.');
        }

        $slug = generateSlug($data['title']);

        $status = ($_SESSION['user_role'] === 'Super Admin' || $_SESSION['user_role'] === 'Content Admin')
                ? $data['status'] ?? 'draft'
                : 'pending';

        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $category = normalizeNewsCategory($data['category'] ?? null);

        $stmt = $db->prepare("
            INSERT INTO news
            (user_id, category, title, slug, content, author, featured_image, status, published_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $result = $stmt->execute([
            $_SESSION['user_id'],
            $category,
            $data['title'],
            $slug,
            $data['content'],
            $data['author'] !== '' && $data['author'] !== null ? $data['author'] : null,
            $data['featured_image'] ?? null,
            $status,
            $publishedAt,
        ]);

        if (!$result) {
            throw new Exception('Database insert failed. Please check the error logs.');
        }

        $id = $db->lastInsertId();

        if (!$id) {
            throw new Exception('Failed to retrieve news ID after creation.');
        }

        logActivity($_SESSION['user_id'], 'CREATE', 'news', $id, 'Created news article: ' . $data['title']);

        return $id;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        error_log('Create news error: ' . $errorMsg);
        error_log('Create news data: ' . json_encode($data));
        throw $e;
    }
}

/**
 * Update a news article.
 */
function updateNews(int $id, array $data): bool {
    try {
        $db = getDB();
        $news = getNewsById($id);

        if (!$news) return false;

        // Check ownership for Content Editors
        if ($_SESSION['user_role'] === 'Content Editor' && $news['user_id'] !== $_SESSION['user_id']) {
            return false;
        }

        $slug = generateSlug($data['title']);
        $status = isset($data['status']) ? $data['status'] : $news['status'];

        // Handle featured_image - only update if new media was provided or removal was requested
        $featuredImage = $news['featured_image'];
        if (array_key_exists('featured_image', $data)) {
            $featuredImage = $data['featured_image']; // may be null (removed) or a new media_id
        }

        $author = array_key_exists('author', $data)
            ? ($data['author'] !== '' ? $data['author'] : null)
            : $news['author'];

        $category = array_key_exists('category', $data)
            ? normalizeNewsCategory($data['category'])
            : ($news['category'] ?? 'Others');

        // Set published_at the first time a status transitions to 'published';
        // leave it alone on subsequent edits so the original publish date sticks.
        $publishedAt = $news['published_at'];
        if ($status === 'published' && empty($publishedAt)) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $stmt = $db->prepare("
            UPDATE news
            SET title = ?, slug = ?, content = ?, author = ?, category = ?, featured_image = ?, status = ?, published_at = ?, updated_at = NOW()
            WHERE news_id = ?
        ");

        $stmt->execute([
            $data['title'],
            $slug,
            $data['content'],
            $author,
            $category,
            $featuredImage,
            $status,
            $publishedAt,
            $id
        ]);

        logActivity($_SESSION['user_id'], 'UPDATE', 'news', $id, 'Updated news article: ' . $data['title']);
        return true;
    } catch (Exception $e) {
        error_log('Update news error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get a news article by ID - joins media_library for the cover image and
 * users for the poster. Does NOT include the gallery — call
 * getNewsImages() separately for that.
 */
function getNewsById(int $id): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT n.*, u.first_name, u.last_name,
                m.file_path as image_path, m.media_id
            FROM news n
            LEFT JOIN users u ON n.user_id = u.user_id
            LEFT JOIN media_library m ON n.featured_image = m.media_id
            WHERE n.news_id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        error_log('Get news error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get a published news article by slug — for the public site.
 */
function getNewsBySlug(string $slug): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT n.*, u.first_name, u.last_name,
                m.file_path as image_path, m.media_id
            FROM news n
            LEFT JOIN users u ON n.user_id = u.user_id
            LEFT JOIN media_library m ON n.featured_image = m.media_id
            WHERE n.slug = ? AND n.status = 'published'
        ");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        error_log('Get news by slug error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get news articles with filters - mirrors getAnnouncements().
 */
function getNews(array $filters = [], int $limit = 20, int $offset = 0): array {
    try {
        $db = getDB();

        $query = "
            SELECT n.*, u.first_name, u.last_name,
                   m.file_path as image_path, m.media_id
            FROM news n
            LEFT JOIN users u ON n.user_id = u.user_id
            LEFT JOIN media_library m ON n.featured_image = m.media_id
            WHERE 1=1
        ";

        $params = [];

        if (isset($filters['status'])) {
            $query .= " AND n.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['category'])) {
            $query .= " AND n.category = ?";
            $params[] = $filters['category'];
        }

        if (isset($filters['search'])) {
            // Escape LIKE wildcard characters, same reasoning as getAnnouncements().
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            $query .= " AND (n.title LIKE ? ESCAPE '\\\\' OR n.content LIKE ? ESCAPE '\\\\')";
            $params[] = '%' . $escapedSearch . '%';
            $params[] = '%' . $escapedSearch . '%';
        }

        if ($_SESSION['user_role'] === 'Content Editor') {
            $query .= " AND n.user_id = ?";
            $params[] = $_SESSION['user_id'];
        }

        $query .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get news error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Delete a news article. The `news_images` gallery rows cascade-delete
 * via the FK (ON DELETE CASCADE) — see sql/add_news_gallery_and_category.sql.
 */
function deleteNews(int $id): bool {
    try {
        $db = getDB();
        $news = getNewsById($id);

        if (!$news) return false;

        // Check permissions
        $isOwner = $news['user_id'] == $_SESSION['user_id'];
        if (!hasPermission('delete_news') && !(hasPermission('delete_own_news') && $isOwner)) {
            return false;
        }

        $stmt = $db->prepare("DELETE FROM news WHERE news_id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            logActivity($_SESSION['user_id'], 'DELETE', 'news', $id, 'Deleted news article: ' . $news['title']);
        }

        return $result;
    } catch (Exception $e) {
        error_log('Delete news error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Pin a news article to the homepage slideshow. Throws if the combined
 * pin cap (MAX_PINNED_ITEMS, shared with announcements) has been reached.
 */
function pinNews(int $id): bool {
    try {
        $db   = getDB();
        $news = getNewsById($id);
        if (!$news) return false;
        if ((int)$news['is_pinned'] === 1) return true; // already pinned, nothing to do

        if (countPinnedItems() >= MAX_PINNED_ITEMS) {
            throw new Exception('You can only pin up to ' . MAX_PINNED_ITEMS . ' announcements/news items at a time. Unpin one first.');
        }

        $stmt = $db->prepare("UPDATE news SET is_pinned = 1, pinned_at = NOW() WHERE news_id = ?");
        $stmt->execute([$id]);
        logActivity($_SESSION['user_id'], 'PIN', 'news', $id, 'Pinned news article: ' . $news['title']);
        return true;
    } catch (Exception $e) {
        error_log('Pin news error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Unpin a news article from the homepage slideshow.
 */
function unpinNews(int $id): bool {
    try {
        $db   = getDB();
        $news = getNewsById($id);
        if (!$news) return false;

        $stmt = $db->prepare("UPDATE news SET is_pinned = 0, pinned_at = NULL WHERE news_id = ?");
        $stmt->execute([$id]);
        logActivity($_SESSION['user_id'], 'UNPIN', 'news', $id, 'Unpinned news article: ' . $news['title']);
        return true;
    } catch (Exception $e) {
        error_log('Unpin news error: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// FACEBOOK POSTS (cached from the Page — see getFacebookPosts() in
// index.php). These are read-only/synced, so there's no create/edit/
// delete here — just listing them for the admin panel and letting an
// admin pin/unpin one to the homepage slideshow, same as an
// announcement or news article.
// ============================================================

/**
 * List cached Facebook posts for the admin panel, newest first, with
 * their resolved image path from media_library.
 */
/**
 * Make text comparable for searching: converts the "fancy font" letters that
 * Facebook posts often use (mathematical bold/italic/script, e.g. the styled
 * ADVISORY) into plain letters, then lowercases. Uses intl's NFKC when
 * available, with a manual fallback for the mathematical alphanumeric block.
 */
function normalizeForSearch(string $text): string {
    if (class_exists('Normalizer')) {
        $n = Normalizer::normalize($text, Normalizer::FORM_KC);
        if ($n !== false) $text = $n;
    }

    // Fallback / extra safety: map U+1D400-U+1D7FF styled letters & digits to ASCII
    $text = preg_replace_callback('/[\x{1D400}-\x{1D7FF}]/u', function ($m) {
        $cp = mb_ord($m[0], 'UTF-8');
        if ($cp >= 0x1D7CE) {                       // styled digits
            return (string)(($cp - 0x1D7CE) % 10);
        }
        if ($cp <= 0x1D6A3) {                       // styled A-Z / a-z (52 per style)
            $i = ($cp - 0x1D400) % 52;
            return $i < 26 ? chr(65 + $i) : chr(97 + $i - 26);
        }
        return $m[0];
    }, $text) ?? $text;

    return mb_strtolower($text, 'UTF-8');
}

function getFacebookPostsAdminList(int $limit = 50, string $search = ''): array {
    try {
        $db = getDB();
        $searching = $search !== '';

        // When searching, scan a much larger window (not just the newest $limit)
        // and filter in PHP, because posts often use styled Unicode letters that
        // SQL LIKE can't match against plain text.
        $fetch = $searching ? 1000 : $limit;

        $stmt = $db->prepare("
            SELECT f.fb_post_id, f.message, f.permalink_url, f.created_time,
                   f.fetched_at, f.is_pinned, f.pinned_at, f.category_id, f.category_locked,
                   c.category_name, m.file_path AS image_path
            FROM fb_posts_cache f
            LEFT JOIN media_library m ON f.image_media_id = m.media_id
            LEFT JOIN categories c ON f.category_id = c.category_id
            ORDER BY f.created_time DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $fetch, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if ($searching) {
            $needle = normalizeForSearch($search);
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                $hay = ($r['message'] ?? '') . ' ' . ($r['category_name'] ?? '');
                return mb_strpos(normalizeForSearch((string)$hay), $needle) !== false;
            }));
            $rows = array_slice($rows, 0, $limit);
        }

        return $rows;
    } catch (Exception $e) {
        error_log('Get Facebook posts admin list error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Pin a cached Facebook post to the homepage slideshow. Throws if the
 * combined pin cap (MAX_PINNED_ITEMS, shared with announcements/news)
 * has already been reached.
 */
function pinFacebookPost(string $fbPostId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT is_pinned FROM fb_posts_cache WHERE fb_post_id = ?");
        $stmt->execute([$fbPostId]);
        $row = $stmt->fetch();
        if (!$row) return false;
        if ((int)$row['is_pinned'] === 1) return true; // already pinned, nothing to do

        if (countPinnedItems() >= MAX_PINNED_ITEMS) {
            throw new Exception('You can only pin up to ' . MAX_PINNED_ITEMS . ' announcements/news/Facebook posts at a time. Unpin one first.');
        }

        $stmt = $db->prepare("UPDATE fb_posts_cache SET is_pinned = 1, pinned_at = NOW() WHERE fb_post_id = ?");
        $stmt->execute([$fbPostId]);
        logActivity($_SESSION['user_id'], 'PIN', 'fb_posts_cache', null, 'Pinned Facebook post: ' . $fbPostId);
        return true;
    } catch (Exception $e) {
        error_log('Pin Facebook post error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Unpin a cached Facebook post from the homepage slideshow.
 */
function unpinFacebookPost(string $fbPostId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE fb_posts_cache SET is_pinned = 0, pinned_at = NULL WHERE fb_post_id = ?");
        $stmt->execute([$fbPostId]);
        logActivity($_SESSION['user_id'], 'UNPIN', 'fb_posts_cache', null, 'Unpinned Facebook post: ' . $fbPostId);
        return true;
    } catch (Exception $e) {
        error_log('Unpin Facebook post error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Set (or reset) the category of a cached Facebook post.
 *
 * $choice:
 *   'auto'   -> re-run the keyword auto-detection and unlock the post
 *   'none'   -> no category (locked, so auto-detection won't change it)
 *   '<id>'   -> that category_id (locked)
 * A locked post (category_locked = 1) is never changed by the Facebook sync.
 */
function setFacebookPostCategory(string $fbPostId, string $choice): bool {
    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT message FROM fb_posts_cache WHERE fb_post_id = ?");
        $stmt->execute([$fbPostId]);
        $message = $stmt->fetchColumn();
        if ($message === false) return false;

        if ($choice === 'auto') {
            require_once __DIR__ . '/../config/fb-categorizer.php';
            $catId  = gncCategoryIdForMessage((string)$message, gncLoadCategoryMap($db));
            $locked = 0;
        } elseif ($choice === 'none') {
            $catId  = null;
            $locked = 1;
        } else {
            $catId = (int)$choice;
            $chk = $db->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ?");
            $chk->execute([$catId]);
            if ($catId <= 0 || (int)$chk->fetchColumn() === 0) return false;
            $locked = 1;
        }

        $upd = $db->prepare("UPDATE fb_posts_cache SET category_id = ?, category_locked = ? WHERE fb_post_id = ?");
        $upd->execute([$catId, $locked, $fbPostId]);
        logActivity($_SESSION['user_id'], 'UPDATE', 'fb_posts_cache', null,
            'Set Facebook post category (' . $choice . '): ' . $fbPostId);
        return true;
    } catch (Exception $e) {
        error_log('Set Facebook post category error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Fetch the currently pinned announcements + news (published only),
 * merged into the same shape index.php already uses for its feed cards,
 * ordered by when each item was pinned (most recently pinned first).
 * Powers the homepage's featured slideshow (max MAX_PINNED_ITEMS items).
 */
function getPinnedFeedItems(int $limit = MAX_PINNED_ITEMS): array {
    $items = [];

    try {
        $db = getDB();

        $stmt = $db->query("
            SELECT a.title, a.slug, a.content, a.pinned_at, a.published_at, a.created_at,
                   m.file_path AS image_path
            FROM announcements a
            LEFT JOIN media_library m ON a.featured_image = m.media_id
            WHERE a.is_pinned = 1 AND a.status = 'published'
        ");
        foreach ($stmt->fetchAll() as $ann) {
            $excerpt = strip_tags($ann['content']);
            $excerpt = mb_strlen($excerpt) > 140 ? mb_substr($excerpt, 0, 140) . '…' : $excerpt;
            $items[] = [
                'source'     => 'admin',
                'type_label' => 'Announcement',
                'title'      => $ann['title'],
                'excerpt'    => $excerpt,
                'image_path' => $ann['image_path'],
                'date'       => $ann['published_at'] ?? $ann['created_at'],
                'pinned_at'  => $ann['pinned_at'],
                'author'     => null,
                'link'       => 'announcement.php?slug=' . urlencode($ann['slug']),
            ];
        }

        $stmt = $db->query("
            SELECT n.title, n.slug, n.content, n.author, n.pinned_at, n.published_at, n.created_at,
                   m.file_path AS image_path
            FROM news n
            LEFT JOIN media_library m ON n.featured_image = m.media_id
            WHERE n.is_pinned = 1 AND n.status = 'published'
        ");
        foreach ($stmt->fetchAll() as $item) {
            $excerpt = strip_tags($item['content']);
            $excerpt = mb_strlen($excerpt) > 140 ? mb_substr($excerpt, 0, 140) . '…' : $excerpt;
            $items[] = [
                'source'     => 'news',
                'type_label' => 'News',
                'title'      => $item['title'],
                'excerpt'    => $excerpt,
                'image_path' => $item['image_path'],
                'date'       => $item['published_at'] ?? $item['created_at'],
                'pinned_at'  => $item['pinned_at'],
                'author'     => $item['author'] ?? null,
                'link'       => 'news.php?slug=' . urlencode($item['slug']),
            ];
        }

        $stmt = $db->query("
            SELECT f.message, f.permalink_url, f.created_time, f.pinned_at, m.file_path AS image_path
            FROM fb_posts_cache f
            LEFT JOIN media_library m ON f.image_media_id = m.media_id
            WHERE f.is_pinned = 1
        ");
        foreach ($stmt->fetchAll() as $row) {
            $excerpt = mb_strlen($row['message']) > 140 ? mb_substr($row['message'], 0, 140) . '…' : $row['message'];
            $items[] = [
                'source'     => 'facebook',
                'type_label' => 'Announcement',
                'title'      => null,
                'excerpt'    => $excerpt,
                'image_path' => $row['image_path'],
                'date'       => $row['created_time'],
                'pinned_at'  => $row['pinned_at'],
                'author'     => null,
                'link'       => $row['permalink_url'] ?? ('https://facebook.com/' . FB_PAGE_ID),
            ];
        }

        usort($items, fn($a, $b) => strtotime($b['pinned_at']) <=> strtotime($a['pinned_at']));
    } catch (Exception $e) {
        error_log('Get pinned feed items error: ' . $e->getMessage());
    }

    return array_slice($items, 0, $limit);
}

// ============================================================
// DASHBOARD STATISTICS
// ============================================================

/**
 * Get dashboard statistics
 */
function getDashboardStats(): array {
    try {
        $db = getDB();
        
        $stats = [
            'total_announcements' => 0,
            'total_news' => 0,
            'total_events' => 0,
            'pending_approvals' => 0,
            'published_content' => 0,
            'total_users' => 0
        ];
        
        // Total announcements
        $stmt = $db->query("SELECT COUNT(*) as count FROM announcements");
        $stats['total_announcements'] = $stmt->fetch()['count'];
        
        // Total news
        $stmt = $db->query("SELECT COUNT(*) as count FROM news");
        $stats['total_news'] = $stmt->fetch()['count'];
        
        // Total events - check if table exists
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM events");
            $stats['total_events'] = $stmt->fetch()['count'];
        } catch (Exception $e) {
            $stats['total_events'] = 0;
        }
        
        // Pending approvals
        $stmt = $db->query("
            SELECT COUNT(*) as count FROM (
                SELECT announcement_id FROM announcements WHERE status = 'pending'
                UNION ALL
                SELECT news_id FROM news WHERE status = 'pending'
            ) as pending
        ");
        $stats['pending_approvals'] = $stmt->fetch()['count'];
        
        // Published content
        $stmt = $db->query("
            SELECT COUNT(*) as count FROM (
                SELECT announcement_id FROM announcements WHERE status = 'published'
                UNION ALL
                SELECT news_id FROM news WHERE status = 'published'
            ) as published
        ");
        $stats['published_content'] = $stmt->fetch()['count'];
        
        // Total users (Super Admin only)
        if ($_SESSION['user_role'] === 'Super Admin') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM users");
            $stats['total_users'] = $stmt->fetch()['count'];
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log('Get dashboard stats error: ' . $e->getMessage());
        return [];
    }
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

/**
 * Generate URL-friendly slug
 */
function generateSlug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\w\s-]/', '', $text);
    $text = preg_replace('/[\s_]+/', '-', $text);
    $text = preg_replace('/^-+|-+$/', '', $text);
    return $text;
}

/**
 * Format date
 */
function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

/**
 * Get status badge class
 */
function getStatusBadgeClass(string $status): string {
    $classes = [
        'draft' => 'badge bg-secondary',
        'pending' => 'badge bg-warning text-dark',
        'published' => 'badge bg-success',
        'rejected' => 'badge bg-danger',
        'active' => 'badge bg-success',
        'inactive' => 'badge bg-secondary'
    ];
    return $classes[$status] ?? 'badge bg-light text-dark';
}

/**
 * Sanitize input
 */
function sanitize(string $input): string {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// HOMEPAGE SLIDESHOW (Section Editor)
// ============================================================

/**
 * Handle an uploaded slide background — image OR video.
 * Validates type/size, stores it under /uploads/hero-slides/.
 * Returns ['type' => 'image'|'video', 'path' => '/uploads/hero-slides/xxx.ext'] or null if no file given.
 * Throws an Exception with a user-friendly message on validation failure.
 */
function uploadHeroMedia(?array $file, bool $imageOnly = false): ?array {
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Map PHP's upload error codes to specific, actionable messages
        // instead of a single generic "Upload failed" for every case.
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server\'s upload_max_filesize limit (see php.ini).',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the maximum size allowed by the form.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.',
        ];
        $msg = $messages[$file['error']] ?? ('Upload failed (error code ' . $file['error'] . ').');
        throw new Exception($msg);
    }

    $allowedImages = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $allowedVideos = [
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (isset($allowedImages[$mime])) {
        $type = 'image';
        $ext  = $allowedImages[$mime];
        $maxBytes = 10 * 1024 * 1024; // 10MB
    } elseif (!$imageOnly && isset($allowedVideos[$mime])) {
        $type = 'video';
        $ext  = $allowedVideos[$mime];
        $maxBytes = 50 * 1024 * 1024; // 50MB
    } else {
        throw new Exception($imageOnly
            ? 'Unsupported file type. The mobile background must be an image (JPG, PNG, GIF, WEBP).'
            : 'Unsupported file type. Allowed: JPG, PNG, GIF, WEBP, MP4, WEBM, MOV.');
    }

    if ($file['size'] > $maxBytes) {
        throw new Exception('File is too large. Maximum size is ' . ($type === 'video' ? '50MB for video' : '10MB for images') . '.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/hero-slides/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save the uploaded file.');
    }

    return ['type' => $type, 'path' => '/uploads/hero-slides/' . $filename];
}

/**
 * Get all hero slides, ordered for display.
 * Pass $publishedOnly = true for the public homepage query.
 */
function getHeroSlides(bool $publishedOnly = false): array {
    try {
        $db = getDB();
        $sql = "SELECT * FROM hero_slides";
        if ($publishedOnly) {
            $sql .= " WHERE status = 'published'";
        }
        $sql .= " ORDER BY display_order ASC, slide_id ASC";
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get hero slides error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a single hero slide by ID.
 */
function getHeroSlideById(int $id): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM hero_slides WHERE slide_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        error_log('Get hero slide error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create a new hero slide. Returns the new slide_id, or false on failure.
 * $data keys: title, subtitle, btn1_text, btn1_link, btn2_text, btn2_link, status, show_on_mobile, show_gradient
 *   show_gradient controls whether the dark gradient overlay renders on top of this slide's
 *   media on the public site (helps text stay readable). Defaults to on (1) when omitted.
 * $media: ['type' => 'image'|'video', 'path' => '...'] from uploadHeroMedia() — the desktop background.
 * $mobileMedia: optional ['type' => 'image', 'path' => '...'] — a separate, independent mobile background.
 *               Pass null when the admin didn't upload a mobile-specific image; the public site then
 *               falls back to the desktop media on mobile.
 */
function createHeroSlide(array $data, array $media, ?array $mobileMedia = null): int|false {
    try {
        $db = getDB();

        $stmt = $db->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM hero_slides");
        $nextOrder = (int)$stmt->fetch()['next_order'];

        $stmt = $db->prepare("
            INSERT INTO hero_slides
                (media_type, media_path, media_path_mobile, title, subtitle, btn1_text, btn1_link, btn2_text, btn2_link,
                 display_order, status, show_on_mobile, show_gradient, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $media['type'],
            $media['path'],
            $mobileMedia['path'] ?? null,
            $data['title'] !== '' ? $data['title'] : null,
            $data['subtitle'] !== '' ? $data['subtitle'] : null,
            $data['btn1_text'] !== '' ? $data['btn1_text'] : null,
            $data['btn1_link'] !== '' ? $data['btn1_link'] : null,
            $data['btn2_text'] !== '' ? $data['btn2_text'] : null,
            $data['btn2_link'] !== '' ? $data['btn2_link'] : null,
            $nextOrder,
            $data['status'] ?? 'published',
            !empty($data['show_on_mobile']) ? 1 : 0,
            array_key_exists('show_gradient', $data) ? (!empty($data['show_gradient']) ? 1 : 0) : 1,
            $_SESSION['user_id'] ?? null,
        ]);

        $newId = (int)$db->lastInsertId();
        logActivity($_SESSION['user_id'], 'CREATE', 'hero_slides', $newId, 'Added a new slideshow slide');
        return $newId;
    } catch (Exception $e) {
        error_log('Create hero slide error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing hero slide. $media is null if no new desktop file was uploaded
 * (keeps the existing media_path/media_type).
 *
 * The mobile image is handled entirely independently of the desktop image:
 *   - $mobileMedia !== null  → replace the mobile image with the new upload
 *   - $removeMobileMedia === true → clear the mobile image (site falls back to desktop on mobile)
 *   - otherwise → leave the existing mobile image untouched
 * Changing the desktop image never affects the mobile image, and vice versa.
 */
function updateHeroSlide(int $id, array $data, ?array $media = null, ?array $mobileMedia = null, bool $removeMobileMedia = false): bool {
    try {
        $db = getDB();
        $existing = getHeroSlideById($id);
        if (!$existing) return false;

        $mediaType = $media['type'] ?? $existing['media_type'];
        $mediaPath = $media['path'] ?? $existing['media_path'];

        if ($mobileMedia !== null) {
            $mediaPathMobile = $mobileMedia['path'];
        } elseif ($removeMobileMedia) {
            $mediaPathMobile = null;
        } else {
            $mediaPathMobile = $existing['media_path_mobile'] ?? null;
        }

        $stmt = $db->prepare("
            UPDATE hero_slides SET
                media_type = ?, media_path = ?, media_path_mobile = ?, title = ?, subtitle = ?,
                btn1_text = ?, btn1_link = ?, btn2_text = ?, btn2_link = ?,
                status = ?, show_on_mobile = ?, show_gradient = ?
            WHERE slide_id = ?
        ");
        $stmt->execute([
            $mediaType,
            $mediaPath,
            $mediaPathMobile,
            ($data['title'] ?? '') !== '' ? $data['title'] : null,
            ($data['subtitle'] ?? '') !== '' ? $data['subtitle'] : null,
            ($data['btn1_text'] ?? '') !== '' ? $data['btn1_text'] : null,
            ($data['btn1_link'] ?? '') !== '' ? $data['btn1_link'] : null,
            ($data['btn2_text'] ?? '') !== '' ? $data['btn2_text'] : null,
            ($data['btn2_link'] ?? '') !== '' ? $data['btn2_link'] : null,
            $data['status'] ?? 'published',
            !empty($data['show_on_mobile']) ? 1 : 0,
            array_key_exists('show_gradient', $data) ? (!empty($data['show_gradient']) ? 1 : 0) : (int)($existing['show_gradient'] ?? 1),
            $id,
        ]);

        // Remove the old desktop file if it was replaced — independent of the mobile file below.
        if ($media !== null && $existing['media_path'] && $existing['media_path'] !== $mediaPath) {
            $oldFile = __DIR__ . '/..' . $existing['media_path'];
            if (is_file($oldFile)) @unlink($oldFile);
        }

        // Remove the old mobile file if it was replaced or explicitly cleared — independent of desktop.
        $oldMobilePath = $existing['media_path_mobile'] ?? null;
        if ($oldMobilePath && $oldMobilePath !== $mediaPathMobile) {
            $oldMobileFile = __DIR__ . '/..' . $oldMobilePath;
            if (is_file($oldMobileFile)) @unlink($oldMobileFile);
        }

        logActivity($_SESSION['user_id'], 'UPDATE', 'hero_slides', $id, 'Updated slideshow slide');
        return true;
    } catch (Exception $e) {
        error_log('Update hero slide error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a hero slide and its uploaded media file.
 */
function deleteHeroSlide(int $id): bool {
    try {
        $db = getDB();
        $slide = getHeroSlideById($id);
        if (!$slide) return false;

        $stmt = $db->prepare("DELETE FROM hero_slides WHERE slide_id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            if (!empty($slide['media_path'])) {
                $file = __DIR__ . '/..' . $slide['media_path'];
                if (is_file($file)) @unlink($file);
            }
            if (!empty($slide['media_path_mobile'])) {
                $mobileFile = __DIR__ . '/..' . $slide['media_path_mobile'];
                if (is_file($mobileFile)) @unlink($mobileFile);
            }
            logActivity($_SESSION['user_id'], 'DELETE', 'hero_slides', $id, 'Deleted slideshow slide');
        }

        return $result;
    } catch (Exception $e) {
        error_log('Delete hero slide error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Persist a new slide order. $orderedIds is an array of slide_id values
 * in the order they should appear (index 0 = first).
 */
function reorderHeroSlides(array $orderedIds): bool {
    try {
        $db = getDB();
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE hero_slides SET display_order = ? WHERE slide_id = ?");
        foreach ($orderedIds as $position => $slideId) {
            $stmt->execute([$position + 1, (int)$slideId]);
        }

        $db->commit();
        logActivity($_SESSION['user_id'], 'UPDATE', 'hero_slides', null, 'Reordered slideshow slides');
        return true;
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        error_log('Reorder hero slides error: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// PAGE CONTENT CMS (Content Editor — page selector)
// ============================================================
// Generic Page → Section → Content structure so the Content Editor can
// manage multiple website pages from one interface. The homepage
// slideshow keeps using the dedicated hero_slides table above; this
// section powers every other editable page (Vision & Mission, About Us,
// History, etc.). Each section stores a published value plus an
// optional draft value so admins can Save → Preview → Publish.

/**
 * Get every page registered in the Content Editor's page selector.
 */
function getEditablePages(): array {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM pages WHERE status = 'active' ORDER BY display_order ASC, title ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get editable pages error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a single page's row by its slug (e.g. 'vision-mission').
 */
function getPageBySlug(string $slug): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        error_log('Get page by slug error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all editable sections for a page, in display order. Each section
 * includes both `content` (currently published) and `draft_content`
 * (unpublished edits, if any) so the editor can show "unpublished
 * changes" state.
 */
function getPageSections(string $pageSlug): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT ps.*
            FROM page_sections ps
            INNER JOIN pages p ON p.page_id = ps.page_id
            WHERE p.slug = ?
            ORDER BY ps.display_order ASC, ps.section_id ASC
        ");
        $stmt->execute([$pageSlug]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get page sections error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get one section's content by page slug + section key.
 * Pass $preferDraft = true to get the unpublished draft when one exists
 * (used for the admin's own Preview), otherwise the published value is
 * returned (used by the public site).
 */
function getSectionContent(string $pageSlug, string $sectionKey, bool $preferDraft = false): ?string {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT ps.content, ps.draft_content
            FROM page_sections ps
            INNER JOIN pages p ON p.page_id = ps.page_id
            WHERE p.slug = ? AND ps.section_key = ?
        ");
        $stmt->execute([$pageSlug, $sectionKey]);
        $row = $stmt->fetch();
        if (!$row) return null;

        if ($preferDraft && $row['draft_content'] !== null) {
            return $row['draft_content'];
        }
        return $row['content'];
    } catch (Exception $e) {
        error_log('Get section content error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Save a section's edits as a draft (does NOT touch the published
 * `content` column, so the live public site is unaffected until Publish
 * is called).
 */
function saveSectionDraft(string $pageSlug, string $sectionKey, string $content): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE page_sections ps
            INNER JOIN pages p ON p.page_id = ps.page_id
            SET ps.draft_content = ?, ps.updated_by = ?, ps.updated_at = NOW()
            WHERE p.slug = ? AND ps.section_key = ?
        ");
        $ok = $stmt->execute([$content, $_SESSION['user_id'] ?? null, $pageSlug, $sectionKey]);
        if ($ok && $stmt->rowCount() > 0) {
            logActivity($_SESSION['user_id'], 'UPDATE', 'page_sections', null, "Saved draft for {$pageSlug}/{$sectionKey}");
        }
        return $ok;
    } catch (Exception $e) {
        error_log('Save section draft error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Publish a section: copies draft_content into the live content column
 * and clears the draft. If there is no pending draft, this is a no-op
 * (returns true) since the section is already published as-is.
 */
function publishSection(string $pageSlug, string $sectionKey): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE page_sections ps
            INNER JOIN pages p ON p.page_id = ps.page_id
            SET ps.content = COALESCE(ps.draft_content, ps.content),
                ps.draft_content = NULL,
                ps.status = 'published',
                ps.updated_by = ?,
                ps.updated_at = NOW()
            WHERE p.slug = ? AND ps.section_key = ?
        ");
        $ok = $stmt->execute([$_SESSION['user_id'] ?? null, $pageSlug, $sectionKey]);
        if ($ok) {
            logActivity($_SESSION['user_id'], 'PUBLISH', 'page_sections', null, "Published {$pageSlug}/{$sectionKey}");
        }
        return $ok;
    } catch (Exception $e) {
        error_log('Publish section error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Discard the pending draft for a section, reverting the editor back to
 * the currently published value.
 */
function discardSectionDraft(string $pageSlug, string $sectionKey): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE page_sections ps
            INNER JOIN pages p ON p.page_id = ps.page_id
            SET ps.draft_content = NULL
            WHERE p.slug = ? AND ps.section_key = ?
        ");
        return $stmt->execute([$pageSlug, $sectionKey]);
    } catch (Exception $e) {
        error_log('Discard section draft error: ' . $e->getMessage());
        return false;
    }
}


// ============================================================
// COLLEGE DEPARTMENTS — CENTRALIZED LOGO + DEAN
// ============================================================
// One row in `departments` is the single source of truth for a
// department. Its logo (departments.media_id) and dean photo
// (departments.dean_media_id) point at rows in media_library, the
// same table the announcements/news uploads already use. Every public
// page (College Departments, the department's own page, Programs, ...)
// reads the department through getDepartments() / getDepartmentById(),
// so replacing a logo here changes it everywhere at once.
// (Curriculum PDFs belong to programs, not departments: see the
// COLLEGE PROGRAMS section at the end of this file.)

const DEPT_LOGO_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB
const DEPT_PDF_MAX_BYTES  = 20 * 1024 * 1024;  // 20 MB
const DEPT_DEAN_PHOTO_MAX_BYTES = 5 * 1024 * 1024; // 5 MB
const PRESIDENT_PHOTO_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

/**
 * Turn a PHP upload error code into a message an admin can act on.
 */
function departmentUploadErrorMessage(int $code, string $label): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "The {$label} is larger than the server allows.";
        case UPLOAD_ERR_PARTIAL:
            return "The {$label} was only partly uploaded. Please try again.";
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return "The server could not save the {$label}. Please contact the site administrator.";
        default:
            return "The {$label} upload failed. Please try again.";
    }
}

/**
 * Make an uploaded SVG safe to host, in place. SVG is XML that can carry
 * scripts, so this parses it and strips everything that can execute or
 * phone home: <script>, <foreignObject>, animation elements that can rewrite
 * links, on* event attributes, non-local hrefs, and risky CSS. Purely
 * visual content (paths, shapes, gradients, embedded PNG/JPEG data, styles)
 * is left alone. Returns false when the file isn't a usable SVG.
 */
function sanitizeSvgFile(string $path): bool {
    if (!class_exists('DOMDocument')) {
        // PHP's DOM/XML extension is missing: refuse SVGs (upload a PNG or WEBP
        // instead) rather than store one we couldn't clean.
        error_log('SVG upload refused: PHP DOM extension (php-xml) is not enabled.');
        return false;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '' || stripos($raw, '<!ENTITY') !== false) {
        return false; // empty, unreadable, or an entity-expansion (XXE / "billion laughs") attempt
    }

    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument();
    $ok   = $doc->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS); // no LIBXML_NOENT: entities are never expanded
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$ok || !$doc->documentElement || strtolower($doc->documentElement->localName) !== 'svg') {
        return false;
    }
    if ($doc->doctype) {
        $doc->removeChild($doc->doctype);
    }

    $blockedTags = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video', 'canvas', 'animate', 'set', 'handler', 'listener'];
    $badCss      = '/@import|expression\s*\(|javascript:|behavior\s*:|-moz-binding|url\(\s*[\'"]?\s*(?!#|data:image\/(?:png|jpe?g|gif|webp))/i';

    $nodes = [];
    foreach ($doc->getElementsByTagName('*') as $n) {
        $nodes[] = $n;
    }
    foreach ($nodes as $node) {
        $tag = strtolower($node->localName);

        if (in_array($tag, $blockedTags, true)) {
            if ($node->parentNode) $node->parentNode->removeChild($node);
            continue;
        }
        if ($tag === 'style' && preg_match($badCss, $node->textContent)) {
            if ($node->parentNode) $node->parentNode->removeChild($node);
            continue;
        }

        $attrs = [];
        foreach ($node->attributes as $a) {
            $attrs[] = $a;
        }
        foreach ($attrs as $a) {
            $name  = strtolower($a->localName);
            $value = trim($a->value);

            if (strpos($name, 'on') === 0) {                       // onload=, onclick=, ...
                $node->removeAttributeNode($a);
            } elseif ($name === 'href') {                          // href / xlink:href
                $local = ($value !== '' && $value[0] === '#');
                $img   = (bool)preg_match('#^data:image/(?:png|jpe?g|gif|webp);base64,#i', $value);
                if (!$local && !$img) $node->removeAttributeNode($a);
            } elseif ($name === 'style' && preg_match($badCss, $value)) {
                $node->removeAttributeNode($a);
            }
        }
    }

    $clean = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $doc->saveXML($doc->documentElement);
    return @file_put_contents($path, $clean) !== false;
}

/**
 * Validate and store one department upload (a logo image or a PDF),
 * insert it into media_library, and return
 * ['media_id' => int, 'path' => '/uploads/...', 'abs' => absolute path].
 * Throws an Exception with a user-friendly message on any failure.
 * Nothing is left behind on failure (no orphan file, no orphan row).
 *
 * @param string $kind 'logo' or 'pdf'
 */
function storeDepartmentUpload(array $file, string $kind): array {
    $configs = [
        'logo' => [
            'label' => 'logo image',
            'max'   => DEPT_LOGO_MAX_BYTES,
            'dir'   => 'departments',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
            'bad'   => 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP, SVG.',
        ],
        'pdf' => [
            'label' => 'PDF',
            'max'   => DEPT_PDF_MAX_BYTES,
            'dir'   => 'program-pdfs',
            'mimes' => ['application/pdf' => 'pdf'],
            'bad'   => 'Invalid file. Only PDF files (.pdf) can be uploaded here.',
        ],
        'dean_photo' => [
            'label' => 'dean photo',
            'max'   => DEPT_DEAN_PHOTO_MAX_BYTES,
            'dir'   => 'department-deans',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            'bad'   => 'Invalid image type. Allowed: JPG, PNG, WEBP.',
        ],
        'president_photo' => [
            'label' => 'president photo',
            'max'   => PRESIDENT_PHOTO_MAX_BYTES,
            'dir'   => 'homepage',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            'bad'   => 'Invalid image type. Allowed: JPG, PNG, WEBP.',
        ],
    ];
    if (!isset($configs[$kind])) {
        throw new Exception('Unknown upload type.');
    }
    $cfg = $configs[$kind];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception(departmentUploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE), $cfg['label']));
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception("The {$cfg['label']} upload failed. Please try again.");
    }
    if ($file['size'] > $cfg['max']) {
        $mb = (int)round($cfg['max'] / 1048576);
        throw new Exception("The {$cfg['label']} is too large. Maximum size is {$mb}MB.");
    }

    $origExt = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

    if ($kind === 'logo' && $origExt === 'svg') {
        // SVG is checked and cleaned by parsing it (finfo can't be trusted to
        // recognise every valid SVG), then stored as a plain .svg.
        if (!sanitizeSvgFile($file['tmp_name'])) {
            throw new Exception('Invalid SVG file. Please export it again from your design tool and try once more.');
        }
        $ext = 'svg';
    } else {
        // Trust the file's real content, never the browser-supplied type/extension.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!isset($cfg['mimes'][$mime])) {
            throw new Exception($cfg['bad']);
        }

        if ($kind === 'pdf') {
            // Belt and braces: a real PDF starts with "%PDF-" (a few readers
            // tolerate leading junk, so look in the first KB) and must be named .pdf.
            $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 1024);
            if (strpos($head, '%PDF-') === false || $origExt !== 'pdf') {
                throw new Exception($cfg['bad']);
            }
        } else {
            // Must decode as an actual image, not just carry an image header.
            if (@getimagesize($file['tmp_name']) === false) {
                throw new Exception($cfg['bad']);
            }
        }

        $ext = $cfg['mimes'][$mime];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    $uploadDir = __DIR__ . '/../uploads/' . $cfg['dir'] . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception("The server could not create the upload folder for the {$cfg['label']}.");
    }

    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Failed to save the uploaded {$cfg['label']}.");
    }

    // Keep the admin's original filename for display (sanitized); the file
    // on disk always gets a random name so nothing can collide or be guessed.
    $display = trim(preg_replace('/[^\p{L}\p{N}\s._()\-]+/u', '', basename((string)($file['name'] ?? ''))));
    $display = mb_substr($display, 0, 200);
    if ($display === '') {
        $display = $filename;
    }

    $filePath = '/uploads/' . $cfg['dir'] . '/' . $filename;

    try {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO media_library (file_name, file_type, file_size, file_path, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$display, $ext, (int)$file['size'], $filePath, $_SESSION['user_id'] ?? null]);

        return [
            'media_id' => (int)$db->lastInsertId(),
            'path'     => $filePath,
            'abs'      => $destination,
        ];
    } catch (Exception $e) {
        @unlink($destination);
        error_log('Department upload media_library insert error: ' . $e->getMessage());
        throw new Exception("Failed to record the {$cfg['label']} in the media library.");
    }
}

/**
 * Undo storeDepartmentUpload() for a file that was stored but never
 * attached to a department (used when a later step fails).
 */
function discardStoredDepartmentUpload(?array $stored): void {
    if (!$stored) return;
    try {
        $stmt = getDB()->prepare("DELETE FROM media_library WHERE media_id = ?");
        $stmt->execute([$stored['media_id']]);
    } catch (Exception $e) {
        error_log('Discard stored upload error: ' . $e->getMessage());
    }
    if (!empty($stored['abs'])) {
        @unlink($stored['abs']);
    }
}

/**
 * Delete a media_library row and its file on disk — but ONLY if nothing
 * else still points at it (announcements, events, news, offices, other
 * departments, ...). Returns true if it was deleted. Any doubt (a lookup
 * error, an unknown path) means "keep it": leaving one orphan file is
 * always safer than breaking another page's image.
 */
function deleteMediaIfUnreferenced(int $mediaId): bool {
    $references = [
        ['announcements',  'featured_image'],
        ['events',         'media_id'],
        ['fb_posts_cache', 'image_media_id'],
        ['news',           'featured_image'],
        ['news_images',    'media_id'],
        ['offices',        'media_id'],
        ['page_sections',  'media_id'],
        ['departments',    'media_id'],
        ['programs',       'pdf_media_id'],
        ['departments',    'dean_media_id'],
        ['homepage_president', 'media_id'],
    ];

    try {
        $db = getDB();
        foreach ($references as [$table, $column]) {
            $stmt = $db->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
            $stmt->execute([$mediaId]);
            if ($stmt->fetchColumn() !== false) {
                return false; // still in use somewhere
            }
        }

        $stmt = $db->prepare("SELECT file_path FROM media_library WHERE media_id = ?");
        $stmt->execute([$mediaId]);
        $path = $stmt->fetchColumn();

        $stmt = $db->prepare("DELETE FROM media_library WHERE media_id = ?");
        $stmt->execute([$mediaId]);

        if ($path) {
            // Only ever unlink files that really live inside /uploads.
            $uploadsRoot = realpath(__DIR__ . '/../uploads');
            $abs         = realpath(__DIR__ . '/..' . $path);
            if ($uploadsRoot && $abs && strpos($abs, $uploadsRoot . DIRECTORY_SEPARATOR) === 0) {
                @unlink($abs);
            }
        }
        return true;
    } catch (Exception $e) {
        error_log('Delete media error: ' . $e->getMessage());
        return false;
    }
}

/**
 * True when a stored /uploads/... (or bundled /assets/images/logos/...) path exists on disk. Public pages use
 * this so a missing file degrades to "no logo / no PDF" instead of a
 * broken image or a viewer that can't load.
 */
function departmentFileExists(?string $webPath): bool {
    if (!$webPath || strpos($webPath, '..') !== false) {
        return false;
    }
    // Uploaded files, plus the logos that shipped with the site (registered by
    // department-media-migration-2.sql so they don't need re-uploading).
    if (strpos($webPath, '/uploads/') !== 0 && strpos($webPath, '/assets/images/logos/') !== 0) {
        return false;
    }
    return is_file(__DIR__ . '/..' . $webPath);
}

/**
 * Add the ready-to-use fields every page needs to a departments row:
 *   logo_url        — public URL of the logo, or null
 *   dean_photo_url  — public URL of the dean's photo, or null
 *   dean_photo_name — the dean photo's original filename, or null
 */
function hydrateDepartmentMedia(array $row): array {
    $row['logo_url']       = departmentFileExists($row['logo_path'] ?? null) ? $row['logo_path'] : null;
    $row['dean_photo_url'] = departmentFileExists($row['dean_photo_path'] ?? null) ? $row['dean_photo_path'] : null;
    if ($row['dean_photo_url'] === null) {
        $row['dean_photo_name'] = null;
    }
    return $row;
}

const DEPARTMENT_SELECT_SQL = "
    SELECT d.*,
           lm.file_path AS logo_path,
           lm.file_name AS logo_name,
           dm.file_path AS dean_photo_path,
           dm.file_name AS dean_photo_name
    FROM departments d
    LEFT JOIN media_library lm ON lm.media_id = d.media_id
    LEFT JOIN media_library dm ON dm.media_id = d.dean_media_id
";

/**
 * All departments, in the order they were created (dept_id).
 */
function getDepartments(): array {
    try {
        $rows = getDB()->query(DEPARTMENT_SELECT_SQL . " ORDER BY d.dept_id ASC")->fetchAll(PDO::FETCH_ASSOC);
        return array_map('hydrateDepartmentMedia', $rows);
    } catch (Exception $e) {
        error_log('Get departments error: ' . $e->getMessage());
        return [];
    }
}

/**
 * One department by its unique ID, or null.
 */
function getDepartmentById(int $deptId): ?array {
    try {
        $stmt = getDB()->prepare(DEPARTMENT_SELECT_SQL . " WHERE d.dept_id = ?");
        $stmt->execute([$deptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? hydrateDepartmentMedia($row) : null;
    } catch (Exception $e) {
        error_log('Get department error: ' . $e->getMessage());
        return null;
    }
}

/**
 * One department by its slug (the page filename: cased, cba, coe, camp, coa,
 * conursing), or null. Slugs are unique and never change when a department is
 * renamed, so the static department pages use this to find their record.
 */
function getDepartmentBySlug(string $slug): ?array {
    try {
        $stmt = getDB()->prepare(DEPARTMENT_SELECT_SQL . " WHERE d.slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? hydrateDepartmentMedia($row) : null;
    } catch (Exception $e) {
        error_log('Get department by slug error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Logo URL for a department row, or $fallback (default null) when it has
 * none. $fallback lets a page keep showing its old static logo for a
 * department until a logo has been uploaded in the Content Editor.
 */
function getDepartmentLogoUrl(?array $dept, ?string $fallback = null): ?string {
    return $dept['logo_url'] ?? $fallback;
}

/**
 * Apply an admin's logo changes to one department in a single step.
 *
 *  - A new upload replaces the current logo.
 *  - $removeLogo clears the current logo (ignored if a new file is uploaded).
 *  - The replaced/removed file is deleted from disk and media_library, unless
 *    something else still references it.
 *  - If anything fails, a newly stored file is cleaned up and the
 *    department is left exactly as it was.
 *
 * Returns the refreshed department row. Throws Exception with a
 * user-friendly message on validation/storage failure.
 */
function saveDepartmentMedia(int $deptId, ?array $logoFile, bool $removeLogo): array {
    $dept = getDepartmentById($deptId);
    if (!$dept) {
        throw new Exception('Department not found.');
    }

    $hasLogo = !empty($logoFile) && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newLogo = $hasLogo ? storeDepartmentUpload($logoFile, 'logo') : null;   // throws (and stores nothing) if invalid

    $oldLogoId = $dept['media_id'] !== null ? (int)$dept['media_id'] : null;
    $logoId    = $newLogo ? $newLogo['media_id'] : ($removeLogo ? null : $oldLogoId);

    if ($logoId === $oldLogoId) {
        return $dept; // nothing changed
    }

    try {
        $stmt = getDB()->prepare("UPDATE departments SET media_id = ? WHERE dept_id = ?");
        $stmt->execute([$logoId, $deptId]);
    } catch (Exception $e) {
        discardStoredDepartmentUpload($newLogo);
        error_log('Save department media error: ' . $e->getMessage());
        throw new Exception('Could not update the department. Please try again.');
    }

    // The department now points at the new file; clean up the old one.
    if ($oldLogoId !== null && $oldLogoId !== $logoId) deleteMediaIfUnreferenced($oldLogoId);

    $what = $logoId === null ? 'removed logo' : ($oldLogoId === null ? 'added logo' : 'replaced logo');
    logActivity($_SESSION['user_id'], 'UPDATE', 'departments', $deptId, "{$dept['dept_name']}: {$what}");

    return getDepartmentById($deptId) ?? $dept;
}

/**
 * Apply an admin's "Head of Office" (Dean) edits to one department in a
 * single step: name, title, bio, phone, email, office, hours, and photo.
 *
 *  - A new photo upload replaces the current one.
 *  - $removePhoto clears the current photo (ignored if a new one is uploaded).
 *  - Text fields are saved as given (trim()'d; empty string stored as NULL).
 *  - If anything fails, a newly stored photo is cleaned up and the
 *    department is left exactly as it was.
 *
 * Returns the refreshed department row. Throws Exception with a
 * user-friendly message on validation/storage failure.
 */
function saveDepartmentDean(
    int $deptId,
    string $name,
    string $title,
    string $bio,
    string $phone,
    string $email,
    string $office,
    string $hours,
    ?array $photoFile,
    bool $removePhoto
): array {
    $dept = getDepartmentById($deptId);
    if (!$dept) {
        throw new Exception('Department not found.');
    }

    $hasPhoto = !empty($photoFile) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    $newPhoto = null;
    try {
        if ($hasPhoto) $newPhoto = storeDepartmentUpload($photoFile, 'dean_photo');
    } catch (Exception $e) {
        discardStoredDepartmentUpload($newPhoto);
        throw $e;
    }

    $oldPhotoId = $dept['dean_media_id'] !== null ? (int)$dept['dean_media_id'] : null;
    $photoId    = $newPhoto ? $newPhoto['media_id'] : ($removePhoto ? null : $oldPhotoId);

    $clean = function (string $v): ?string {
        $v = trim($v);
        return $v === '' ? null : $v;
    };
    $name   = $clean($name);
    $title  = $clean($title);
    $bio    = $clean($bio);
    $phone  = $clean($phone);
    $email  = $clean($email);
    $office = $clean($office);
    $hours  = $clean($hours);

    $textChanged =
        $name   !== $dept['dean_name']  ||
        $title  !== $dept['dean_title'] ||
        $bio    !== $dept['dean_bio']   ||
        $phone  !== $dept['dean_phone'] ||
        $email  !== $dept['dean_email'] ||
        $office !== $dept['dean_office']||
        $hours  !== $dept['dean_hours'];

    if (!$textChanged && $photoId === $oldPhotoId) {
        return $dept; // nothing changed
    }

    try {
        $stmt = getDB()->prepare("
            UPDATE departments
            SET dean_name = ?, dean_title = ?, dean_bio = ?, dean_media_id = ?,
                dean_phone = ?, dean_email = ?, dean_office = ?, dean_hours = ?
            WHERE dept_id = ?
        ");
        $stmt->execute([$name, $title, $bio, $photoId, $phone, $email, $office, $hours, $deptId]);
    } catch (Exception $e) {
        discardStoredDepartmentUpload($newPhoto);
        error_log('Save department dean error: ' . $e->getMessage());
        throw new Exception('Could not update the Head of Office. Please try again.');
    }

    if ($oldPhotoId !== null && $oldPhotoId !== $photoId) {
        deleteMediaIfUnreferenced($oldPhotoId);
    }

    $changes = [];
    if ($textChanged)          $changes[] = 'updated Head of Office details';
    if ($photoId !== $oldPhotoId) $changes[] = $photoId === null ? 'removed dean photo' : ($oldPhotoId === null ? 'added dean photo' : 'replaced dean photo');
    logActivity($_SESSION['user_id'], 'UPDATE', 'departments', $deptId, "{$dept['dept_name']}: " . implode(', ', $changes));

    return getDepartmentById($deptId) ?? $dept;
}

/**
 * The slice of a department the Content Editor needs (JSON-safe).
 */
function departmentAdminPayload(array $dept): array {
    return [
        'dept_id'         => (int)$dept['dept_id'],
        'name'            => $dept['dept_name'],
        'logo_url'        => $dept['logo_url'] ?? null,
        'logo_name'       => !empty($dept['logo_url']) ? ($dept['logo_name'] ?? null) : null,
        'dean_name'       => $dept['dean_name']   ?? null,
        'dean_title'      => $dept['dean_title']  ?? null,
        'dean_bio'        => $dept['dean_bio']    ?? null,
        'dean_phone'      => $dept['dean_phone']  ?? null,
        'dean_email'      => $dept['dean_email']  ?? null,
        'dean_office'     => $dept['dean_office'] ?? null,
        'dean_hours'      => $dept['dean_hours']  ?? null,
        'dean_photo_url'  => $dept['dean_photo_url']  ?? null,
        'dean_photo_name' => !empty($dept['dean_photo_url']) ? ($dept['dean_photo_name'] ?? null) : null,
    ];
}

// ============================================================
// HOMEPAGE — "Message from the President" (single-row settings)
// ============================================================
// One row (id = 1) in `homepage_president` is the single source of truth
// for the photo, name, title, org, and welcome message shown in the
// "Message from the President" section on index.php. Requires
// sql/homepage-president-migration.sql to have been run.

const HOMEPAGE_PRESIDENT_SELECT_SQL = "
    SELECT hp.*,
           m.file_path AS photo_path,
           m.file_name AS photo_name
    FROM homepage_president hp
    LEFT JOIN media_library m ON m.media_id = hp.media_id
    WHERE hp.id = 1
";

/**
 * The homepage President row, hydrated with a ready-to-use photo_url, or
 * an all-null shell if the migration hasn't been run / seeded yet (so
 * callers never have to null-check the whole row).
 */
function getHomepagePresident(): array {
    try {
        $stmt = getDB()->query(HOMEPAGE_PRESIDENT_SELECT_SQL);
        $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    } catch (Exception $e) {
        error_log('Get homepage president error: ' . $e->getMessage());
        $row = false;
    }
    if (!$row) {
        $row = ['id' => 1, 'name' => null, 'title' => null, 'org' => null, 'eyebrow' => null,
                'heading' => null, 'greeting' => null, 'message' => null, 'media_id' => null,
                'photo_path' => null, 'photo_name' => null];
    }
    $row['photo_url'] = departmentFileExists($row['photo_path'] ?? null) ? $row['photo_path'] : null;
    if ($row['photo_url'] === null) {
        $row['photo_name'] = null;
    }
    return $row;
}

/**
 * Apply an admin's edits to the homepage President section in a single
 * step: name, title, org, eyebrow, heading, greeting, message, and photo.
 *
 *  - A new photo upload replaces the current one.
 *  - $removePhoto clears the current photo (ignored if a new one is uploaded).
 *  - Text fields are saved as given (trim()'d; empty string stored as NULL).
 *  - If anything fails, a newly stored photo is cleaned up and the row is
 *    left exactly as it was.
 *
 * Returns the refreshed row. Throws Exception with a user-friendly message
 * on validation/storage failure.
 */
function saveHomepagePresident(
    string $name,
    string $title,
    string $org,
    string $eyebrow,
    string $heading,
    string $greeting,
    string $message,
    ?array $photoFile,
    bool $removePhoto
): array {
    $current = getHomepagePresident();

    $hasPhoto = !empty($photoFile) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    $newPhoto = null;
    try {
        if ($hasPhoto) $newPhoto = storeDepartmentUpload($photoFile, 'president_photo');
    } catch (Exception $e) {
        discardStoredDepartmentUpload($newPhoto);
        throw $e;
    }

    $oldPhotoId = $current['media_id'] !== null ? (int)$current['media_id'] : null;
    $photoId    = $newPhoto ? $newPhoto['media_id'] : ($removePhoto ? null : $oldPhotoId);

    $clean = function (string $v): ?string {
        $v = trim($v);
        return $v === '' ? null : $v;
    };
    $name     = $clean($name);
    $title    = $clean($title);
    $org      = $clean($org);
    $eyebrow  = $clean($eyebrow);
    $heading  = $clean($heading);
    $greeting = $clean($greeting);
    $message  = $clean($message);

    try {
        $stmt = getDB()->prepare("
            INSERT INTO homepage_president (id, name, title, org, eyebrow, heading, greeting, message, media_id, updated_by)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name), title = VALUES(title), org = VALUES(org),
                eyebrow = VALUES(eyebrow), heading = VALUES(heading), greeting = VALUES(greeting),
                message = VALUES(message), media_id = VALUES(media_id), updated_by = VALUES(updated_by)
        ");
        $stmt->execute([$name, $title, $org, $eyebrow, $heading, $greeting, $message, $photoId, $_SESSION['user_id'] ?? null]);
    } catch (Exception $e) {
        discardStoredDepartmentUpload($newPhoto);
        error_log('Save homepage president error: ' . $e->getMessage());
        throw new Exception('Could not update the President section. Please try again.');
    }

    if ($oldPhotoId !== null && $oldPhotoId !== $photoId) {
        deleteMediaIfUnreferenced($oldPhotoId);
    }

    logActivity($_SESSION['user_id'], 'UPDATE', 'homepage_president', 1, 'Updated Message from the President');

    return getHomepagePresident();
}

/**
 * The slice of the homepage President row the Content Editor needs
 * (JSON-safe).
 */
function homepagePresidentAdminPayload(array $row): array {
    return [
        'name'       => $row['name']     ?? null,
        'title'      => $row['title']    ?? null,
        'org'        => $row['org']      ?? null,
        'eyebrow'    => $row['eyebrow']  ?? null,
        'heading'    => $row['heading']  ?? null,
        'greeting'   => $row['greeting'] ?? null,
        'message'    => $row['message']  ?? null,
        'photo_url'  => $row['photo_url']  ?? null,
        'photo_name' => !empty($row['photo_url']) ? ($row['photo_name'] ?? null) : null,
    ];
}


// ============================================================
// COLLEGE PROGRAMS — CURRICULUM PDF
// ============================================================
// One row in `programs` per academic program. program_code is the same code
// the program page already declares (CURRENT_PROGRAM, e.g. 'BAEL') and that
// getProgramCategories() defines; dept_id ties the program to its department,
// so Department → Programs → Curriculum PDF is all one chain of records.
// The PDF (programs.pdf_media_id) points at media_library like every other
// upload, and is shown in the page's "Program Curriculum" section.
//
// Requires sql/program-pdf-migration.sql (creates `programs`).

/**
 * Add the ready-to-use fields every page needs to a programs row:
 *   program_name — full display name (from getProgramCategories())
 *   pdf_url      — public URL of the curriculum PDF, or null
 *   pdf_name     — the PDF's original filename, or null
 *   pdf_size     — the PDF's size in bytes, or null
 *   dept_name / dept_slug — the department the program belongs to
 */
function hydrateProgram(array $row): array {
    $row['program_name'] = getProgramCategoryLabel($row['program_code'] ?? null);
    $row['pdf_url']      = departmentFileExists($row['pdf_path'] ?? null) ? $row['pdf_path'] : null;
    if ($row['pdf_url'] === null) {
        $row['pdf_name'] = null;
        $row['pdf_size'] = null;
    }
    return $row;
}

const PROGRAM_SELECT_SQL = "
    SELECT p.*,
           d.dept_name,
           d.slug AS dept_slug,
           pm.file_path AS pdf_path,
           pm.file_name AS pdf_name,
           pm.file_size AS pdf_size
    FROM programs p
    LEFT JOIN departments d ON d.dept_id = p.dept_id
    LEFT JOIN media_library pm ON pm.media_id = p.pdf_media_id
";

/**
 * All programs, grouped by department in creation order.
 */
function getPrograms(): array {
    try {
        $rows = getDB()->query(PROGRAM_SELECT_SQL . " ORDER BY p.dept_id ASC, p.program_id ASC")->fetchAll(PDO::FETCH_ASSOC);
        return array_map('hydrateProgram', $rows);
    } catch (Exception $e) {
        error_log('Get programs error: ' . $e->getMessage());
        return [];
    }
}

function getProgramById(int $programId): ?array {
    try {
        $stmt = getDB()->prepare(PROGRAM_SELECT_SQL . " WHERE p.program_id = ?");
        $stmt->execute([$programId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? hydrateProgram($row) : null;
    } catch (Exception $e) {
        error_log('Get program error: ' . $e->getMessage());
        return null;
    }
}

/**
 * One program by its code (BAEL, BSCE, ...), or null. Program pages call this
 * with their CURRENT_PROGRAM constant.
 */
function getProgramByCode(string $code): ?array {
    try {
        $stmt = getDB()->prepare(PROGRAM_SELECT_SQL . " WHERE p.program_code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? hydrateProgram($row) : null;
    } catch (Exception $e) {
        error_log('Get program by code error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Upload, replace or remove one program's curriculum PDF.
 *
 *  - A new upload replaces the current PDF; $removePdf clears it (ignored if
 *    a new file is uploaded).
 *  - The replaced/removed file is deleted from disk and media_library, unless
 *    something else still references it.
 *  - If anything fails, a newly stored file is cleaned up and the program is
 *    left exactly as it was.
 *
 * Returns the refreshed program row. Throws Exception with a user-friendly
 * message on validation/storage failure.
 */
function saveProgramPdf(int $programId, ?array $pdfFile, bool $removePdf): array {
    $program = getProgramById($programId);
    if (!$program) {
        throw new Exception('Program not found.');
    }

    $hasPdf = !empty($pdfFile) && ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newPdf = $hasPdf ? storeDepartmentUpload($pdfFile, 'pdf') : null;   // throws (and stores nothing) if invalid

    $oldId = ($program['pdf_media_id'] ?? null) !== null ? (int)$program['pdf_media_id'] : null;
    $newId = $newPdf ? $newPdf['media_id'] : ($removePdf ? null : $oldId);

    if ($newId === $oldId) {
        return $program; // nothing changed
    }

    try {
        $stmt = getDB()->prepare("UPDATE programs SET pdf_media_id = ? WHERE program_id = ?");
        $stmt->execute([$newId, $programId]);
    } catch (Exception $e) {
        discardStoredDepartmentUpload($newPdf);
        error_log('Save program PDF error: ' . $e->getMessage());
        throw new Exception('Could not update the program. Please try again.');
    }

    if ($oldId !== null && $oldId !== $newId) {
        deleteMediaIfUnreferenced($oldId);
    }

    $what = $newId === null ? 'removed curriculum PDF' : ($oldId === null ? 'added curriculum PDF' : 'replaced curriculum PDF');
    logActivity($_SESSION['user_id'], 'UPDATE', 'programs', $programId, "{$program['program_name']}: {$what}");

    return getProgramById($programId) ?? $program;
}

/**
 * The slice of a program the Content Editor needs (JSON-safe).
 */
function programAdminPayload(array $program): array {
    return [
        'program_id'   => (int)$program['program_id'],
        'program_code' => $program['program_code'],
        'name'         => $program['program_name'],
        'pdf_url'      => $program['pdf_url']  ?? null,
        'pdf_name'     => $program['pdf_name'] ?? null,
        'pdf_size'     => isset($program['pdf_size']) ? (int)$program['pdf_size'] : null,
    ];
}