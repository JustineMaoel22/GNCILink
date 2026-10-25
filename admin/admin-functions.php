<?php
/**
 * GNC Admin Panel - Core Functions
 * Updated to use existing media_library schema with featured_image column
 */

// ============================================================
// SESSION MANAGEMENT
// ============================================================

require_once __DIR__ . '/settings-functions.php';
require_once __DIR__ . '/../includes/content-publish-policy.php';
require_once __DIR__ . '/photo-check.php';
require_once __DIR__ . '/../includes/image-framing.php';
require_once __DIR__ . '/rich-text.php';
require_once __DIR__ . '/basic-education-content.php';
require_once __DIR__ . '/department-page-content.php';
require_once __DIR__ . '/student-organizations.php';
require_once __DIR__ . '/../includes/office-location.php';
require_once __DIR__ . '/../includes/student-achievements.php';
require_once __DIR__ . '/../includes/admin-rbac.php';
require_once __DIR__ . '/../includes/first-login-password.php';
require_once __DIR__ . '/../includes/auth-security.php';

/**
 * Initialize secure session
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure=authIsHttps()||!authLocalDevelopment();
        ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.use_trans_sid','0');
        session_set_cookie_params(['path'=>'/','httponly'=>true,'secure'=>$secure,'samesite'=>'Lax']);
        session_start();
        // Regenerate session ID for security
        if (!isset($_SESSION['_initiated'])) {
            session_regenerate_id(true);
            $_SESSION['_initiated'] = true;
        }
        if(isset($_SESSION['last_activity'])&&time()-(int)$_SESSION['last_activity']>SESSION_TIMEOUT){authClearSession();header('Location: /admin/login.php?timeout=1');exit;}
        $_SESSION['last_activity'] = time();
    }
    authEnforceTransport();
    if (isLoggedIn()) rbacRequestBoundary();
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
        $user = $stmt->fetch();
        if ($user) $user['role'] = rbacRole($user['role']);
        return $user ?: null;
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
    authClearSession();
}

// ============================================================
// AUTHENTICATION & PERMISSIONS
// ============================================================

/**
 * Check permission based on role
 */
function hasPermission(string $permission): bool {
    if (!isLoggedIn()) return false;
    
    rbacRefreshIdentity();
    if (!empty($_SESSION['must_change_password'])) return false;
    $role = rbacRole();
    $permissions = getPermissions($role);
    return in_array($permission, $permissions);
}

/**
 * Get permissions for a role
 */
function getPermissions(string $role): array {
    $role = rbacRole($role);
    $permissionMap = [
        'Super Admin' => [
            'manage_settings',
            'manage_users', 'add_user', 'edit_user', 'delete_user', 'assign_roles',
            'manage_announcements', 'create_announcement', 'edit_announcement', 'delete_announcement', 'publish_announcement',
            'manage_news', 'create_news', 'edit_news', 'delete_news', 'publish_news',
            'manage_events', 'create_event', 'edit_event', 'delete_event', 'publish_event',
            'approve_content', 'reject_content', 'request_revision',
            'view_logs', 'manage_approvals',
            'manage_page_content',
            'view_analytics'
        ],
        'Content Editor' => [
            'manage_settings', 'manage_page_content', 'edit_page_content', 'view_analytics',
            'manage_announcements','create_announcement','edit_announcement','delete_announcement',
            'manage_news','create_news','edit_news','delete_news',
            'manage_events','create_event','edit_event','delete_event', 'view_own_submissions'
        ],
        'The Mirror' => [
            'create_announcement','edit_own_announcement', 'create_news','edit_own_news', 'view_own_submissions'
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
    rbacRequestBoundary();
}

// ============================================================
// ACTIVITY LOGGING
// ============================================================

/**
 * Log user activity
 */
function logActivity(int $userId, string $action, ?string $table = null, ?int $recordId = null, ?string $description = null): bool {
    if(in_array($action,['LOGIN','LOGOUT','PASSWORD_CHANGED','PASSWORD_CHANGE_FAILED'],true))authAudit($userId,$action,$description??$action);
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
    if (!rbacIsSuper()) return [];
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
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = getProgramCategoriesBuiltIn();
    // Programs added (or renamed) in Content Editor -> Programs live in the
    // `programs` table; they join this list so announcements can be tagged
    // with them too. Before the program-page migration there is no
    // program_name column: the built-in list alone is used.
    try {
        $rows = getDB()->query("SELECT program_code, program_name FROM programs WHERE program_name IS NOT NULL AND program_name <> ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cache[$r['program_code']] = $r['program_name'];
        }
    } catch (Throwable $e) {
        // keep the built-in list
    }
    return $cache;
}

/** Announcement choices exclude Basic Education tracks; use their department instead. */
function getAnnouncementProgramCategories(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = getProgramCategories();
    try {
        $rows = getDB()->query("SELECT p.program_code FROM programs p JOIN departments d ON d.dept_id = p.dept_id WHERE d.department_type = 'basic-education'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $code) unset($cache[$code]);
    } catch (Throwable $e) {
        // Older schemas have only the shipped College choices.
        $cache = getProgramCategoriesBuiltIn();
    }
    return $cache;
}

/** Keep existing legacy tags on edits, but never assign new Basic Education program tags. */
function normalizeAnnouncementProgramCategory(?string $code, ?string $existingCode = null): string {
    $code = normalizeProgramCategory($code);
    if (isset(getAnnouncementProgramCategories()[$code]) || $code === $existingCode) return $code;
    throw new InvalidArgumentException('Choose a College or Graduate School program. For Basic Education, select its department instead.');
}

/** The programs the site shipped with (fallback + 'ALL'). */
function getProgramCategoriesBuiltIn(): array {
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
    $file = imageUploadPrepare($file);
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no image provided — that's fine, it's optional
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }

    // 5MB max
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new Exception('Image is too large. Maximum size is 5MB.');
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
        imageFramingSaveUpload($filePath, $destination);
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
        if (empty($data['title']) && empty($data['allow_incomplete'])) {
            throw new Exception('Title is required.');
        }
        if (empty($data['content']) && empty($data['allow_incomplete'])) {
            throw new Exception('Content is required.');
        }
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('User session not found.');
        }
        
        $slug = uniqueContentSlug('announcements', 'announcement_id', $data['title']);
        
        $status = rbacIsSuper()
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
            rbacContentOwnerId(),
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
        
        rbacCreatedRecord('announcement',(int)$id);
        logActivity($_SESSION['user_id'], 'CREATE', 'announcements', $id, 'Created announcement: ' . $data['title']);
        if($status==='published')logActivity((int)$_SESSION['user_id'],'PUBLISH','announcements',(int)$id,'Published announcement: '.$data['title']);
        
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
        if (rbacRole() === 'The Mirror' && (int)$announcement['user_id'] !== (int)$_SESSION['user_id']) {
            return false;
        }
        
        $slug = uniqueContentSlug('announcements', 'announcement_id', $data['title'], $id);
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
        if($status==='published'){
            if($announcement['status']==='pending')logActivity((int)$_SESSION['user_id'],'APPROVE','announcements',$id,'Approved pending announcement.');
            logActivity((int)$_SESSION['user_id'],'PUBLISH','announcements',$id,'Published announcement changes.');
        }
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
        $row=$stmt->fetch();
        if($row&&rbacRequestRoute()!==''&&rbacRole()==='The Mirror'&&(int)$row['user_id']!==(int)$_SESSION['user_id'])return null;
        return $row?:null;
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
        
        if (rbacRole() === 'The Mirror') {
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
 * Handle several uploaded announcement photos at once ($_FILES['gallery_images'],
 * from <input type="file" name="gallery_images[]" multiple>).
 * Every file goes through uploadAnnouncementImage() (same checks, same
 * media_library row). Returns the ordered list of new media_ids.
 */
function uploadAnnouncementImages(?array $files): array {
    if (empty($files) || empty($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $mediaIds = [];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue; // empty slot
        }

        $mediaId = uploadAnnouncementImage([
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ]);
        if ($mediaId !== null) {
            $mediaIds[] = $mediaId;
        }
    }

    return $mediaIds;
}

/**
 * Attach uploaded media to an announcement's photo gallery
 * (announcement_images), after whatever is already there.
 */
function addAnnouncementImages(int $announcementId, array $mediaIds): bool {
    if (empty($mediaIds)) return true;

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) FROM announcement_images WHERE announcement_id = ?");
        $stmt->execute([$announcementId]);
        $nextOrder = (int)$stmt->fetchColumn() + 1;

        $stmt = $db->prepare("
            INSERT INTO announcement_images (announcement_id, media_id, display_order, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        foreach ($mediaIds as $mediaId) {
            $stmt->execute([$announcementId, $mediaId, $nextOrder++]);
        }

        return true;
    } catch (Exception $e) {
        error_log('Add announcement images error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get every gallery photo of an announcement, in display order.
 */
function getAnnouncementImages(int $announcementId): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT ai.image_id, ai.media_id, ai.display_order, m.file_path
            FROM announcement_images ai
            LEFT JOIN media_library m ON ai.media_id = m.media_id
            WHERE ai.announcement_id = ?
            ORDER BY ai.display_order ASC, ai.image_id ASC
        ");
        $stmt->execute([$announcementId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Get announcement images error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Remove one gallery photo from an announcement (by announcement_images.image_id).
 */
function removeAnnouncementImage(int $imageId, int $announcementId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM announcement_images WHERE image_id = ? AND announcement_id = ?");
        return $stmt->execute([$imageId, $announcementId]);
    } catch (Exception $e) {
        error_log('Remove announcement image error: ' . $e->getMessage());
        return false;
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
const MAX_PINNED_ITEMS = 5;

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
        'Student Life'  => 'Student Organization',
        'Community Extension' => 'Community Extension Activity',
        'Athletics and Sports' => 'Athletics and Sports',
        'Job Opportunities' => 'Job Opportunities',
        'Scholarship'    => 'Scholarship',
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
    $file = imageUploadPrepare($file);
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no image provided — that's fine, it's optional
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }

    // 5MB max
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new Exception('Image is too large. Maximum size is 5MB.');
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
        imageFramingSaveUpload($filePath, $destination);
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
        if (empty($data['title']) && empty($data['allow_incomplete'])) {
            throw new Exception('Title is required.');
        }
        if (empty($data['content']) && empty($data['allow_incomplete'])) {
            throw new Exception('Content is required.');
        }
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('User session not found.');
        }

        $slug = uniqueContentSlug('news', 'news_id', $data['title']);

        $status = rbacIsSuper()
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
            rbacContentOwnerId(),
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

        rbacCreatedRecord('news',(int)$id);
        logActivity($_SESSION['user_id'], 'CREATE', 'news', $id, 'Created news article: ' . $data['title']);
        if($status==='published')logActivity((int)$_SESSION['user_id'],'PUBLISH','news',(int)$id,'Published news: '.$data['title']);

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
        if (rbacRole() === 'The Mirror' && (int)$news['user_id'] !== (int)$_SESSION['user_id']) {
            return false;
        }

        $slug = uniqueContentSlug('news', 'news_id', $data['title'], $id);
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
        if($status==='published'){
            if($news['status']==='pending')logActivity((int)$_SESSION['user_id'],'APPROVE','news',$id,'Approved pending news.');
            logActivity((int)$_SESSION['user_id'],'PUBLISH','news',$id,'Published news changes.');
        }
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
        if($row&&rbacRequestRoute()!==''&&rbacRole()==='The Mirror'&&(int)$row['user_id']!==(int)$_SESSION['user_id'])return null;
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

        if (rbacRole() === 'The Mirror') {
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
        // Keep auto-categories current (manually edited/locked posts are skipped).
        require_once __DIR__ . '/../config/fb-categorizer.php';
        gncAutoCategorizeFbPosts($db);
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
                'link'       => '/announcement-article.php?slug=' . urlencode($ann['slug']),
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
                'link'       => '/news-article.php?slug=' . urlencode($item['slug']),
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
 * Make a web address ("slug") from a title that no other row of the table is using.
 * "Test" -> test, then test-2, test-3 ... so two articles with the same title
 * never open each other. $excludeId lets an item keep its own slug when it is edited.
 * Only the tables listed here are allowed.
 */
function uniqueContentSlug(string $table, string $idColumn, string $title, ?int $excludeId = null): string {
    $allowed = ['news' => 'news_id', 'announcements' => 'announcement_id'];
    if (!isset($allowed[$table]) || $allowed[$table] !== $idColumn) {
        return generateSlug($title);
    }

    $base = mb_substr(generateSlug($title), 0, 200);
    if ($base === '') {
        $base = $table === 'news' ? 'news' : 'announcement'; // title had no letters or numbers
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND `$idColumn` <> ?");
    $slug = $base;
    $n = 2;
    while (true) {
        $stmt->execute([$slug, (int)$excludeId]);
        if ((int)$stmt->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $n++;
    }
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
    $file = imageUploadPrepare($file);
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
        $maxBytes = 5 * 1024 * 1024; // 5MB
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
        throw new Exception('File is too large. Maximum size is ' . ($type === 'video' ? '50MB for video' : '5MB for images') . '.');
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

    imageFramingSaveUpload('/uploads/hero-slides/' . $filename, $destination);
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

/** Ensure the History page has editable rows in the existing page_sections CMS. */
function ensureHistoryPageSections(): void {
    $defaults = [
        ['history_hero_eyebrow', 'History hero eyebrow', 'Since 1918'],
        ['history_hero_title', 'History hero title', 'History of GNC'],
        ['history_intro_title', 'Journey heading', 'The Journey Through Time'],
        ['history_intro_text', 'Journey introduction', 'Scroll down to follow our story, from a parochial school in 1918 to the college we are today.'],
        ['history_1918', '1918 — The Beginning', '<h2>1918</h2><h3>The Beginning</h3><p>In June 18, 1918, Rev. Fr. Nicanor Banzali, then parish priest of Guagua, founded a parochial school initially housed at the Catholic Convent in the town plaza.</p>'],
        ['history_1922_1941', '1922 and 1941 — Growth and Expansion', '<h2>1922 and 1941</h2><h3>Growth and Expansion</h3><p>The school became the Guagua National Institute (GNI) when it started to offer high school courses in 1922 and in 1941, the institution offered college courses.</p>'],
        ['history_1941_1948', '1941–1948 — Trials and Resilience', '<h2>1941 — 1948</h2><h3>Trials and Resilience</h3><p>However, on December 8, 1941, upon the outbreak of World War II, GNI had to temporarily close its doors.</p><p>On February 10, 1942, its three school buildings were razed to the ground by a fire of incendiary (flammable) origin. Hundreds of alumni served in World War II (WW II), and among them was Capt. Euben Sangco, one of the notable heroes of Pampanga.</p>'],
        ['history_1948', '1948 — A New Beginning', '<h2>1948</h2><h3>A New Beginning</h3><p>On June 1, 1948, GNI reopened its doors in spite of the uncertainty of a place to house the school. Classes were held under ‘Gampalati’ trees and in makeshift tents.</p><p>The determination to move on in spite of the odds finds a parallel event in 1991, when a week after the eruption of Mt. Pinatubo, GNI resumed regular classes. The aim was to instill in the community and in the youth the importance of acting with dispatch in times of crisis.</p>'],
        ['history_1949', '1949 — A Legacy of Excellence', '<h2>1949</h2><h3>A Legacy of Excellence</h3><p>In 1949, the Institute became a College. GNI was, therefore, renamed to Guagua National Colleges, Inc. (GNC).</p><p>Being a Catholic co-educational institution with its high quality of instruction, GNC has produced outstanding alumni who have distinguished themselves locally, countrywide, and abroad.</p><p>GNC is a private college and a member in good standing of the Catholic Educational Association of the Philippines (CEAP).</p>'],
        ['history_present', 'Present — Moving Forward Together', '<h2>Present</h2><h3>Moving Forward Together</h3><p>At present, GNC’s officials and administrators, educators, staff and students are united in carrying out the school’s mission with vigor and is dedicated to the pursuit of quality education and academic excellence.</p>'],
        ['history_quote', 'History closing statement', '<p>From humble beginnings to a legacy of service and excellence, GNC continues to educate, inspire, and transform lives.</p>'],
    ];
    try {
        $db = getDB();
        $page = getPageBySlug('history');
        if (!$page) return;
        $check = $db->prepare('SELECT 1 FROM page_sections WHERE page_id = ? AND section_key = ? LIMIT 1');
        $insert = $db->prepare("INSERT INTO page_sections (page_id, section_key, title, content, status, display_order) VALUES (?, ?, ?, ?, 'published', ?)");
        foreach ($defaults as $order => $row) {
            $check->execute([(int)$page['page_id'], $row[0]]);
            if (!$check->fetchColumn()) $insert->execute([(int)$page['page_id'], $row[0], $row[1], $row[2], $order + 1]);
        }
    } catch (Throwable $e) {
        error_log('Ensure History page sections error: ' . $e->getMessage());
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

/** Return the validated public path for a page section's attached image. */
function getPageSectionImagePath(string $pageSlug, string $sectionKey): ?string {
    try {
        $stmt = getDB()->prepare('SELECT m.file_path FROM page_sections ps INNER JOIN pages p ON p.page_id = ps.page_id INNER JOIN media_library m ON m.media_id = ps.media_id WHERE p.slug = ? AND ps.section_key = ? LIMIT 1');
        $stmt->execute([$pageSlug, $sectionKey]);
        $path = (string)($stmt->fetchColumn() ?: '');
        if (strpos($path, '/uploads/') !== 0 || strpos($path, '..') !== false || !is_file(__DIR__ . '/..' . $path)) return null;
        return $path;
    } catch (Throwable $e) {
        error_log('Get page section image error: ' . $e->getMessage());
        return null;
    }
}

/** Attach or remove an image on a History timeline section or closing quote. */
function saveHistorySectionImage(string $sectionKey, ?array $file, bool $remove): ?string {
    if (!preg_match('/^history_(1918|1922_1941|1941_1948|1948|1949|present|quote)$/', $sectionKey)) {
        throw new Exception('That History photo slot is not valid.');
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT ps.media_id FROM page_sections ps INNER JOIN pages p ON p.page_id = ps.page_id WHERE p.slug = 'history' AND ps.section_key = ? LIMIT 1");
    $stmt->execute([$sectionKey]);
    $oldId = $stmt->fetchColumn();
    if ($oldId === false) throw new Exception('History section not found. Refresh the editor and try again.');
    $stored = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? storeDepartmentUpload($file, 'history_image') : null;
    if (!$stored && !$remove) throw new Exception('Choose an image to upload.');
    $newId = $stored ? (int)$stored['media_id'] : null;
    try {
        $stmt = $db->prepare("UPDATE page_sections ps INNER JOIN pages p ON p.page_id = ps.page_id SET ps.media_id = ? WHERE p.slug = 'history' AND ps.section_key = ?");
        $stmt->execute([$newId, $sectionKey]);
    } catch (Throwable $e) {
        if ($stored) deleteMediaIfUnreferenced($newId);
        error_log('Save History section image error: ' . $e->getMessage());
        throw new Exception('Could not save the History photo. Please try again.');
    }
    if ($oldId !== null && $oldId !== false && (int)$oldId !== (int)$newId) deleteMediaIfUnreferenced((int)$oldId);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'UPDATE', 'page_sections', null, 'Updated History photo: ' . $sectionKey);
    return $newId ? getPageSectionImagePath('history', $sectionKey) : null;
}

/**
 * Save a section's edits as a draft (does NOT touch the published
 * `content` column, so the live public site is unaffected until Publish
 * is called).
 */
function saveSectionDraft(string $pageSlug, string $sectionKey, string $content): bool {
    if ($pageSlug === 'contact-us') {
        require_once __DIR__ . '/../components/contact-page-content.php';
        $content = contactPageValidate($sectionKey, $content);
    }
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
    if ($pageSlug === 'contact-us') {
        require_once __DIR__ . '/../components/contact-page-content.php';
        contactPageValidate($sectionKey, (string)getSectionContent($pageSlug, $sectionKey, true));
    }
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
// PAGE CONTENT — rich text + approval workflow
// (used by the redesigned Content Editor, admin/content-edit.php)
// ============================================================
// Roles (see getPermissions):
//   Super Admin                  -> publish directly and approve/reject.
//   Content Editor               -> private snapshots and queued change requests.
//   The request boundary intercepts editor writes before any live handler runs.
// A submitted draft waits in page_sections.review_status = 'pending'.
// The public site only ever reads page_sections.content, which changes
// only on publish / approve. Needs sql/page-approval-migration.sql.

/** True once sql/page-approval-migration.sql has been run. */
function pageReviewReady(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $ready = (bool)getDB()->query("SHOW COLUMNS FROM page_sections LIKE 'review_status'")->fetch();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/** Does stored text contain formatting (as opposed to plain text)? */
/** One section with the names of the people involved (editor UI). */
function getSectionDetailed(string $pageSlug, string $sectionKey): ?array {
    try {
        $db = getDB();
        $ready = pageReviewReady();
        $stmt = $db->prepare("
            SELECT ps.*, p.slug AS page_slug, p.title AS page_title,
                   CONCAT(COALESCE(ue.first_name, ''), ' ', COALESCE(ue.last_name, '')) AS updated_by_name"
                   . ($ready ? ", CONCAT(COALESCE(us.first_name, ''), ' ', COALESCE(us.last_name, '')) AS submitted_by_name" : "") . "
            FROM page_sections ps
            INNER JOIN pages p ON p.page_id = ps.page_id
            LEFT JOIN users ue ON ue.user_id = ps.updated_by"
            . ($ready ? " LEFT JOIN users us ON us.user_id = ps.submitted_by" : "") . "
            WHERE p.slug = ? AND ps.section_key = ?
            LIMIT 1
        ");
        $stmt->execute([$pageSlug, $sectionKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['review_status'] = $row['review_status'] ?? 'none';
        return $row;
    } catch (Throwable $e) {
        error_log('Get section detailed error: ' . $e->getMessage());
        return null;
    }
}

function getPageSectionsDetailed(string $pageSlug): array {
    $out = [];
    foreach (getPageSections($pageSlug) as $s) {
        $d = getSectionDetailed($pageSlug, (string)$s['section_key']);
        if ($d) $out[] = $d;
    }
    return $out;
}

/** Back to "not submitted" (used when the author edits or withdraws). */
function resetSectionReview(string $pageSlug, string $sectionKey): void {
    if (!pageReviewReady()) return;
    $row = getSectionDetailed($pageSlug, $sectionKey);
    if (!$row || $row['review_status'] === 'none') return;
    $stmt = getDB()->prepare("UPDATE page_sections SET review_status = 'none', review_note = NULL WHERE section_id = ?");
    $stmt->execute([(int)$row['section_id']]);
}

/** Send a saved draft to the approvers. Throws Exception with a user-safe message. */
function submitSectionForReview(string $pageSlug, string $sectionKey): void {
    if (!pageReviewReady()) throw new Exception('The approval feature is not set up yet. Ask your developer to run sql/page-approval-migration.sql.');
    $row = getSectionDetailed($pageSlug, $sectionKey);
    if (!$row) throw new Exception('That section was not found.');
    if ($row['draft_content'] === null) throw new Exception('There is nothing new to submit. Make a change first.');
    $stmt = getDB()->prepare("
        UPDATE page_sections
        SET review_status = 'pending', submitted_by = ?, submitted_at = ?, review_note = NULL, reviewed_by = NULL, reviewed_at = NULL
        WHERE section_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'] ?? null, date('Y-m-d H:i:s'), (int)$row['section_id']]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'SUBMIT', 'page_sections', (int)$row['section_id'], "Submitted {$pageSlug}/{$sectionKey} for approval");
}

/** Approver publishes a pending submission (reuses publishSection). */
function approveSection(string $pageSlug, string $sectionKey): void {
    if (!pageReviewReady()) throw new Exception('The approval feature is not set up yet.');
    $row = getSectionDetailed($pageSlug, $sectionKey);
    if (!$row) throw new Exception('That section was not found.');
    if ($row['review_status'] !== 'pending') throw new Exception('This change is not waiting for approval (someone may have already reviewed it).');
    if (!publishSection($pageSlug, $sectionKey)) throw new Exception('Could not publish. Please try again.');
    $stmt = getDB()->prepare("UPDATE page_sections SET review_status = 'none', reviewed_by = ?, reviewed_at = ?, review_note = NULL WHERE section_id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? null, date('Y-m-d H:i:s'), (int)$row['section_id']]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'APPROVE', 'page_sections', (int)$row['section_id'], "Approved and published {$pageSlug}/{$sectionKey}");
}

/** Approver sends a pending submission back with a note. The draft is kept. */
function rejectSection(string $pageSlug, string $sectionKey, string $note): void {
    if (!pageReviewReady()) throw new Exception('The approval feature is not set up yet.');
    $note = trim(preg_replace('/\s+/', ' ', $note));
    if ($note === '') throw new Exception('Please write a short note so the editor knows what to change.');
    if (mb_strlen($note) > 500) throw new Exception('The note is too long (maximum 500 characters).');
    $row = getSectionDetailed($pageSlug, $sectionKey);
    if (!$row) throw new Exception('That section was not found.');
    if ($row['review_status'] !== 'pending') throw new Exception('This change is not waiting for approval (someone may have already reviewed it).');
    $stmt = getDB()->prepare("UPDATE page_sections SET review_status = 'rejected', reviewed_by = ?, reviewed_at = ?, review_note = ? WHERE section_id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? null, date('Y-m-d H:i:s'), $note !== '' ? $note : null, (int)$row['section_id']]);
    logActivity((int)($_SESSION['user_id'] ?? 0), 'REJECT', 'page_sections', (int)$row['section_id'], "Requested changes on {$pageSlug}/{$sectionKey}");
}

/** Everything waiting for approval, oldest first. */
function getPendingSections(): array {
    if (!pageReviewReady()) return [];
    try {
        return getDB()->query("
            SELECT ps.section_id, ps.section_key, ps.title AS section_title, ps.content, ps.draft_content,
                   ps.submitted_at, p.slug AS page_slug, p.title AS page_title,
                   CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS submitted_by_name
            FROM page_sections ps
            INNER JOIN pages p ON p.page_id = ps.page_id
            LEFT JOIN users u ON u.user_id = ps.submitted_by
            WHERE ps.review_status = 'pending'
            ORDER BY ps.submitted_at ASC, ps.section_id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Get pending sections error: ' . $e->getMessage());
        return [];
    }
}

/** Pages + their combined status, for the Content Editor's content list. */
function getContentHubPages(): array {
    try {
        $ready = pageReviewReady();
        return getDB()->query("
            SELECT p.page_id, p.slug, p.title, p.page_type, p.description, p.updated_at AS page_updated,
                   MAX(ps.updated_at) AS sections_updated,
                   COUNT(ps.section_id) AS section_count,
                   SUM(CASE WHEN ps.draft_content IS NOT NULL THEN 1 ELSE 0 END) AS drafts"
                   . ($ready ? ",
                   SUM(CASE WHEN ps.review_status = 'pending'  THEN 1 ELSE 0 END) AS pending,
                   SUM(CASE WHEN ps.review_status = 'rejected' THEN 1 ELSE 0 END) AS rejected" : ",
                   0 AS pending, 0 AS rejected") . "
            FROM pages p
            LEFT JOIN page_sections ps ON ps.page_id = p.page_id
            WHERE p.status = 'active'
            GROUP BY p.page_id, p.slug, p.title, p.page_type, p.description, p.updated_at, p.display_order
            ORDER BY p.display_order ASC, p.title ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Get content hub pages error: ' . $e->getMessage());
        return [];
    }
}

function getContentHubPrograms(): array {
    try {
        return getDB()->query("
            SELECT p.program_id, p.program_code, p.program_name, p.is_published, d.dept_name
            FROM programs p LEFT JOIN departments d ON d.dept_id = p.dept_id
            ORDER BY p.dept_id IS NULL, p.dept_id ASC, p.program_id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Get content hub programs error: ' . $e->getMessage());
        return [];
    }
}

function getContentHubEvents(): array {
    try {
        return getDB()->query("SELECT event_id, title, status, start_date, user_id FROM events ORDER BY start_date DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Get content hub events error: ' . $e->getMessage());
        return [];
    }
}

function getContentHubDepartments(): array {
    try {
        return getDB()->query("SELECT dept_id, dept_name, slug, archived_at FROM departments ORDER BY dept_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Get content hub departments error: ' . $e->getMessage());
        return [];
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
const ADMIN_PHOTO_MAX_BYTES = 5 * 1024 * 1024; // 5 MB (Administration page photos)

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
    if (!function_exists('mb_substr')) throw new RuntimeException('Uploads require the PHP mbstring extension. Enable mbstring in your hosting PHP settings.');
    if (!function_exists('finfo_open')) throw new RuntimeException('Uploads require the PHP Fileinfo extension. Enable fileinfo in your hosting PHP settings.');
    $file = imageUploadPrepare($file);
    $configs = [
        'department_banner' => [
            'label' => 'department banner',
            'max' => 5 * 1024 * 1024,
            'dir' => 'department-banners',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            'bad' => 'Upload a JPG, PNG, or WEBP banner.',
        ],
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
        'program_image' => [
            'label' => 'picture',
            'max'   => 5 * 1024 * 1024,
            'dir'   => 'programs',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
            'bad'   => 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP, SVG.',
        ],
        'enrollment_illustration' => [
            'label' => 'enrollment illustration',
            'max' => 5 * 1024 * 1024,
            'dir' => 'enrollment-procedures',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            'bad' => 'Upload an SVG, PNG, JPG, or WEBP illustration.',
        ],
        'history_image' => [
            'label' => 'history photo',
            'max'   => 5 * 1024 * 1024,
            'dir'   => 'history',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            'bad'   => 'Invalid image type. Allowed: JPG, PNG, WEBP.',
        ],
        // Facility pictures (Programs > Facilities): same folder as other program pictures, but up to 5 MB.
        'facility_image' => [
            'label' => 'picture',
            'max'   => PROGRAM_FACILITY_IMAGE_MAX_BYTES,
            'dir'   => 'programs',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            'bad'   => 'Invalid image type. Allowed: JPG, PNG, WEBP.',
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
        'homepage_image' => [
            'label' => 'picture',
            'max'   => 5 * 1024 * 1024,
            'dir'   => 'homepage',
            'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
            'bad'   => 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP.',
        ],
        'admin_photo' => [
            'label' => 'photo',
            'max'   => ADMIN_PHOTO_MAX_BYTES,
            'dir'   => 'administration',
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

    if (in_array($kind, ['logo', 'program_image', 'enrollment_illustration'], true) && $origExt === 'svg') {
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

    if (in_array($kind, ['dean_photo', 'president_photo', 'admin_photo'], true)) {
        photoCheckEnforce($file['tmp_name']);
    }

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
        imageFramingSaveUpload($filePath, $destination);
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
    } catch (Throwable $e) {
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
        ['programs',       'image_media_id'],
        ['programs',       'hero_media_id'],
        ['programs',       'seal_media_id'],
        ['program_items',  'image_media_id'],
        ['departments',    'dean_media_id'],
        ['homepage_president', 'media_id'],
        ['admin_people',   'media_id'],
        ['admin_offices',  'media_id'],
        ['student_organizations', 'logo_media_id'],
        ['student_organizations', 'cover_media_id'],
        ['student_org_gallery', 'media_id'],
        ['student_org_page_content', 'content'],
    ];

    try {
        $db = getDB();
        foreach ($references as [$table, $column]) {
            try {
                $stmt = $db->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
                $stmt->execute([$mediaId]);
            } catch (PDOException $e) {
                // Optional CMS modules may not be installed yet; absent columns cannot reference media.
                if (in_array($e->getCode(), ['42S02', '42S22'], true)) continue;
                throw $e;
            }
            if ($stmt->fetchColumn() !== false) {
                return false; // still in use somewhere
            }
        }

        // Banner references live in the department's existing content rows.
        try {
            $banner = $db->prepare("SELECT 1 FROM department_page_content WHERE field_key = 'banner_media_id' AND content = ? LIMIT 1");
            $banner->execute([(string)$mediaId]);
            if ($banner->fetchColumn() !== false) return false;
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') throw $e;
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
 * Ensure the Graduate School has a department record so it can reuse the
 * existing department and program editors. Existing records/content are left
 * untouched. The four old static program pages are registered once so their
 * programs can move onto the shared database-backed program template; the old
 * PHP pages remain available for existing bookmarks during migration.
 */
function ensureGraduateSchoolDepartment(): ?array {
    $dept = getDepartmentBySlug('graduate-school');
    $created = false;
    if (!$dept) {
        try {
            $dept = saveDepartmentDetails([
                'dept_name' => 'Graduate School',
                'description' => 'Postgraduate programs in education, business, and public administration.',
                'hero_text' => 'Graduate School',
                'about_text' => 'The Graduate School is committed to providing quality postgraduate education that develops highly competent professionals, responsible leaders, and innovative researchers. Through advanced learning, research, and professional development, the school prepares graduates to address emerging challenges in education, business, public administration, and other fields while contributing to the sustainable development of the nation and the preservation of Philippine culture and heritage.\n\nThrough these learning opportunities, the College prepares future educators and professionals to become competent, innovative, and socially responsible individuals who can effectively respond to the needs of their learners, communities, and professions.',
                'facebook_url' => '', 'office_label' => 'Graduate School Office', 'theme_color' => '',
            ]);
            $created = true;
        } catch (Throwable $e) {
            error_log('Ensure Graduate School department error: ' . $e->getMessage());
            return null;
        }
    }

    if ($created && $dept) {
        try {
            saveDepartmentDean((int)$dept['dept_id'], 'Lolita D. Nazal, Ed. D.', 'Dean',
                'The Graduate School advances academic excellence, innovation, and service through postgraduate education.',
                '(045) 900-4473 Loc. 106', 'info@gnc.edu.ph', 'BETA Hall, Floor 2, Room 202',
                'Monday - Friday, 8:00 AM - 4:00 PM', null, false);
        } catch (Throwable $e) {
            error_log('Graduate School leadership migration error: ' . $e->getMessage());
        }
    }

    if (!$dept || empty($dept['dept_id'])) return $dept;
    $legacyPrograms = [
        ['GS-PHD', 'Doctor of Philosophy (Educational Management)', 'The Ph.D. in Educational Management prepares educational leaders, researchers, and innovators through advanced study and research.'],
        ['GS-MAED', 'Master of Arts in Education (Educational Management)', 'The Master of Arts in Education develops reflective, research-oriented education professionals and leaders.'],
        ['GS-MBA', 'Master in Business Administration', 'The MBA develops strategic business leaders equipped to make sound decisions and support sustainable growth.'],
        ['GS-MPA', 'Master in Public Administration', 'The MPA prepares public leaders for effective governance, responsive service, and community development.'],
    ];
    foreach ($legacyPrograms as [$code, $name, $description]) {
        try {
            $stmt = getDB()->prepare('SELECT program_id FROM programs WHERE program_code = ? LIMIT 1');
            $stmt->execute([$code]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                $id = createProgramPage([
                    'program_code' => $code, 'program_name' => $name,
                    'dept_id' => (int)$dept['dept_id'], 'theme' => 'green',
                ]);
                $stmt = getDB()->prepare('UPDATE programs SET description = ? WHERE program_id = ? AND dept_id = ?');
                $stmt->execute([$description, $id, (int)$dept['dept_id']]);
            } else {
                $id = (int)$id;
            }
            graduateSchoolImportLegacyProgram((int)$id, $code);
        } catch (Throwable $e) {
            // Keep the rest of the site usable if a legacy code is already in
            // use or the optional program-page schema is not installed yet.
            error_log('Graduate School program migration error (' . $code . '): ' . $e->getMessage());
        }
    }
    return getDepartmentBySlug('graduate-school') ?? $dept;
}

/** Import the text and card copy from a legacy static program page once. */
function graduateSchoolImportLegacyProgram(int $programId, string $code): void {
    $files = [
        'GS-PHD' => 'phd-educ-mgt.php', 'GS-MAED' => 'ma-educ.php',
        'GS-MBA' => 'ma-business-admin.php', 'GS-MPA' => 'ma-public-admin.php',
    ];
    if (!isset($files[$code])) return;
    $source = __DIR__ . '/../pages/academics/graduate-school/programs/' . $files[$code];
    $html = is_file($source) ? file_get_contents($source) : false;
    if (!is_string($html) || $html === '') return;

    require_once __DIR__ . '/../components/program-page-public.php';
    $current = getProgramPageById($programId);
    if (!$current) return;
    $stored = json_decode((string)($current['page_content'] ?? ''), true);
    if (is_array($stored) && $stored) return; // never overwrite editor changes

    $text = static function (string $markup): string {
        $value = html_entity_decode(strip_tags($markup), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    };
    $section = static function (string $markup, string $class): string {
        $q = preg_quote($class, '~');
        return preg_match('~<section\b(?=[^>]*\bclass=["\'][^"\']*\b' . $q . '\b)[^>]*>(.*?)</section>~is', $markup, $m) ? $m[1] : '';
    };
    $firstClass = static function (string $markup, string $class) use ($text): string {
        $q = preg_quote($class, '~');
        if (!preg_match('~<([a-z0-9]+)\b(?=[^>]*\bclass=["\'][^"\']*\b' . $q . '\b)[^>]*>(.*?)</\1>~is', $markup, $m)) return '';
        return $text($m[2]);
    };
    $firstAll = static function (string $markup, string $class) use ($text): array {
        $q = preg_quote($class, '~');
        preg_match_all('~<([a-z0-9]+)\b(?=[^>]*\bclass=["\'][^"\']*\b' . $q . '\b)[^>]*>(.*?)</\1>~is', $markup, $matches);
        return array_map($text, $matches[2] ?? []);
    };
    $about = $section($html, 'template-about');
    $why = $section($html, 'template-why');
    $curriculum = $section($html, 'template-curriculum');
    $career = $section($html, 'template-career');
    $cta = $section($html, 'template-cta');
    $texts = [
        'subtitle' => $firstClass($html, 'template-subtitle'),
        'hero_desc' => $firstClass($html, 'template-desc'),
        'about_title' => $firstClass($about, 'template-section-title'),
        'about_text' => $firstClass($about, 'template-section-text'),
        'vision_title' => $firstClass($about, 'template-vm-card-title'),
        'vision_text' => $firstClass($about, 'template-vm-card-text'),
        'mission_title' => ($firstAll($about, 'template-vm-card-title')[1] ?? ''),
        'mission_text' => ($firstAll($about, 'template-vm-card-text')[1] ?? ''),
        'why_title' => $firstClass($why, 'template-section-title'),
        'why_intro' => $firstClass($why, 'whychoose-subtitle'),
        'curriculum_title' => $firstClass($curriculum, 'template-section-title'),
        'curriculum_intro' => $firstClass($curriculum, 'template-section-text'),
        'career_title' => $firstClass($career, 'template-section-title'),
        'cta_title' => $firstClass($cta, 'template-cta-title'),
        'cta_text' => $firstClass($cta, 'template-cta-desc'),
        'cta_button' => $firstClass($cta, 'template-cta-btn'),
    ];
    if (preg_match('~data-bg=["\']([^"\']+)["\']~i', $html, $heroImage)
        || preg_match('~background-image:\s*url\(["\']?([^"\')]+)["\']?\)~i', $html, $heroImage)) {
        $texts['hero_image_path'] = $heroImage[1];
    }
    if (preg_match('~<img\b(?=[^>]*class=["\'][^"\']*\btemplate-seal\b)[^>]*src=["\']([^"\']+)["\']~i', $html, $sealImage)) {
        $texts['seal_path'] = $sealImage[1];
    }
    $content = [];
    foreach (array_keys(programPageContentFields()) as $key) $content[$key] = $texts[$key] ?? '';
    $stmt = getDB()->prepare('UPDATE programs SET page_content = ? WHERE program_id = ? AND (page_content IS NULL OR page_content = "")');
    $stmt->execute([json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $programId]);

    // The text card rows use the same item saver as visual edits. Skip a list
    // when it has already been migrated or edited.
    foreach ([['whychoose-card', 'why', 'whychoose-title', 'whychoose-desc'], ['career-card', 'career', 'career-title', 'career-desc']] as [$cardClass, $type, $titleClass, $bodyClass]) {
        $check = getDB()->prepare('SELECT COUNT(*) FROM program_items WHERE program_id = ? AND item_type = ?');
        $check->execute([$programId, $type]);
        if ((int)$check->fetchColumn() > 0) continue;
        $fragment = $type === 'why' ? $why : $career;
        $q = preg_quote($cardClass, '~');
        preg_match_all('~<div\b(?=[^>]*\bclass=["\'][^"\']*\b' . $q . '\b)[^>]*>(.*?)(?=<div\b(?=[^>]*\bclass=["\'][^"\']*\b' . $q . '\b)[^>]*>|$)~is', $fragment, $cards);
        $rows = [];
        $images = [];
        foreach ($cards[1] ?? [] as $card) {
            $title = $firstClass($card, $titleClass);
            if ($title === '') continue;
            $rows[] = ['title' => $title, 'body' => $firstClass($card, $bodyClass)];
            $images[] = preg_match('~<img\b[^>]*src=["\']([^"\']+)["\']~i', $card, $image) ? $image[1] : null;
        }
        if ($rows) {
            $rows = array_slice($rows, 0, $type === 'why' ? 6 : 12);
            saveProgramPageItems($programId, $type, $rows);
            if ($type === 'career') {
                $update = getDB()->prepare('UPDATE program_items SET image_path = ? WHERE program_id = ? AND item_type = ? AND display_order = ?');
                foreach (array_slice($images, 0, count($rows)) as $i => $path) {
                    if (is_string($path) && preg_match('~^/assets/images/[a-z0-9_./() -]+\.(?:png|jpe?g|webp|svg)$~i', $path)) {
                        $update->execute([$path, $programId, 'career', $i + 1]);
                    }
                }
            }
        }
    }

    $check = getDB()->prepare('SELECT COUNT(*) FROM program_items WHERE program_id = ? AND item_type = ?');
    $check->execute([$programId, 'badge']);
    if ((int)$check->fetchColumn() === 0 && preg_match_all("~\\['icon'\\s*=>\\s*'([^']*)',\\s*'title'\\s*=>\\s*'([^']*)',\\s*'subtitle'\\s*=>\\s*'([^']*)'\\]~", $html, $badges, PREG_SET_ORDER)) {
        $rows = [];
        foreach ($badges as $badge) $rows[] = ['icon' => $badge[1], 'title' => $badge[2], 'subtitle' => $badge[3]];
        if ($rows) saveProgramPageItems($programId, 'badge', array_slice($rows, 0, 6));
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

    if (trim($office) !== trim((string)($dept['dean_office'] ?? ''))) {
        if (($_SESSION['user_role'] ?? '') !== 'Super Admin') {
            throw new Exception('Only Super Admin can change office locations.');
        }
        if (($dept['office_building_id'] ?? null) !== null) {
            throw new Exception('Update this office through its location card in the Visual Editor.');
        }
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
    $bio    = $clean(richTextNormalize($bio));
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
 * Rename one department: the name shown on the College Departments page and
 * in each program's breadcrumb.
 *
 *  - Only the display name (dept_name) changes. The slug — which the page
 *    filenames, links and logic use — is never touched, so no URL breaks.
 *  - The name is trimmed and runs of whitespace are collapsed to one space.
 *  - It is required, at most 255 characters (the column size), and can't be
 *    the name of another department (so two cards never look identical).
 *  - Saving the name it already has is not an error and changes nothing.
 *
 * Returns the refreshed department row. Throws Exception with a
 * user-friendly message on validation failure.
 */
function saveDepartmentName(int $deptId, string $name): array {
    $dept = getDepartmentById($deptId);
    if (!$dept) {
        throw new Exception('Department not found.');
    }

    $clean = preg_replace('/\s+/u', ' ', trim($name));
    if ($clean === null) {
        throw new Exception('The department name contains characters that can\'t be saved.');
    }
    $clean = trim($clean);
    if ($clean === '' && !contentPublishAllowsIncomplete()) {
        throw new Exception('Please enter a department name.');
    }
    if (mb_strlen($clean) > 255) {
        throw new Exception('The department name is too long (maximum 255 characters).');
    }

    if ($clean === $dept['dept_name']) {
        return $dept; // nothing changed
    }

    try {
        $db = getDB();

        // The column's collation is case-insensitive, so this also catches "college of nursing".
        $stmt = $db->prepare("SELECT 1 FROM departments WHERE dept_id <> ? AND dept_name = ? LIMIT 1");
        $stmt->execute([$deptId, $clean]);
        if ($stmt->fetchColumn() !== false) {
            throw new Exception('Another department is already named "' . $clean . '". Please choose a different name.');
        }

        $stmt = $db->prepare("UPDATE departments SET dept_name = ? WHERE dept_id = ?");
        $stmt->execute([$clean, $deptId]);
    } catch (PDOException $e) {
        error_log('Save department name error: ' . $e->getMessage());
        throw new Exception('Could not rename the department. Please try again.');
    }

    logActivity(
        (int)($_SESSION['user_id'] ?? 0), 'UPDATE', 'departments', $deptId,
        "Renamed department \"{$dept['dept_name']}\" to \"{$clean}\""
    );

    return getDepartmentById($deptId) ?? $dept;
}

/**
 * The slice of a department the Content Editor needs (JSON-safe).
 */
function departmentAdminPayload(array $dept): array {
    return [
        'office_location' => departmentOfficeLocation($dept),
        'office_buildings' => campusOfficeBuildings(),
        'can_edit_office' => ($_SESSION['user_role'] ?? '') === 'Super Admin',
        'dept_id'         => (int)$dept['dept_id'],
        'slug'            => $dept['slug'] ?? null,
        'name'            => $dept['dept_name'],
        'dept_name'       => $dept['dept_name'],   // the editor's field key
        'description'     => $dept['description']  ?? null,
        'hero_text'       => $dept['hero_text']    ?? null,
        'about_text'      => $dept['about_text']   ?? null,
        'facebook_url'    => $dept['facebook_url'] ?? null,
        'office_label'    => $dept['office_label'] ?? null,
        'theme_color'     => $dept['theme_color']  ?? null,
        'archived'        => !empty($dept['archived_at']),
        'logo_url'        => $dept['logo_url'] ?? null,
        'banner_url'      => departmentBanner($dept)['file_path'] ?? null,
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
// COLLEGE DEPARTMENTS — FULL CRUD
// ============================================================
// Everything below powers the Content Editor's College Departments
// manager: creating departments, editing their core details, archiving
// (hiding from the public site without losing data) and deleting them.
// The public College Departments page and the generic department.php
// template read the same `departments` rows, so every change here is
// reflected on the website immediately. No department names, slugs or
// IDs are hardcoded anywhere in the frontend.

/**
 * Generate a slug that is unique among `departments` (the column's
 * collation is case-insensitive, so "CBA" and "cba" collide — that's
 * intended). $excludeDeptId keeps a department's own slug out of the
 * collision check when it is being regenerated around an existing row.
 */
function uniqueDepartmentSlug(string $name, ?int $excludeDeptId = null): string {
    $base = generateSlug($name);
    if ($base === '') {
        $base = 'department';
    }

    $db   = getDB();
    $slug = $base;
    $i    = 2;
    while (true) {
        if ($excludeDeptId !== null) {
            $stmt = $db->prepare("SELECT 1 FROM departments WHERE slug = ? AND dept_id <> ? LIMIT 1");
            $stmt->execute([$slug, $excludeDeptId]);
        } else {
            $stmt = $db->prepare("SELECT 1 FROM departments WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
        }
        if ($stmt->fetchColumn() === false) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

/**
 * Validate + normalize the department "details" form. Returns the cleaned
 * fields with empty strings converted to NULL (matching how the rest of
 * this file stores optional text). Throws Exception with a user-safe
 * message on any validation failure.
 */
function cleanDepartmentDetails(array $data): array {
    $name = preg_replace('/\s+/u', ' ', trim((string)($data['dept_name'] ?? '')));
    if ($name === null || (trim($name) === '' && !contentPublishAllowsIncomplete())) {
        throw new Exception('Please enter a department name.');
    }
    $name = trim($name);
    if (mb_strlen($name) > 255) {
        throw new Exception('The department name is too long (maximum 255 characters).');
    }

    $description = richTextNormalize((string)($data['description'] ?? ''));
    $aboutText   = trim((string)($data['about_text'] ?? ''));
    if (preg_match('/<[a-z][\s\S]*>/i', $aboutText)) $aboutText = richTextSanitize($aboutText);
    if (mb_strlen($description) > 10000 || mb_strlen($aboutText) > 10000) {
        throw new Exception('The description/about text is too long (maximum 10000 characters).');
    }

    $heroText = trim((string)($data['hero_text'] ?? ''));
    if (mb_strlen($heroText) > 255) {
        throw new Exception('The hero text is too long (maximum 255 characters).');
    }

    $officeLabel = trim((string)($data['office_label'] ?? ''));
    if (mb_strlen($officeLabel) > 100) {
        throw new Exception('The office label is too long (maximum 100 characters).');
    }

    $facebookUrl = trim((string)($data['facebook_url'] ?? ''));
    if ($facebookUrl !== '') {
        if (!preg_match('#^https?://#i', $facebookUrl)) {
            throw new Exception('The Facebook URL must start with http:// or https:// (or leave it empty).');
        }
        if (mb_strlen($facebookUrl) > 255) {
            throw new Exception('The Facebook URL is too long (maximum 255 characters).');
        }
    }

    $themeColor = trim((string)($data['theme_color'] ?? ''));
    if ($themeColor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
        throw new Exception('The theme color must be a #RRGGBB value (e.g. #1F5E2C) or empty.');
    }

    $nullIfEmpty = fn(string $v): ?string => $v === '' ? null : $v;

    return [
        'dept_name'    => $name,
        'description'  => $nullIfEmpty($description),
        'about_text'   => $nullIfEmpty($aboutText),
        'hero_text'    => $nullIfEmpty($heroText),
        'facebook_url' => $nullIfEmpty($facebookUrl),
        'office_label' => $nullIfEmpty($officeLabel),
        'theme_color'  => $nullIfEmpty($themeColor),
    ];
}

/**
 * Two departments must never share a display name (the column collation is
 * case-insensitive, so this also catches "college of nursing").
 */
function assertDepartmentNameAvailable(string $name, ?int $excludeDeptId): void {
    $db = getDB();
    if ($excludeDeptId !== null) {
        $stmt = $db->prepare("SELECT 1 FROM departments WHERE dept_id <> ? AND dept_name = ? LIMIT 1");
        $stmt->execute([$excludeDeptId, $name]);
    } else {
        $stmt = $db->prepare("SELECT 1 FROM departments WHERE dept_name = ? LIMIT 1");
        $stmt->execute([$name]);
    }
    if ($stmt->fetchColumn() !== false) {
        throw new Exception('Another department is already named "' . $name . '". Please choose a different name.');
    }
}

/**
 * Create (dept_id = 0) or update a department's core details: name,
 * card description, department-page texts, Facebook URL, office label
 * and theme color. The slug is generated automatically at creation and
 * never changes afterwards, so renaming a department can never break an
 * existing URL (see saveDepartmentName()).
 *
 * Returns the refreshed department row. Throws Exception with a
 * user-friendly message on validation/storage failure.
 */
function saveDepartmentDetails(array $data): array {
    $db      = getDB();
    $deptId  = (int)($data['dept_id'] ?? 0);
    $details = cleanDepartmentDetails($data);

    if ($deptId > 0) {
        $current = getDepartmentById($deptId);
        if ($current && trim((string)$details['office_label']) !== trim((string)($current['office_label'] ?? ''))
            && ($_SESSION['user_role'] ?? '') !== 'Super Admin') {
            throw new Exception('Only Super Admin can change office locations.');
        }
    }

    try {
        if ($deptId > 0) {
            if (!getDepartmentById($deptId)) {
                throw new Exception('Department not found.');
            }
            assertDepartmentNameAvailable($details['dept_name'], $deptId);

            $stmt = $db->prepare("
                UPDATE departments
                SET dept_name = ?, description = ?, about_text = ?, hero_text = ?,
                    facebook_url = ?, office_label = ?, theme_color = ?
                WHERE dept_id = ?
            ");
            $stmt->execute([
                $details['dept_name'],
                $details['description'],
                $details['about_text'],
                $details['hero_text'],
                $details['facebook_url'],
                $details['office_label'],
                $details['theme_color'],
                $deptId,
            ]);
            logActivity((int)($_SESSION['user_id'] ?? 0), 'UPDATE', 'departments', $deptId,
                'Updated details for ' . $details['dept_name']);
        } else {
            assertDepartmentNameAvailable($details['dept_name'], null);
            $slug = uniqueDepartmentSlug($details['dept_name']);

            $stmt = $db->prepare("
                INSERT INTO departments (dept_name, slug, description, about_text, hero_text,
                                         facebook_url, office_label, theme_color)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $details['dept_name'],
                $slug,
                $details['description'],
                $details['about_text'],
                $details['hero_text'],
                $details['facebook_url'],
                $details['office_label'],
                $details['theme_color'],
            ]);
            $deptId = (int)$db->lastInsertId();
            logActivity((int)($_SESSION['user_id'] ?? 0), 'CREATE', 'departments', $deptId,
                'Created department: ' . $details['dept_name'] . ' (slug: ' . $slug . ')');
        }
    } catch (PDOException $e) {
        error_log('Save department details error: ' . $e->getMessage());
        throw new Exception('Could not save the department. Please try again.');
    }

    $dept = getDepartmentById($deptId);
    if (!$dept) {
        throw new Exception('Department not found.');
    }
    return $dept;
}

/**
 * Permanently delete a department. Programs and announcements that point
 * at it are NOT deleted — the FK constraints (fk_programs_dept,
 * fk_ann_dept) detach them with ON DELETE SET NULL. The department's
 * logo and dean photo are removed from disk/media_library only when no
 * other record references them.
 */
function deleteDepartment(int $deptId): void {
    $dept = getDepartmentById($deptId);
    if (!$dept) {
        throw new Exception('That department was already removed. Please refresh the page.');
    }

    $db = getDB();
    try {
        $stmt = $db->prepare("DELETE FROM departments WHERE dept_id = ?");
        $stmt->execute([$deptId]);
    } catch (PDOException $e) {
        error_log('Delete department error: ' . $e->getMessage());
        throw new Exception('Could not delete the department. Please try again.');
    }

    if (!empty($dept['media_id'])) {
        deleteMediaIfUnreferenced((int)$dept['media_id']);
    }
    if (!empty($dept['dean_media_id'])) {
        deleteMediaIfUnreferenced((int)$dept['dean_media_id']);
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'DELETE', 'departments', $deptId,
        'Deleted department: ' . $dept['dept_name']);
}

/**
 * Archive (hide from the public website, keep the data) or restore a
 * department. Restoring puts it back on the College Departments page.
 * Doing something that's already done is not an error.
 */
function setDepartmentArchived(int $deptId, bool $archive): array {
    $dept = getDepartmentById($deptId);
    if (!$dept) {
        throw new Exception('Department not found.');
    }

    $isArchived = !empty($dept['archived_at']);
    if ($isArchived === $archive) {
        return $dept; // already in the requested state
    }

    $db = getDB();
    try {
        $stmt = $db->prepare($archive
            ? "UPDATE departments SET archived_at = NOW() WHERE dept_id = ?"
            : "UPDATE departments SET archived_at = NULL WHERE dept_id = ?");
        $stmt->execute([$deptId]);
    } catch (PDOException $e) {
        error_log('Archive department error: ' . $e->getMessage());
        throw new Exception('Could not update the department. Please try again.');
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'UPDATE', 'departments', $deptId,
        ($archive ? 'Archived department: ' : 'Restored department: ') . $dept['dept_name']);

    return getDepartmentById($deptId) ?? $dept;
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
    $message  = $clean(richTextNormalize($message));

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
    $own = trim((string)($row['program_name'] ?? ''));          // programs.program_name (set in Content Editor)
    $row['program_name'] = $own !== '' ? $own : getProgramCategoryLabel($row['program_code'] ?? null);
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


// ============================================================
// ADMINISTRATION PAGE  (About → Administration)
// ============================================================
// The four tabs of the public Administration page live in three tables:
//   admin_sections — one row per tab (title, description, layout)
//   admin_people   — the cards of the three "people" tabs
//                    (Board of Trustees, Corporate Officers, Board of Administration)
//   admin_offices  — the cards of "Heads of Student Services"
//
// Photos go through media_library like every other upload (media_id).
// image_path always holds the card's current photo URL; for the photos that
// shipped with the site it is the only value (/assets/images/...), because
// those were never uploaded through the admin panel.
//
// A card can be ARCHIVED (archived_at set): it disappears from the public
// page but stays in the database and can be restored from the editor.
// Needs sql/administration-archive-migration.sql; until that is run the
// editor simply hides its Archive buttons and everything else keeps working.
//
// Edited in:  Content Editor → Administration (components/administration-manager.php)
// Saved by:   the admin_* actions in action/page-content-handler.php
// Read by:    pages/about/administration.php via getAdministrationContent()
//
// Every function below that the editor calls throws an Exception with a
// message that is safe to show to the admin; database errors are logged and
// replaced with a generic message.

/**
 * Public URL of an Administration photo, or null when there is none or the
 * file is missing on disk (so the page shows the school logo instead of a
 * broken image). Allowed: uploaded photos and the photos bundled with the site.
 */
function administrationPhotoUrl(?string $webPath): ?string {
    if (!$webPath || strpos($webPath, '..') !== false) {
        return null;
    }
    if (strpos($webPath, '/uploads/') !== 0 && strpos($webPath, '/assets/images/') !== 0) {
        return null;
    }
    return is_file(__DIR__ . '/..' . $webPath) ? $webPath : null;
}

/**
 * True once sql/administration-archive-migration.sql has been run (both
 * Administration card tables have an `archived_at` column). Checked once per
 * request. When false, nothing is ever treated as archived.
 */
function administrationArchiveSupported(): bool {
    static $supported = null;
    if ($supported === null) {
        $supported = true;
        try {
            $db = getDB();
            foreach (['admin_people', 'admin_offices'] as $table) {
                $stmt = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'archived_at'");
                if (!$stmt || !$stmt->fetch()) {
                    $supported = false;
                    break;
                }
            }
        } catch (Exception $e) {
            $supported = false;
        }
    }
    return $supported;
}

/**
 * Every section with its cards, keyed by section key, in display order.
 * Archived cards are left out unless $includeArchived is true (the editor).
 * Shape (shared by the public page and the editor):
 *   key, title, desc, layout ('people'|'offices'), can_archive,
 *   people  => [ [id, name, role, image, archived, archived_at], ... ]
 *   offices => [ [id, office, name, role, image, archived, archived_at,
 *                 contact => [phone, email, location, hours],
 *                 services => [string, ...]], ... ]
 * Returns [] when the tables are missing or empty.
 */
function fetchAdministrationSections(bool $includeArchived = false): array {
    try {
        $db = getDB();
        $canArchive = administrationArchiveSupported();

        $rows = $db->query("
            SELECT section_key, title, description, layout
            FROM admin_sections
            ORDER BY display_order ASC, section_key ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return [];
        }

        $sections = [];
        foreach ($rows as $r) {
            $sections[$r['section_key']] = [
                'key'     => $r['section_key'],
                'title'   => (string)$r['title'],
                'desc'    => (string)($r['description'] ?? ''),
                'layout'  => $r['layout'] === 'offices' ? 'offices' : 'people',
                'can_archive' => $canArchive,
                'people'  => [],
                'offices' => [],
            ];
        }

        $archivedCol = $canArchive ? 'p.archived_at' : 'NULL';
        $archivedFilter = ($canArchive && !$includeArchived) ? 'WHERE p.archived_at IS NULL' : '';
        $people = $db->query("
            SELECT p.person_id, p.section_key, p.name, p.role,
                   COALESCE(m.file_path, p.image_path) AS photo,
                   {$archivedCol} AS archived_at
            FROM admin_people p
            LEFT JOIN media_library m ON m.media_id = p.media_id
            {$archivedFilter}
            ORDER BY p.display_order ASC, p.person_id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($people as $p) {
            if (!isset($sections[$p['section_key']])) continue;
            $sections[$p['section_key']]['people'][] = [
                'id'    => (int)$p['person_id'],
                'name'  => (string)$p['name'],
                'role'  => (string)$p['role'],
                'image' => administrationPhotoUrl($p['photo']),
                'archived'    => $p['archived_at'] !== null,
                'archived_at' => $p['archived_at'],
            ];
        }

        $officeFilter = ($canArchive && !$includeArchived) ? 'WHERE o.archived_at IS NULL' : '';
        $offices = $db->query("
            SELECT o.*, COALESCE(m.file_path, o.image_path) AS photo
            FROM admin_offices o
            LEFT JOIN media_library m ON m.media_id = o.media_id
            {$officeFilter}
            ORDER BY o.display_order ASC, o.office_id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($offices as $o) {
            if (!isset($sections[$o['section_key']])) continue;
            $services = preg_split('/\r\n|\r|\n/', (string)($o['services'] ?? ''));
            $services = array_values(array_filter(array_map('trim', $services), 'strlen'));
            $sections[$o['section_key']]['offices'][] = [
                'id'       => (int)$o['office_id'],
                'office'   => (string)$o['office_name'],
                'name'     => (string)$o['head_name'],
                'role'     => (string)$o['head_role'],
                'image'    => administrationPhotoUrl($o['photo']),
                'archived'    => $canArchive && ($o['archived_at'] ?? null) !== null,
                'archived_at' => $canArchive ? ($o['archived_at'] ?? null) : null,
                'contact'  => [
                    'phone'    => (string)($o['phone'] ?? ''),
                    'email'    => (string)($o['email'] ?? ''),
                    'location' => (string)($o['location'] ?? ''),
                    'hours'    => (string)($o['hours'] ?? ''),
                ],
                'services' => $services,
            ];
        }

        return $sections;
    } catch (Exception $e) {
        error_log('Fetch administration sections error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Content for the public Administration page, keyed by section key — or
 * null when the tables aren't set up / are empty, so the page can fall back
 * to its built-in defaults. Archived cards are never included.
 */
function getAdministrationContent(): ?array {
    $sections = fetchAdministrationSections();
    return $sections ?: null;
}

/**
 * The same content as a plain list (JSON-safe) for the Content Editor,
 * archived cards included (flagged `archived`). An empty array means
 * "tables not set up yet".
 */
function administrationAdminPayload(): array {
    return array_values(fetchAdministrationSections(true));
}

/**
 * One section row (key, title, layout), or null if it doesn't exist.
 */
function getAdministrationSectionRow(string $key): ?array {
    try {
        $stmt = getDB()->prepare("SELECT section_key, title, layout FROM admin_sections WHERE section_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log('Get administration section error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Clean a single-line text field: collapse whitespace/newlines to single
 * spaces, enforce the maximum length (in characters) and "required".
 * $label is a phrase that reads naturally in the messages, e.g. "the name":
 * "Please enter the name." / "The name is too long (maximum 255 characters)."
 */
function administrationCleanLine(string $value, int $max, string $label, bool $required = false): string {
    if (!function_exists('mb_strlen')) throw new RuntimeException('Saving administration content requires the PHP mbstring extension. Enable mbstring in your hosting PHP settings.');
    $clean = preg_replace('/\s+/u', ' ', trim($value));
    if ($clean === null) {
        throw new Exception(ucfirst($label) . ' contains characters that can\'t be saved.');
    }
    $clean = trim($clean);
    if ($required && $clean === '' && !contentPublishAllowsIncomplete()) {
        throw new Exception('Please enter ' . $label . '.');
    }
    if (mb_strlen($clean) > $max) {
        throw new Exception(ucfirst($label) . " is too long (maximum {$max} characters).");
    }
    return $clean;
}

/**
 * Clean a multi-line text field (operating hours, services): one entry per
 * line, each line trimmed, empty lines dropped, stored with "\n" newlines.
 */
function administrationCleanLines(string $value, int $max, string $label): string {
    if (!function_exists('mb_strlen')) throw new RuntimeException('Saving administration content requires the PHP mbstring extension. Enable mbstring in your hosting PHP settings.');
    $lines = preg_split('/\r\n|\r|\n/', $value);
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
    $clean = implode("\n", $lines);
    if (mb_strlen($clean) > $max) {
        throw new Exception("There's too much text in {$label} (maximum {$max} characters).");
    }
    return $clean;
}

/**
 * Decide what an admin's photo choice means for one card:
 *   new file uploaded  → store it (throws, storing nothing, if it's invalid)
 *   "remove" ticked    → no photo
 *   neither            → keep the current photo
 * Returns [media_id|null, image_path|null, stored upload|null].
 */
function administrationResolvePhoto(?array $file, bool $remove, ?int $currentMediaId, ?string $currentPath): array {
    $hasFile = !empty($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasFile) {
        $stored = storeDepartmentUpload($file, 'admin_photo');
        return [$stored['media_id'], $stored['path'], $stored];
    }
    if ($remove) {
        return [null, null, null];
    }
    return [$currentMediaId, $currentPath, null];
}

/**
 * Edit a tab's title and description.
 */
function saveAdministrationSection(string $sectionKey, string $title, string $description): void {
    $title = administrationCleanLine($title, 255, 'the section title', true);

    $description = richTextNormalize(str_replace(["\r\n", "\r"], "\n", $description));
    if (mb_strlen($description) > 2000) {
        throw new Exception('The description is too long (maximum 2000 characters).');
    }

    if (!getAdministrationSectionRow($sectionKey)) {
        throw new Exception('That section no longer exists. Please refresh the page.');
    }

    try {
        $stmt = getDB()->prepare("
            UPDATE admin_sections
            SET title = ?, description = ?, updated_by = ?
            WHERE section_key = ?
        ");
        $stmt->execute([$title, $description === '' ? null : $description, $_SESSION['user_id'] ?? null, $sectionKey]);
    } catch (PDOException $e) {
        error_log('Save administration section error: ' . $e->getMessage());
        throw new Exception('Could not save the section. Please try again.');
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'UPDATE', 'admin_sections', null, "Administration: updated the \"{$title}\" heading");
}

/**
 * Add a person card, or edit one ($personId > 0).
 * New cards go to the end of their section.
 */
function saveAdministrationPerson(
    int $personId,
    string $sectionKey,
    string $name,
    string $role,
    ?array $photoFile,
    bool $removePhoto
): void {
    // Validate everything BEFORE storing the photo, so a bad form leaves nothing behind.
    $name = administrationCleanLine($name, 255, 'the name', true);
    $role = administrationCleanLine($role, 500, 'the position');

    $db = getDB();
    $current = null;

    if ($personId > 0) {
        try {
            $stmt = $db->prepare("SELECT person_id, section_key, media_id, image_path FROM admin_people WHERE person_id = ?");
            $stmt->execute([$personId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Load administration person error: ' . $e->getMessage());
            throw new Exception('Could not load this card. Please try again.');
        }
        if (!$current) {
            throw new Exception('That card no longer exists. Please refresh the page.');
        }
        $sectionKey = $current['section_key'];
    } else {
        $section = getAdministrationSectionRow($sectionKey);
        if (!$section || $section['layout'] !== 'people') {
            throw new Exception('Please choose a valid section.');
        }
    }

    $oldMediaId = ($current && $current['media_id'] !== null) ? (int)$current['media_id'] : null;
    [$mediaId, $imagePath, $stored] = administrationResolvePhoto(
        $photoFile, $removePhoto, $oldMediaId, $current['image_path'] ?? null
    );

    try {
        if ($current) {
            $stmt = $db->prepare("
                UPDATE admin_people
                SET name = ?, role = ?, media_id = ?, image_path = ?
                WHERE person_id = ?
            ");
            $stmt->execute([$name, $role, $mediaId, $imagePath, $personId]);
            $recordId = $personId;
        } else {
            $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 FROM admin_people WHERE section_key = ?");
            $stmt->execute([$sectionKey]);
            $nextOrder = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("
                INSERT INTO admin_people (section_key, name, role, media_id, image_path, display_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sectionKey, $name, $role, $mediaId, $imagePath, $nextOrder]);
            $recordId = (int)$db->lastInsertId();
        }
    } catch (PDOException $e) {
        discardStoredDepartmentUpload($stored);
        error_log('Save administration person error: ' . $e->getMessage());
        throw new Exception('Could not save this card. Please try again.');
    }

    if ($oldMediaId !== null && $oldMediaId !== $mediaId) {
        deleteMediaIfUnreferenced($oldMediaId);
    }

    logActivity(
        (int)($_SESSION['user_id'] ?? 0), $current ? 'UPDATE' : 'CREATE', 'admin_people', $recordId,
        'Administration: ' . ($current ? 'updated ' : 'added ') . $name
    );
}

/**
 * Add an office card (Heads of Student Services), or edit one ($officeId > 0).
 * New cards go to the end of their section.
 */
function saveAdministrationOffice(
    int $officeId,
    string $sectionKey,
    string $officeName,
    string $headName,
    string $headRole,
    string $phone,
    string $email,
    string $location,
    string $hours,
    string $services,
    ?array $photoFile,
    bool $removePhoto
): void {
    // Validate everything BEFORE storing the photo, so a bad form leaves nothing behind.
    $officeName = administrationCleanLine($officeName, 255, 'the office name', true);
    $headName   = administrationCleanLine($headName, 255, 'the name of the head of office', true);
    $headRole   = administrationCleanLine($headRole, 500, 'the position');
    $phone      = administrationCleanLine($phone, 100, 'the contact number');
    $email      = administrationCleanLine($email, 255, 'the email address');
    $location   = administrationCleanLine($location, 255, 'the location');
    $hours      = administrationCleanLines($hours, 500, 'the operating hours');
    $services   = administrationCleanLines($services, 5000, 'the list of services');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address, or leave it empty.');
    }

    $db = getDB();
    $current = null;

    if ($officeId > 0) {
        try {
            $stmt = $db->prepare("SELECT office_id, section_key, media_id, image_path FROM admin_offices WHERE office_id = ?");
            $stmt->execute([$officeId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Load administration office error: ' . $e->getMessage());
            throw new Exception('Could not load this office. Please try again.');
        }
        if (!$current) {
            throw new Exception('That office no longer exists. Please refresh the page.');
        }
        $sectionKey = $current['section_key'];
    } else {
        $section = getAdministrationSectionRow($sectionKey);
        if (!$section || $section['layout'] !== 'offices') {
            throw new Exception('Please choose a valid section.');
        }
    }

    $oldMediaId = ($current && $current['media_id'] !== null) ? (int)$current['media_id'] : null;
    [$mediaId, $imagePath, $stored] = administrationResolvePhoto(
        $photoFile, $removePhoto, $oldMediaId, $current['image_path'] ?? null
    );

    // Empty optional fields are stored as NULL.
    $orNull = function (string $v): ?string { return $v === '' ? null : $v; };

    try {
        if ($current) {
            $stmt = $db->prepare("
                UPDATE admin_offices
                SET office_name = ?, head_name = ?, head_role = ?, media_id = ?, image_path = ?,
                    phone = ?, email = ?, location = ?, hours = ?, services = ?
                WHERE office_id = ?
            ");
            $stmt->execute([
                $officeName, $headName, $headRole, $mediaId, $imagePath,
                $orNull($phone), $orNull($email), $orNull($location), $orNull($hours), $orNull($services),
                $officeId,
            ]);
            $recordId = $officeId;
        } else {
            $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 FROM admin_offices WHERE section_key = ?");
            $stmt->execute([$sectionKey]);
            $nextOrder = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("
                INSERT INTO admin_offices
                    (section_key, office_name, head_name, head_role, media_id, image_path,
                     phone, email, location, hours, services, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sectionKey, $officeName, $headName, $headRole, $mediaId, $imagePath,
                $orNull($phone), $orNull($email), $orNull($location), $orNull($hours), $orNull($services),
                $nextOrder,
            ]);
            $recordId = (int)$db->lastInsertId();
        }
    } catch (PDOException $e) {
        discardStoredDepartmentUpload($stored);
        error_log('Save administration office error: ' . $e->getMessage());
        throw new Exception('Could not save this office. Please try again.');
    }

    if ($oldMediaId !== null && $oldMediaId !== $mediaId) {
        deleteMediaIfUnreferenced($oldMediaId);
    }

    logActivity(
        (int)($_SESSION['user_id'] ?? 0), $current ? 'UPDATE' : 'CREATE', 'admin_offices', $recordId,
        'Administration: ' . ($current ? 'updated ' : 'added ') . $officeName
    );
}

/**
 * Delete a person card (and its uploaded photo, if nothing else uses it).
 */
function deleteAdministrationPerson(int $personId): void {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT name, media_id FROM admin_people WHERE person_id = ?");
        $stmt->execute([$personId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Load administration person error: ' . $e->getMessage());
        throw new Exception('Could not delete this card. Please try again.');
    }
    if (!$row) {
        throw new Exception('That card was already removed. Please refresh the page.');
    }

    try {
        $stmt = $db->prepare("DELETE FROM admin_people WHERE person_id = ?");
        $stmt->execute([$personId]);
    } catch (PDOException $e) {
        error_log('Delete administration person error: ' . $e->getMessage());
        throw new Exception('Could not delete this card. Please try again.');
    }

    if ($row['media_id'] !== null) {
        deleteMediaIfUnreferenced((int)$row['media_id']);
    }
    logActivity((int)($_SESSION['user_id'] ?? 0), 'DELETE', 'admin_people', $personId, 'Administration: removed ' . $row['name']);
}

/**
 * Delete an office card (and its uploaded photo, if nothing else uses it).
 */
function deleteAdministrationOffice(int $officeId): void {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT office_name, media_id FROM admin_offices WHERE office_id = ?");
        $stmt->execute([$officeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Load administration office error: ' . $e->getMessage());
        throw new Exception('Could not delete this office. Please try again.');
    }
    if (!$row) {
        throw new Exception('That office was already removed. Please refresh the page.');
    }

    try {
        $stmt = $db->prepare("DELETE FROM admin_offices WHERE office_id = ?");
        $stmt->execute([$officeId]);
    } catch (PDOException $e) {
        error_log('Delete administration office error: ' . $e->getMessage());
        throw new Exception('Could not delete this office. Please try again.');
    }

    if ($row['media_id'] !== null) {
        deleteMediaIfUnreferenced((int)$row['media_id']);
    }
    logActivity((int)($_SESSION['user_id'] ?? 0), 'DELETE', 'admin_offices', $officeId, 'Administration: removed ' . $row['office_name']);
}

/**
 * Archive or restore one card (a person or an office).
 *   archive → hidden from the public page, kept in the database
 *   restore → shown again, at the end of its section
 * Doing something that's already done (archiving an archived card) is not
 * an error. $kind is 'person' or 'office'.
 */
function setAdministrationArchived(string $kind, int $id, bool $archive): void {
    if (!administrationArchiveSupported()) {
        throw new Exception('Archiving isn\'t set up yet. Ask your developer to run sql/administration-archive-migration.sql once, then reload this page.');
    }

    // Table and column names come from this fixed list, never from the request.
    $map = [
        'person' => ['admin_people',  'person_id', 'name'],
        'office' => ['admin_offices', 'office_id', 'office_name'],
    ];
    if (!isset($map[$kind])) {
        throw new Exception('Unknown card type.');
    }
    [$table, $idCol, $nameCol] = $map[$kind];
    $db = getDB();

    try {
        $stmt = $db->prepare("SELECT section_key, `{$nameCol}` AS label, archived_at FROM `{$table}` WHERE `{$idCol}` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Load administration card error: ' . $e->getMessage());
        throw new Exception('Could not update this card. Please try again.');
    }
    if (!$row) {
        throw new Exception('That card no longer exists. Please refresh the page.');
    }

    $isArchived = $row['archived_at'] !== null;
    if ($isArchived === $archive) {
        return; // already in the requested state
    }

    try {
        if ($archive) {
            $stmt = $db->prepare("UPDATE `{$table}` SET archived_at = NOW() WHERE `{$idCol}` = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 FROM `{$table}` WHERE section_key = ?");
            $stmt->execute([$row['section_key']]);
            $nextOrder = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("UPDATE `{$table}` SET archived_at = NULL, display_order = ? WHERE `{$idCol}` = ?");
            $stmt->execute([$nextOrder, $id]);
        }
    } catch (PDOException $e) {
        error_log('Archive administration card error: ' . $e->getMessage());
        throw new Exception('Could not update this card. Please try again.');
    }

    logActivity(
        (int)($_SESSION['user_id'] ?? 0), 'UPDATE', $table, $id,
        'Administration: ' . ($archive ? 'archived ' : 'restored ') . $row['label']
    );
}

function archiveAdministrationPerson(int $personId, bool $archive): void {
    setAdministrationArchived('person', $personId, $archive);
}

function archiveAdministrationOffice(int $officeId, bool $archive): void {
    setAdministrationArchived('office', $officeId, $archive);
}

/**
 * Save a new card order for one section. $orderedIds are person_ids (or
 * office_ids for the offices layout), first card first. Any cards the list
 * doesn't mention keep their relative order after the listed ones.
 */
function reorderAdministration(string $sectionKey, array $orderedIds): void {
    $section = getAdministrationSectionRow($sectionKey);
    if (!$section) {
        throw new Exception('That section no longer exists. Please refresh the page.');
    }

    // Table and column names come from this fixed list, never from the request.
    [$table, $idCol] = $section['layout'] === 'offices'
        ? ['admin_offices', 'office_id']
        : ['admin_people', 'person_id'];

    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT `{$idCol}` FROM `{$table}` WHERE section_key = ? ORDER BY display_order ASC, `{$idCol}` ASC");
        $stmt->execute([$sectionKey]);
        $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        error_log('Load administration order error: ' . $e->getMessage());
        throw new Exception('Could not save the new order. Please try again.');
    }

    $wanted = [];
    foreach ($orderedIds as $id) {
        $id = (int)$id;
        if (!in_array($id, $existing, true)) {
            throw new Exception('The new order includes a card that isn\'t in this section. Please refresh the page.');
        }
        if (!in_array($id, $wanted, true)) {
            $wanted[] = $id;
        }
    }
    $final = array_merge($wanted, array_values(array_diff($existing, $wanted)));

    try {
        $db->beginTransaction();
        $upd = $db->prepare("UPDATE `{$table}` SET display_order = ? WHERE `{$idCol}` = ? AND section_key = ?");
        foreach ($final as $position => $id) {
            $upd->execute([$position + 1, $id, $sectionKey]);
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Reorder administration error: ' . $e->getMessage());
        throw new Exception('Could not save the new order. Please try again.');
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'UPDATE', $table, null, "Administration: reordered \"{$section['title']}\"");
}

/* ─────────────────────────────────────────────────────────────────────────
 * College Departments — dynamic detail page: full program CRUD
 * ───────────────────────────────────────────────────────────────────────── */

const PROGRAM_IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

/**
 * Validate + store a program card image, insert it into media_library.
 * Mirrors storeDepartmentUpload() but standalone (that function's config
 * list is local to it). Returns ['media_id','path','abs']; throws with a
 * user-safe message on failure, leaving nothing behind.
 */
function storeProgramImageUpload(array $file): array {
    $file = imageUploadPrepare($file);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception(departmentUploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE), 'program image'));
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('The program image upload failed. Please try again.');
    }
    if ($file['size'] > PROGRAM_IMAGE_MAX_BYTES) {
        throw new Exception('The program image is too large. Maximum size is 5MB.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new Exception('Invalid image type. Allowed: JPG, PNG, WEBP.');
    }

    $filename  = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $uploadDir = __DIR__ . '/../uploads/programs/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('The server could not create the upload folder for the program image.');
    }
    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save the uploaded program image.');
    }
    $filePath = '/uploads/programs/' . $filename;

    $display = trim(preg_replace('/[^\p{L}\p{N}\s._()\-]+/u', '', basename((string)($file['name'] ?? ''))));
    $display = mb_substr($display === '' ? $filename : $display, 0, 200);

    try {
        $db   = getDB();
        imageFramingSaveUpload($filePath, $destination);
        $stmt = $db->prepare("
            INSERT INTO media_library (file_name, file_type, file_size, file_path, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$display, $allowed[$mime], (int)$file['size'], $filePath, $_SESSION['user_id'] ?? null]);
        return ['media_id' => (int)$db->lastInsertId(), 'path' => $filePath, 'abs' => $destination];
    } catch (Exception $e) {
        @unlink($destination);
        error_log('Program image media_library insert error: ' . $e->getMessage());
        throw new Exception('Failed to record the program image in the media library.');
    }
}

const PROGRAM_FULL_SELECT_SQL = "
    SELECT p.*,
           d.dept_name,
           d.slug AS dept_slug,
           pm.file_path AS pdf_path, pm.file_name AS pdf_name, pm.file_size AS pdf_size,
           im.file_path AS image_path, im.file_name AS image_name
    FROM programs p
    LEFT JOIN departments d ON d.dept_id = p.dept_id
    LEFT JOIN media_library pm ON pm.media_id = p.pdf_media_id
    LEFT JOIN media_library im ON im.media_id = p.image_media_id
";

/**
 * hydrateProgram() + the card image fields.
 */
function hydrateProgramFull(array $row): array {
    $row = hydrateProgram($row);
    $row['image_url'] = departmentFileExists($row['image_path'] ?? null) ? $row['image_path'] : null;
    if ($row['image_url'] === null) {
        $row['image_name'] = null;
    }
    return $row;
}

function getProgramFullById(int $programId): ?array {
    try {
        $stmt = getDB()->prepare(PROGRAM_FULL_SELECT_SQL . " WHERE p.program_id = ?");
        $stmt->execute([$programId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? hydrateProgramFull($row) : null;
    } catch (Exception $e) {
        error_log('Get program full error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create ($data['program_id'] = 0) or update a program: code, department
 * assignment, card description, and card image (upload / replace / remove).
 *
 *  - program_code must be one of getProgramCategories() and unique.
 *  - The slug is generated from the code at creation and never changes
 *    (same rule as departments — renames can't break URLs).
 *  - dept_id of 0 / unknown means "unassigned" (NULL).
 *
 * Returns the refreshed program row. Throws Exception with a user-safe
 * message on validation/storage failure.
 */
function saveProgramFull(array $data, ?array $imageFile, bool $removeImage): array {
    assertProgramDepartmentAllowed((int)($data['dept_id'] ?? 0));
    $db        = getDB();
    $programId = (int)($data['program_id'] ?? 0);

    // ── validate text first, so a bad form leaves nothing behind ──
    $code = strtoupper(preg_replace('/\s+/', '', (string)($data['program_code'] ?? '')));
    $valid = getProgramCategories();
    if (!isset($valid[$code])) {
        throw new Exception('Please choose a valid program.');
    }

    $deptId = (int)($data['dept_id'] ?? 0);
    if ($deptId > 0) {
        $stmt = $db->prepare("SELECT 1 FROM departments WHERE dept_id = ?");
        $stmt->execute([$deptId]);
        if ($stmt->fetchColumn() === false) {
            $deptId = 0; // unknown department = unassigned, not an error
        }
    }

    $description = richTextNormalize((string)($data['description'] ?? ''));
    if (mb_strlen($description) > 5000) {
        throw new Exception('The program description is too long (maximum 5000 characters).');
    }
    $description = $description === '' ? null : $description;

    // ── image ──
    $hasImage = !empty($imageFile) && ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newImage = $hasImage ? storeProgramImageUpload($imageFile) : null; // throws if invalid

    $current    = null;
    $oldImageId = null;
    if ($programId > 0) {
        $current = getProgramFullById($programId);
        if (!$current) {
            discardStoredDepartmentUpload($newImage);
            throw new Exception('Program not found.');
        }
        $oldImageId = ($current['image_media_id'] ?? null) !== null ? (int)$current['image_media_id'] : null;
    }
    $imageId = $newImage ? $newImage['media_id'] : ($removeImage ? null : $oldImageId);

    // ── unique code check (excluding self) ──
    try {
        if ($programId > 0) {
            $stmt = $db->prepare("SELECT 1 FROM programs WHERE program_code = ? AND program_id <> ? LIMIT 1");
            $stmt->execute([$code, $programId]);
        } else {
            $stmt = $db->prepare("SELECT 1 FROM programs WHERE program_code = ? LIMIT 1");
            $stmt->execute([$code]);
        }
        if ($stmt->fetchColumn() !== false) {
            discardStoredDepartmentUpload($newImage);
            throw new Exception('That program already exists. Each program can only be added once.');
        }
    } catch (PDOException $e) {
        discardStoredDepartmentUpload($newImage);
        throw new Exception('Could not save the program. Please try again.');
    }

    try {
        if ($programId > 0) {
            $stmt = $db->prepare("
                UPDATE programs
                SET program_code = ?, dept_id = ?, description = ?, image_media_id = ?
                WHERE program_id = ?
            ");
            $stmt->execute([$code, $deptId > 0 ? $deptId : null, $description, $imageId, $programId]);
        } else {
            $slug = generateSlug($valid[$code]); // e.g. "bachelor-of-elementary-education"
            $stmt = $db->prepare("
                INSERT INTO programs (program_code, slug, description, dept_id, image_media_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $slug, $description, $deptId > 0 ? $deptId : null, $imageId]);
            $programId = (int)$db->lastInsertId();
        }
    } catch (PDOException $e) {
        discardStoredDepartmentUpload($newImage);
        error_log('Save program full error: ' . $e->getMessage());
        throw new Exception('Could not save the program. Please try again.');
    }

    if ($oldImageId !== null && $oldImageId !== $imageId) {
        deleteMediaIfUnreferenced($oldImageId);
    }

    logActivity(
        (int)($_SESSION['user_id'] ?? 0),
        $current ? 'UPDATE' : 'CREATE',
        'programs', $programId,
        ($current ? 'Updated program: ' : 'Added program: ') . $valid[$code]
    );

    $program = getProgramFullById($programId);
    if (!$program) {
        throw new Exception('Program not found.');
    }
    return $program;
}

/**
 * Permanently delete a program. Its card image and curriculum PDF are
 * removed from disk/media_library only when nothing else references them.
 */
function deleteProgramFull(int $programId): void {
    $program = getProgramFullById($programId);
    if (!$program) {
        throw new Exception('That program was already removed. Please refresh the page.');
    }

    try {
        $stmt = getDB()->prepare("DELETE FROM programs WHERE program_id = ?");
        $stmt->execute([$programId]);
    } catch (PDOException $e) {
        error_log('Delete program full error: ' . $e->getMessage());
        throw new Exception('Could not delete the program. Please try again.');
    }

    if (!empty($program['image_media_id'])) {
        deleteMediaIfUnreferenced((int)$program['image_media_id']);
    }
    if (!empty($program['pdf_media_id'])) {
        deleteMediaIfUnreferenced((int)$program['pdf_media_id']);
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'DELETE', 'programs', $programId,
        'Deleted program: ' . $program['program_name']);
}

/**
 * The slice of a program the Admin Panel needs (JSON-safe).
 */
function programFullAdminPayload(array $p): array {
    $payload = programAdminPayload($p);
    $payload['dept_id']      = ($p['dept_id'] ?? null) !== null ? (int)$p['dept_id'] : null;
    $payload['dept_name']    = $p['dept_name'] ?? null;
    $payload['description']  = $p['description'] ?? null;
    $payload['image_url']    = $p['image_url'] ?? null;
    $payload['image_name']   = !empty($p['image_url']) ? ($p['image_name'] ?? null) : null;
    return $payload;
}

/* ─────────────────────────────────────────────────────────────────────────
 * PROGRAM PAGES — the dynamic program page (program.php?slug=...)
 * Needs sql/program-page-migration.sql.
 * Public side: components/program-page-public.php.
 * Admin side:  components/program-page-manager.php + admin/action/program-page-handler.php.
 * ───────────────────────────────────────────────────────────────────────── */

/** Max rows per repeating list on a program page. */
function programPageItemLimits(): array {
    return ['badge' => 6, 'core_value' => 12, 'why' => 12, 'career' => 12, 'uniform' => 8, 'facility' => 24, 'hero_slide' => 8];
}

/**
 * Create a new program (the "Add a program" button). It starts hidden-proof:
 * published, in the chosen colour, with its page ready to fill in.
 * Returns the new program_id. Throws Exception with a user-safe message.
 */
function createProgramPage(array $data): int {
    assertProgramDepartmentAllowed((int)($data['dept_id'] ?? 0));
    $db = getDB();

    $code = trim((string)($data['program_code'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{1,9}$/', $code)) {
        throw new Exception('The short code must be 2–10 letters or numbers (a dash is allowed), for example BSBA-MM.');
    }
    $name = trim(preg_replace('/\s+/', ' ', (string)($data['program_name'] ?? '')));
    if ($name === '') {
        throw new Exception('Please enter the full program name.');
    }
    if (mb_strlen($name) > 255) {
        throw new Exception('The program name is too long (maximum 255 characters).');
    }

    $deptId = (int)($data['dept_id'] ?? 0);
    if ($deptId > 0) {
        $stmt = $db->prepare("SELECT 1 FROM departments WHERE dept_id = ?");
        $stmt->execute([$deptId]);
        if ($stmt->fetchColumn() === false) $deptId = 0;
    }

    $accent = programPageResolveTheme($data['theme'] ?? '', $data['theme_custom'] ?? '');

    try {
        $stmt = $db->prepare("SELECT 1 FROM programs WHERE program_code = ? LIMIT 1");
        $stmt->execute([$code]);
        if ($stmt->fetchColumn() !== false) {
            throw new Exception('A program with that short code already exists.');
        }

        // Slug: from the code; numbered if somebody already has it. Never changes later.
        $base = generateSlug($code) ?: 'program';
        $slug = $base;
        for ($n = 2; $n < 50; $n++) {
            $stmt = $db->prepare("SELECT 1 FROM programs WHERE slug = ? UNION SELECT 1 FROM slug_history WHERE old_slug = ? AND `type` = 'program' LIMIT 1");
            $stmt->execute([$slug, $slug]);
            if ($stmt->fetchColumn() === false) break;
            $slug = $base . '-' . $n;
        }

        $stmt = $db->prepare("
            INSERT INTO programs (program_code, slug, program_name, dept_id, theme_color, theme_color_light, is_published, core_values_enabled)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $code, $slug, $name, $deptId > 0 ? $deptId : null,
            $accent['accent'], $accent['light'],
            !empty($data['core_values_enabled']) ? 1 : 0,
        ]);
        $programId = (int)$db->lastInsertId();
    } catch (PDOException $e) {
        error_log('Create program page error: ' . $e->getMessage());
        throw new Exception('Could not add the program. Please try again.');
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'CREATE', 'programs', $programId, 'Added program: ' . $name);
    return $programId;
}

/**
 * Pick the accent/light pair from a preset key, or from a custom #hex.
 * Falls back to green.
 */
function programPageResolveTheme($presetKey, $custom): array {
    require_once __DIR__ . '/../components/program-page-public.php';
    $presets = programPageThemePresets();
    $key = (string)$presetKey;
    if ($key === 'custom') {
        $hex = programPageNormalizeHex($custom);
        if ($hex === null) {
            throw new Exception('Please enter the custom colour as a hex code like #1A7F5A.');
        }
        $rgb   = programPageHexToRgb($hex);
        $light = programPageRgbToHex([255 - (255 - $rgb[0]) * 0.12, 255 - (255 - $rgb[1]) * 0.12, 255 - (255 - $rgb[2]) * 0.12]);
        return ['accent' => $hex, 'light' => $light];
    }
    $p = $presets[$key] ?? $presets['green'];
    return ['accent' => $p['accent'], 'light' => $p['light']];
}

/**
 * Save everything in the "Details" tab of one program: name, department,
 * card text, colour, core-values switch, visibility, every text field of the
 * page, and the hero / seal pictures.
 *
 * $files: ['hero' => $_FILES[..]|null, 'seal' => ...]; $remove: ['hero' => bool, 'seal' => bool]
 * Returns the refreshed program (getProgramPageById). Throws Exception.
 */
function saveProgramPageDetails(int $programId, array $data, array $files = [], array $remove = []): array {
    assertProgramDepartmentAllowed((int)($data['dept_id'] ?? 0));
    require_once __DIR__ . '/../components/program-page-public.php';
    $db = getDB();

    $current = getProgramPageById($programId);
    if (!$current) {
        throw new Exception('Program not found.');
    }

    // ── text ──
    $name = trim(preg_replace('/\s+/', ' ', (string)($data['program_name'] ?? '')));
    if ($name === '' && !contentPublishAllowsIncomplete()) throw new Exception('The program name can\'t be empty.');
    if (mb_strlen($name) > 255) throw new Exception('The program name is too long (maximum 255 characters).');

    $card = richTextNormalize((string)($data['description'] ?? ''));
    if (mb_strlen($card) > 5000) throw new Exception('The short description is too long (maximum 5000 characters).');

    $deptId = (int)($data['dept_id'] ?? 0);
    if ($deptId > 0) {
        $stmt = $db->prepare("SELECT 1 FROM departments WHERE dept_id = ?");
        $stmt->execute([$deptId]);
        if ($stmt->fetchColumn() === false) $deptId = 0;
    }

    $accent = programPageResolveTheme($data['theme'] ?? '', $data['theme_custom'] ?? '');

    $content = [];
    foreach (programPageContentFields() as $key => $spec) {
        $val = trim(str_replace("\r\n", "\n", (string)($data['c_' . $key] ?? '')));
        if ($spec['type'] === 'icon') {
            $val = programPageCleanIcon($val, '');
        } elseif ($spec['type'] === 'path') {
            // Static pictures can't be typed in by hand: keep what is stored.
            $val = $current['content'][$key];
        } elseif ($spec['type'] === 'long') {
            $val = richTextNormalize($val);
        } elseif ($spec['type'] === 'text') {
            $val = preg_replace('/\s+/', ' ', $val);
        }
        if (mb_strlen($val) > $spec['max']) {
            throw new Exception('One of the texts is too long (' . str_replace('_', ' ', $key) . ': maximum ' . $spec['max'] . ' characters).');
        }
        if ($key === 'cta_link' && $val !== '' && !preg_match('#^(/|https?://)#i', $val)) {
            throw new Exception('The button link must start with / or https://');
        }
        $content[$key] = $val;
    }

    // ── pictures (validate + store first, so a bad file changes nothing) ──
    $new = ['hero' => null, 'seal' => null];
    try {
        foreach (['hero', 'seal'] as $slot) {
            $f = $files[$slot] ?? null;
            if (!empty($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $new[$slot] = storeDepartmentUpload($f, 'program_image');
            }
        }
    } catch (Exception $e) {
        discardStoredDepartmentUpload($new['hero']);
        discardStoredDepartmentUpload($new['seal']);
        throw $e;
    }

    $oldHero = !empty($current['hero_media_id']) ? (int)$current['hero_media_id'] : null;
    $oldSeal = !empty($current['seal_media_id']) ? (int)$current['seal_media_id'] : null;
    $heroId  = $new['hero'] ? $new['hero']['media_id'] : (!empty($remove['hero']) ? null : $oldHero);
    $sealId  = $new['seal'] ? $new['seal']['media_id'] : (!empty($remove['seal']) ? null : $oldSeal);
    // Removing a picture also drops the built-in one it was falling back to.
    if (!$new['hero'] && !empty($remove['hero'])) $content['hero_image_path'] = '';
    if (!$new['seal'] && !empty($remove['seal'])) $content['seal_path'] = '';

    try {
        $stmt = $db->prepare("
            UPDATE programs
            SET program_name = ?, description = ?, dept_id = ?, theme_color = ?, theme_color_light = ?,
                core_values_enabled = ?, is_published = ?, hero_media_id = ?, seal_media_id = ?, page_content = ?
            WHERE program_id = ?
        ");
        $stmt->execute([
            $name, $card === '' ? null : $card, $deptId > 0 ? $deptId : null,
            $accent['accent'], $accent['light'],
            !empty($data['core_values_enabled']) ? 1 : 0,
            !empty($data['is_published']) ? 1 : 0,
            $heroId, $sealId,
            json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $programId,
        ]);
    } catch (PDOException $e) {
        discardStoredDepartmentUpload($new['hero']);
        discardStoredDepartmentUpload($new['seal']);
        error_log('Save program page error: ' . $e->getMessage());
        throw new Exception('Could not save the program. Please try again.');
    }

    if ($oldHero !== null && $oldHero !== $heroId) deleteMediaIfUnreferenced($oldHero);
    if ($oldSeal !== null && $oldSeal !== $sealId) deleteMediaIfUnreferenced($oldSeal);

    logActivity((int)($_SESSION['user_id'] ?? 0), 'UPDATE', 'programs', $programId, 'Updated program page: ' . $name);
    return getProgramPageById($programId) ?? $current;
}

/** Largest single facility picture. Keep in step with the "MB" limit in the Program details form (components/program-page-manager.php). */
const PROGRAM_FACILITY_IMAGE_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

/**
 * Facility pictures only: JPG / PNG / WEBP, up to 5 MB, and it must really be
 * an image (not just a renamed file). Throws Exception with a user-safe message.
 * $f is one $_FILES entry.
 */
function programPageCheckFacilityImage(array $f): void {
    $f = imageUploadPrepare($f);
    $max = PROGRAM_FACILITY_IMAGE_MAX_BYTES;
    $err = $f['error'] ?? UPLOAD_ERR_OK;
    if ($err !== UPLOAD_ERR_OK) {
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new Exception('A facility picture is too large. The maximum size is 5 MB.');
        }
        throw new Exception('A facility picture could not be uploaded. Please try again.');
    }
    if ((int)($f['size'] ?? 0) > $max) {
        throw new Exception('A facility picture is too large. The maximum size is 5 MB.');
    }
    $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new Exception('Facility pictures must be JPG, PNG or WEBP.');
    }
    $tmp  = (string)($f['tmp_name'] ?? '');
    $info = ($tmp !== '' && is_readable($tmp)) ? @getimagesize($tmp) : false;
    $okTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (!$info || !in_array($info[2], $okTypes, true)) {
        throw new Exception('One of the facility files is not a valid JPG, PNG or WEBP picture.');
    }
    if ($info[0] * $info[1] > 40000000) {
        throw new Exception('A facility picture has too many pixels. Please use a smaller photo.');
    }
}

/**
 * Top-banner slideshow of a program page ("hero_slide" items): one PICTURE or VIDEO per slide.
 * Stored in /uploads/program-banners/ (not in the media library, like the homepage slideshow).
 * Pictures: JPG, PNG, WEBP, GIF up to 5 MB. Videos: MP4 or WEBM up to 50 MB.
 * $f is one $_FILES entry. Returns ['type' => 'image'|'video', 'path' => '/uploads/program-banners/x.ext', 'abs' => absolute path].
 * Throws Exception with a user-safe message.
 */
function programPageStoreBannerMedia(array $f): array {
    $f = imageUploadPrepare($f);
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new Exception('A banner file is too large for this server (see upload_max_filesize and post_max_size in php.ini). Pictures can be up to 5 MB and videos up to 50 MB.');
        }
        throw new Exception('A banner file could not be uploaded. Please try again.');
    }
    $tmp = (string)($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new Exception('A banner file could not be uploaded. Please try again.');
    }

    $images = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $videos = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

    // Trust the file's real content, never the browser-supplied type or extension.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    if (isset($images[$mime])) {
        $type = 'image'; $ext = $images[$mime]; $max = 5 * 1024 * 1024;
        if (@getimagesize($tmp) === false) {
            throw new Exception('One of the banner pictures is not a valid picture.');
        }
    } elseif (isset($videos[$mime])) {
        $type = 'video'; $ext = $videos[$mime]; $max = 50 * 1024 * 1024;
    } else {
        throw new Exception('Banner slides must be a picture (JPG, PNG, WEBP, GIF) or a video (MP4, WEBM).');
    }
    if ((int)($f['size'] ?? 0) > $max) {
        throw new Exception('A banner file is too large. The maximum is ' . ($type === 'video' ? '50 MB for a video' : '5 MB for a picture') . '.');
    }

    $dir = __DIR__ . '/../uploads/program-banners/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new Exception('The server could not create the upload folder for banner slides.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . $name)) {
        throw new Exception('Failed to save a banner file.');
    }
    imageFramingSaveUpload('/uploads/program-banners/' . $name, $dir . $name);
    return ['type' => $type, 'path' => '/uploads/program-banners/' . $name, 'abs' => $dir . $name];
}

/** Delete a banner file we stored (only inside /uploads/program-banners/; anything else is left alone). */
function programPageDeleteBannerFile(?string $webPath): void {
    $webPath = (string)$webPath;
    if (strpos($webPath, '/uploads/program-banners/') !== 0 || strpos($webPath, '..') !== false) return;
    $abs = __DIR__ . '/..' . $webPath;
    if (is_file($abs)) @unlink($abs);
}

/**
 * program_items.item_type may be an ENUM that does not list 'hero_slide' yet. Add the value once
 * (needs ALTER permission); otherwise tell the admin the one line to run. Nothing happens when
 * the column is already a VARCHAR or already lists the value.
 */
function programPageEnsureItemType(string $type): void {
    static $done = [];
    if (isset($done[$type])) return;
    try {
        $db  = getDB();
        $col = $db->query("SHOW COLUMNS FROM program_items LIKE 'item_type'")->fetch(PDO::FETCH_ASSOC);
        $def = (string)($col['Type'] ?? '');
        if (stripos($def, 'enum(') === 0 && stripos($def, "'" . $type . "'") === false) {
            preg_match_all("/'((?:[^']|'')*)'/", $def, $m);
            $vals = array_map(function ($v) { return "'" . $v . "'"; }, $m[1]);
            $vals[] = "'" . $type . "'";
            $sql = 'ALTER TABLE program_items MODIFY item_type ENUM(' . implode(',', $vals) . ')'
                 . ((($col['Null'] ?? 'NO') === 'NO') ? ' NOT NULL' : ' NULL');
            if (($col['Default'] ?? null) !== null) $sql .= ' DEFAULT ' . $db->quote((string)$col['Default']);
            $db->exec($sql);
        }
        $done[$type] = true;
    } catch (Throwable $t) {
        error_log('program_items item_type update error: ' . $t->getMessage());
        throw new Exception('One-time database update needed. In phpMyAdmin run: ALTER TABLE program_items MODIFY item_type VARCHAR(30) NOT NULL;  then try again.');
    }
}

/** PHP's memory_limit in bytes (0 = unlimited). */
function programPageMemoryLimitBytes(): int {
    $v = trim((string)ini_get('memory_limit'));
    if ($v === '' || $v === '-1') return 0;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

/**
 * Shrink a stored picture so it loads fast: the LONG side is scaled down to at
 * most $maxSide px (1920 = "1080p": a 4K 3840x2160 photo becomes 1920x1080;
 * portrait photos are limited the same way), and phone photos are turned the
 * right way up (EXIF) before the metadata is dropped. Never enlarges. Best
 * effort: on any problem the original file is left exactly as uploaded.
 */
function programPageOptimizeImage(int $mediaId, int $maxSide = 1920): void {
    try {
        if (!function_exists('imagecreatetruecolor')) return;
        $stmt = getDB()->prepare("SELECT file_path FROM media_library WHERE media_id = ?");
        $stmt->execute([$mediaId]);
        $web = (string)$stmt->fetchColumn();
        if ($web === '' || strpos($web, '..') !== false || strpos($web, '/uploads/') !== 0) return;
        $file = __DIR__ . '/..' . $web;
        if (!is_file($file)) return;

        $info = @getimagesize($file);
        if (!$info) return;
        [$w, $h, $type] = $info;

        // A big camera photo (e.g. 24 megapixels) needs roughly 5 bytes per pixel to open and resize. Ask for more
        // memory, and if there still isn't enough, keep the original file as uploaded instead of crashing the request.
        $needBytes = (int)($w * $h * 5) + 32 * 1024 * 1024;
        $limit = programPageMemoryLimitBytes();
        if ($limit > 0 && $limit < $needBytes) {
            @ini_set('memory_limit', (string)max((int)ceil($needBytes / 1048576), 256) . 'M');
            $limit = programPageMemoryLimitBytes();
        }
        if ($limit > 0 && $limit - memory_get_usage(true) < $needBytes) {
            error_log('Optimize image skipped (not enough memory for ' . $w . 'x' . $h . '): raise memory_limit in php.ini');
            return;
        }

        $orient = 1;
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file);
            $orient = (int)($exif['Orientation'] ?? 1);
        }
        if (max($w, $h) <= $maxSide && $orient === 1) return;

        if ($type === IMAGETYPE_JPEG)      $img = @imagecreatefromjpeg($file);
        elseif ($type === IMAGETYPE_PNG)   $img = @imagecreatefrompng($file);
        elseif ($type === IMAGETYPE_WEBP)  $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
        else return;
        if (!$img) return;

        $angle = [3 => 180, 6 => -90, 8 => 90][$orient] ?? 0;
        if ($angle !== 0) {
            $rot = imagerotate($img, $angle, 0);
            if ($rot) { imagedestroy($img); $img = $rot; }
        }
        $sw = imagesx($img); $sh = imagesy($img);
        if (max($sw, $sh) > $maxSide) {
            $k  = $maxSide / max($sw, $sh);
            $scaled = imagescale($img, max(1, (int)round($sw * $k)), max(1, (int)round($sh * $k)), IMG_BICUBIC);
            if ($scaled) { imagedestroy($img); $img = $scaled; }
        }
        if ($type !== IMAGETYPE_JPEG) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        $tmp = $file . '.opt';
        if ($type === IMAGETYPE_JPEG)     $ok = imagejpeg($img, $tmp, 82);
        elseif ($type === IMAGETYPE_PNG)  $ok = imagepng($img, $tmp, 6);
        else                              $ok = function_exists('imagewebp') && imagewebp($img, $tmp, 82);
        imagedestroy($img);

        if ($ok && is_file($tmp) && filesize($tmp) > 0) {
            imageUploadOptimizePath($tmp);
            rename($tmp, $file);
            clearstatcache(true, $file);
            $up = getDB()->prepare("UPDATE media_library SET file_size = ? WHERE media_id = ?");
            $up->execute([filesize($file), $mediaId]);
        } elseif (is_file($tmp)) {
            @unlink($tmp);
        }
    } catch (Throwable $e) {
        error_log('Optimize image error: ' . $e->getMessage());
    }
}

/**
 * Replace one repeating list of a program page (badges, core values,
 * why-choose cards, careers or uniform pictures) with the submitted one.
 *
 * $rows: list of ['item_id' => int (0 = new), 'title', 'subtitle', 'body',
 *        'icon', 'letter', 'remove_image' => bool, 'file' => $_FILES entry|null]
 * in the order they should appear. Rows missing from the list are deleted.
 * Returns the refreshed items of that type. Throws Exception.
 */
function saveProgramPageItems(int $programId, string $type, array $rows): array {
    require_once __DIR__ . '/../components/program-page-public.php';
    $limits = programPageItemLimits();
    if (!isset($limits[$type])) throw new Exception('Unknown list.');
    $program = getProgramPageById($programId);
    if (!$program) throw new Exception('Program not found.');
    if (count($rows) > $limits[$type]) {
        throw new Exception('You can add at most ' . $limits[$type] . ' items here.');
    }

    $hasImage = in_array($type, ['career', 'uniform', 'facility', 'hero_slide'], true);   // 'why' cards are text only
    if ($type === 'hero_slide') programPageEnsureItemType('hero_slide');
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM program_items WHERE program_id = ? AND item_type = ?");
    $stmt->execute([$programId, $type]);
    $existing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $existing[(int)$r['item_id']] = $r;

    // ── validate + store pictures first ──
    $clean  = [];
    $stored = [];
    $storedBanner = [];   // banner files written during this save (removed again if the save fails)
    try {
        foreach ($rows as $i => $row) {
            $id    = (int)($row['item_id'] ?? 0);
            $old   = ($id > 0 && isset($existing[$id])) ? $existing[$id] : null;
            if ($id > 0 && !$old) $id = 0;

            $title = trim(preg_replace('/\s+/', ' ', (string)($row['title'] ?? '')));
            $sub   = trim(preg_replace('/\s+/', ' ', (string)($row['subtitle'] ?? '')));
            $body  = richTextNormalize((string)($row['body'] ?? ''));
            $icon  = programPageCleanIcon($row['icon'] ?? '', '');
            $letter = mb_substr(trim((string)($row['letter'] ?? '')), 0, 2);

            if (mb_strlen($title) > 255 || mb_strlen($sub) > 255 || mb_strlen($body) > 2000) {
                throw new Exception('Item ' . ($i + 1) . ' has text that is too long.');
            }
            if ($type !== 'uniform' && $type !== 'hero_slide' && $title === '' && !contentPublishAllowsIncomplete()) {
                throw new Exception(($type === 'facility' ? 'Facility ' : 'Item ') . ($i + 1) . ' needs a name.');
            }
            if ($type === 'facility' && mb_strlen($body) > 500) {
                throw new Exception('Facility ' . ($i + 1) . ': the description is too long (maximum 500 characters).');
            }
            if ($type === 'core_value' && $letter === '') $letter = mb_strtoupper(mb_substr($title, 0, 1));
            if (in_array($type, ['badge', 'core_value'], true) && $icon === '') {
                $icon = $type === 'badge' ? 'material:school' : 'material:star';
            }

            $mediaId = $old && $old['image_media_id'] !== null ? (int)$old['image_media_id'] : null;
            $path    = $old['image_path'] ?? null;
            $upload  = null;
            if ($type === 'hero_slide') {
                // Banner slide: a picture or a video, kept as a file path (no media-library row).
                $f = $row['file'] ?? null;
                if (!empty($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $banner = programPageStoreBannerMedia($f);
                    $storedBanner[] = $banner['abs'];
                    $mediaId = null;
                    $path    = $banner['path'];
                } elseif (!empty($row['remove_image'])) {
                    $mediaId = null;
                    $path    = null;
                }
                if ($mediaId === null && $path === null) {
                    throw new Exception('Banner slide ' . ($i + 1) . ' needs a picture or a video.');
                }
            } elseif ($hasImage) {
                $f = $row['file'] ?? null;
                if (!empty($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if ($type === 'facility') programPageCheckFacilityImage($f);
                    $upload = storeDepartmentUpload($f, $type === 'facility' ? 'facility_image' : 'program_image');
                    $stored[] = $upload;
                    $mediaId = $upload['media_id'];
                    $path    = null;
                    // Preserve original upload bytes; framing is stored separately.
                } elseif (!empty($row['remove_image'])) {
                    $mediaId = null;
                    $path    = null;
                }
                if (in_array($type, ['uniform', 'facility'], true) && $mediaId === null && $path === null) {
                    throw new Exception(($type === 'facility' ? 'Facility ' : 'Uniform ') . ($i + 1) . ' needs a picture.');
                }
            }

            $clean[] = [
                'id' => $id, 'title' => $title, 'subtitle' => $sub !== '' ? $sub : null,
                'body' => $body !== '' ? $body : null, 'icon' => $icon !== '' ? $icon : null,
                'letter' => $letter !== '' ? $letter : null, 'media' => $mediaId, 'path' => $path,
            ];
        }
    } catch (Exception $e) {
        foreach ($stored as $s) discardStoredDepartmentUpload($s);
        foreach ($storedBanner as $abs) { if (is_file($abs)) @unlink($abs); }
        throw $e;
    }

    // ── write in one transaction ──
    $keep = [];
    try {
        $db->beginTransaction();
        foreach ($clean as $order => $it) {
            if ($it['id'] > 0) {
                $stmt = $db->prepare("
                    UPDATE program_items SET title = ?, subtitle = ?, body = ?, icon = ?, letter = ?,
                           image_media_id = ?, image_path = ?, display_order = ?
                    WHERE item_id = ? AND program_id = ?
                ");
                $stmt->execute([$it['title'], $it['subtitle'], $it['body'], $it['icon'], $it['letter'],
                    $it['media'], $it['path'], $order + 1, $it['id'], $programId]);
                $keep[] = $it['id'];
            } else {
                $stmt = $db->prepare("
                    INSERT INTO program_items (program_id, item_type, title, subtitle, body, icon, letter, image_media_id, image_path, display_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$programId, $type, $it['title'], $it['subtitle'], $it['body'], $it['icon'], $it['letter'],
                    $it['media'], $it['path'], $order + 1]);
                $keep[] = (int)$db->lastInsertId();
            }
        }
        foreach ($existing as $id => $old) {
            if (!in_array($id, $keep, true)) {
                $stmt = $db->prepare("DELETE FROM program_items WHERE item_id = ? AND program_id = ?");
                $stmt->execute([$id, $programId]);
            }
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        foreach ($stored as $s) discardStoredDepartmentUpload($s);
        foreach ($storedBanner as $abs) { if (is_file($abs)) @unlink($abs); }
        error_log('Save program items error: ' . $e->getMessage());
        throw new Exception('Could not save. Please try again.');
    }

    // Pictures that were replaced, removed, or belonged to deleted rows.
    $stillUsed = array_filter(array_column($clean, 'media'));
    foreach ($existing as $id => $old) {
        $oldMedia = $old['image_media_id'] !== null ? (int)$old['image_media_id'] : null;
        if ($oldMedia !== null && !in_array($oldMedia, $stillUsed, true)) {
            deleteMediaIfUnreferenced($oldMedia);
        }
    }

    // Banner files that were replaced, or belonged to slides that were deleted.
    if ($type === 'hero_slide') {
        $stillUsedPaths = array_filter(array_column($clean, 'path'));
        foreach ($existing as $old) {
            $oldPath = (string)($old['image_path'] ?? '');
            if ($oldPath !== '' && !in_array($oldPath, $stillUsedPaths, true)) {
                programPageDeleteBannerFile($oldPath);
            }
        }
    }

    logActivity((int)($_SESSION['user_id'] ?? 0), 'UPDATE', 'programs', $programId,
        $program['program_name'] . ': updated ' . str_replace('_', ' ', $type) . ' list');

    return programPageItems($programId)[$type];
}

/**
 * Permanently delete a program together with its page content and pictures.
 * (deleteProgramFull() removes the row + card image + PDF; the rest is here.)
 */
function deleteProgramPage(int $programId): void {
    require_once __DIR__ . '/../components/program-page-public.php';
    $program = getProgramPageById($programId);
    if (!$program) {
        throw new Exception('That program was already removed. Please refresh the page.');
    }

    $media = [];
    foreach (['hero_media_id', 'seal_media_id'] as $col) {
        if (!empty($program[$col])) $media[] = (int)$program[$col];
    }
    try {
        $stmt = getDB()->prepare("SELECT image_media_id FROM program_items WHERE program_id = ? AND image_media_id IS NOT NULL");
        $stmt->execute([$programId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $m) $media[] = (int)$m;
    } catch (Exception $e) {
        error_log('Delete program page: item lookup error: ' . $e->getMessage());
    }
    $bannerFiles = [];   // banner-slide pictures/videos live as plain files
    try {
        $stmt = getDB()->prepare("SELECT image_path FROM program_items WHERE program_id = ? AND item_type = 'hero_slide' AND image_path IS NOT NULL");
        $stmt->execute([$programId]);
        $bannerFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log('Delete program page: banner lookup error: ' . $e->getMessage());
    }

    deleteProgramFull($programId);          // row (items cascade), card image, PDF
    foreach ($bannerFiles as $bf) programPageDeleteBannerFile((string)$bf);

    foreach (array_unique($media) as $m) {
        deleteMediaIfUnreferenced($m);
    }
}

// ============================================================
// VISUAL EDITOR PREVIEW MODE
// ============================================================
// The Content Editor (admin/content-edit.php) shows the REAL public pages
// in a frame and edits them in place. To do that, a public page is opened
// with ?ce_preview=1. This only works for a signed-in person who may edit
// page content; anyone else gets the normal public page, unchanged.
//
// In preview mode a public page:
//   - shows unpublished DRAFT text (so what you saved as a draft is what you see)
//   - prints small data-ve-* markers that tell the editor what is editable
//   - is never cached or indexed
// Visitors never get the markers or the drafts.

/** True when this request is the Content Editor's live preview. */
function cePreviewActive(): bool {
    static $active = null;
    if ($active !== null) return $active;
    $active = false;
    if (($_GET['ce_preview'] ?? '') !== '1') return false;   // normal visitors: no session, no cost
    initSession();
    if (isLoggedIn() && (hasPermission('manage_page_content') || hasPermission('edit_page_content'))) {
        $active = true;
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Robots-Tag: noindex, nofollow');
        }
    }
    return $active;
}

/**
 * Editor markers for a public template, e.g.
 *   <div class="vm-text"<?= ceMark('data-ve-section="vision"') ?>>
 * Editor controls are preview-only; public text keeps an opaque display identity.
 */
function ceMark(string $attributes): string {
    return ceTextLayoutMark($attributes) . (cePreviewActive() ? ' ' . $attributes : '');
}

/** Stable public display identity; never exposes draft text or editor controls. */
function ceTextLayoutMark(string $attributes): string {
    foreach (['slide','person','office','sport','team','facility','program-card','achievement-id','org-card','department-card'] as $kind) {
        if (preg_match('/data-ve-'. $kind .'="([^"]+)"/', $attributes, $scope)) return ' data-gnc-layout-scope="'.substr(hash('sha256',$kind.':'.$scope[1]),0,24).'"';
    }
    if (!preg_match('/data-ve-(?:field|section)="([^"]+)"/', $attributes, $field)) return '';
    $identity = $field[0];
    foreach (['key','id'] as $key) if (preg_match('/data-ve-'.$key.'="([^"]*)"/', $attributes, $match)) $identity .= '|'.$match[0];
    return ' data-gnc-text="'.substr(hash('sha256',$identity),0,24).'"';
}

/** HTML-escape an attribute value for ceMark(). */
function ceAttr($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * True only inside the Content Editor preview AND only for someone who may MANAGE page content.
 * Departments, programs and the homepage posts are saved through handlers that need that permission,
 * so only those people get their editing markers and data blocks (text editors never see them).
 */
function cePreviewCanManage(): bool {
    static $can = null;
    if ($can === null) $can = cePreviewActive() && hasPermission('manage_page_content');
    return $can;
}

/** Like ceMark(), but for the pages that only managers can edit (departments, programs, posts). */
function ceManageMark(string $attributes): string {
    return ceTextLayoutMark($attributes) . (cePreviewCanManage() ? ' ' . $attributes : '');
}

/**
 * Marker that turns an existing element into an editable text field in the Content Editor, e.g.
 *   <h1<?= ceField('dept-name', ['max' => 255, 'label' => 'Department name']) ?>>...</h1>
 * Options: key (which stored value), max (characters), label, ph (placeholder), multiline.
 * Visitors receive only the opaque text display identity.
 */
function ceField(string $name, array $o = []): string {
    $a = 'data-ve-field="' . ceAttr($name) . '"';
    if (!empty($o['key']))       $a .= ' data-ve-key="'   . ceAttr($o['key'])   . '"';
    if (!empty($o['max']))       $a .= ' data-ve-max="'   . (int)$o['max']      . '"';
    if (!empty($o['label']))     $a .= ' data-ve-label="' . ceAttr($o['label']) . '"';
    if (!empty($o['ph']))        $a .= ' data-ve-ph="'    . ceAttr($o['ph'])    . '"';
    if (!empty($o['multiline'])) $a .= ' data-ve-multiline="1"';
    if (!empty($o['rich'])) $a .= ' data-ve-rich="1"';
    if (!empty($o['optional'])) $a .= ' data-warning-optional="true"';
    if (!empty($o['warning_required'])) $a .= ' data-warning-required="true"';
    if (!empty($o['section'])) $a .= ' data-warning-section="' . ceAttr($o['section']) . '"';
    return ceManageMark($a);
}

/**
 * A block of JSON the editor reads from the page (the current values it needs to save a whole record safely).
 * Printed only for managers in the preview. Every character that could end the <script> is escaped.
 */
function ceData(string $id, array $data): string {
    if (!cePreviewCanManage()) return '';
    return '<script type="application/json" id="' . ceAttr($id) . '">'
        . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR)
        . '</script>';
}


// ============================================================
// HOMEPAGE — editable section headings, heading icons, intro lines and the three Academic Programs cards
// ============================================================
// index.php used to have these words typed straight into the page. Each one now has a
// "key" below with its original wording as the default, so:
//   - nothing changes on the website until someone edits it in the Content Editor, and
//   - an edit is stored in `homepage_text` (created automatically the first time you save).
// Pictures are stored as a media_library id, like every other upload.
//
// Optional: the table can also be created ahead of time with
//   CREATE TABLE homepage_text (
//       text_key   VARCHAR(60) NOT NULL PRIMARY KEY,
//       value      TEXT NULL,
//       media_id   INT NULL,
//       updated_by INT NULL,
//       updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
//   ) DEFAULT CHARSET = utf8mb4;

const HOME_LOREM = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

/** Every editable homepage block: type (text|link|icon|image), original wording, max length, label shown in the editor. */
function homeContentSchema(): array {
    $s = [
        'news_background'   => ['image', 'assets/images/DSC_2965.JPG', 0, 'News & Announcements background'],
        'programs_background' => ['image', 'assets/images/classroom.JPG', 0, 'Academic Programs background'],
        'news_icon'         => ['icon', 'bi-megaphone-fill', 60, 'News heading icon'],
        'news_heading'      => ['text', 'News & Announcements', 60, 'News heading'],
        'news_subheading'   => ['text', "Stay Updated,\nStay Informed", 60, 'News heading, second part'],
        'news_intro'        => ['text', 'Get the latest news, events, and important updates.', 200, 'News introduction'],
        'calendar_icon'     => ['icon', 'bi-calendar-event-fill', 60, 'Calendar small heading icon'],
        'calendar_kicker'   => ['text', 'Calendar of Events', 60, 'Calendar small heading'],
        'calendar_heading'  => ['text', 'Mark Your', 40, 'Calendar heading'],
        'calendar_accent'   => ['text', 'Calendar', 40, 'Calendar heading, highlighted word'],
        'calendar_intro'    => ['text', 'Keep track of school activities, holidays, and important dates.', 200, 'Calendar introduction'],
        'events_label'      => ['text', 'Upcoming Events', 60, 'Events list heading'],
        'programs_icon'     => ['icon', 'bi-mortarboard-fill', 60, 'Programs heading icon'],
        'programs_heading'  => ['text', 'Academic Programs', 60, 'Programs heading'],
        'programs_subheading' => ['text', "Shaping Minds,\nBuilding Futures", 60, 'Programs heading, second part'],
        'programs_intro'    => ['text', 'From basic education to graduate school, GNC offers a path for every learner.', 200, 'Programs introduction'],
        'president_label'   => ['text', 'Message from the President', 60, 'President section label'],
        'campus_heading'    => ['text', 'Find Your Way Around', 80, 'Campus map heading'],
        'campus_intro'      => ['text', 'Tap a building to see the route from the main gate', 200, 'Campus map introduction'],
    ];
    $cards = [
        1 => ['Basic Education',  '/pages/academics/basic-edu/basic-edu-dept.php',           'assets/images/basic-edu.png'],
        2 => ['College Programs', '/pages/academics/college/college-dept.php',               'assets/images/college-programs.png'],
        3 => ['Graduate School',  '/pages/academics/graduate-school/graduate-school.php',    'assets/images/graduate-school.png'],
    ];
    foreach ($cards as $n => [$title, $link, $img]) {
        $s["programs_{$n}_title"]  = ['text',  $title,        80,  "Program {$n} title"];
        $s["programs_{$n}_text"]   = ['text',  HOME_LOREM,    400, "Program {$n} description"];
        $s["programs_{$n}_button"] = ['text',  'Learn More',  30,  "Program {$n} button"];
        $s["programs_{$n}_link"]   = ['link',  $link,         255, "Program {$n} button link"];
        $s["programs_{$n}_image"]  = ['image', $img,          0,   "Program {$n} picture"];
    }
    $out = [];
    foreach ($s as $key => [$type, $default, $max, $label]) {
        $out[$key] = ['type' => $type, 'default' => $default, 'max' => $max, 'label' => $label];
    }
    return $out;
}

/** Stored overrides only: key => ['value' => ?string, 'media_id' => ?int, 'path' => ?string]. Empty if the table doesn't exist yet. */
function homeContentStored(): array {
    try {
        $rows = getDB()->query("
            SELECT t.text_key, t.value, t.media_id, m.file_path
            FROM homepage_text t LEFT JOIN media_library m ON m.media_id = t.media_id
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];     // table not created yet: the website simply shows the original wording
    }
    $out = [];
    foreach ($rows as $r) {
        $out[$r['text_key']] = ['value' => $r['value'], 'media_id' => $r['media_id'] !== null ? (int)$r['media_id'] : null, 'path' => $r['file_path']];
    }
    return $out;
}

/**
 * Everything on the homepage that can be edited, ready to print: key => ['type','value','default','max','label'].
 * For a picture, 'value' is the web path to show (an upload, or the original image).
 */
function getHomeContent(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $stored = homeContentStored();
    $cache = [];
    foreach (homeContentSchema() as $key => $def) {
        $value = $def['default'];
        if (isset($stored[$key])) {
            if ($def['type'] === 'image') {
                $p = $stored[$key]['path'] ?? null;
                if ($p && departmentFileExists($p)) $value = ltrim($p, '/');
            } elseif ($stored[$key]['value'] !== null && trim((string)$stored[$key]['value']) !== '') {
                $candidate = trim((string)$stored[$key]['value']);
                // an icon is only ever a Bootstrap Icons class name; anything else falls back to the original
                if ($def['type'] !== 'icon' || homeIconIsValid($candidate)) $value = $candidate;
            }
        }
        $cache[$key] = $def + ['value' => $value];
    }
    return $cache;
}

/** The current text (or picture path) for one homepage block. */
function homeText(string $key): string {
    $all = getHomeContent();
    return isset($all[$key]) ? (string)$all[$key]['value'] : '';
}

/**
 * Print a homepage text block, escaped. Inside the Content Editor's preview it is wrapped in a marker so it can be
 * edited in place; for visitors the output is exactly the plain text.
 */
function homeTextHtml(string $key): string {
    $all = getHomeContent();
    if (!isset($all[$key])) return '';
    $text = htmlspecialchars((string)$all[$key]['value'], ENT_QUOTES, 'UTF-8');
    if (!cePreviewActive()) return '<ve-text'.ceTextLayoutMark('data-ve-field="home-text" data-ve-key="'.ceAttr($key).'"').'>'.$text.'</ve-text>';
    return '<ve-text'.ceTextLayoutMark('data-ve-field="home-text" data-ve-key="'.ceAttr($key).'"').' data-ve-field="home-text" data-ve-key="' . ceAttr($key) . '" data-ve-max="' . (int)$all[$key]['max']
         . '" data-ve-label="' . ceAttr($all[$key]['label']) . '">' . $text . '</ve-text>';
}

/** Homepage text blocks where one line break (Enter) is allowed, e.g. the gold second line of a heading. */
function homeMultilineKeys(): array {
    return ['news_subheading', 'programs_subheading'];
}
function homeRichKeys(): array {
    return ['news_intro','calendar_intro','programs_intro','campus_intro','programs_1_text','programs_2_text','programs_3_text'];
}

/** A multi-line block, escaped, with its line break printed as <br> (for visitors and for the editor preview). */
function homeTextBr(string $key): string {
    return preg_replace('/\r\n|\r|\n/', '<br>', htmlspecialchars(homeText($key), ENT_QUOTES, 'UTF-8'));
}

/** Is this a safe Bootstrap Icons class name, e.g. "bi-megaphone-fill"? */
function homeIconIsValid(string $v): bool {
    return (bool)preg_match('/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/', $v) && strlen($v) <= 60;
}

/** The icon class to print for a heading icon, e.g. "bi-megaphone-fill" (always safe to print inside class="..."). */
function homeIconClass(string $key): string {
    $v = homeText($key);
    if (homeIconIsValid($v)) return $v;
    $all = getHomeContent();
    return isset($all[$key]) ? (string)$all[$key]['default'] : 'bi-circle';
}

/** Attributes that make a heading icon clickable in the editor (to pick another icon). Empty for visitors. */
function homeIconMark(string $key): string {
    $all = getHomeContent();
    if (!isset($all[$key])) return '';
    return ceMark('data-ve-icon="home-icon" data-ve-key="' . ceAttr($key) . '" data-ve-label="' . ceAttr($all[$key]['label']) . '"');
}

/** Attributes that make an existing element (heading, paragraph, button) editable in place. Empty for visitors. */
function homeTextMark(string $key, bool $multiline = false): string {
    $all = getHomeContent();
    if (!isset($all[$key])) return '';
    return ceMark('data-ve-field="home-text" data-ve-key="' . ceAttr($key) . '" data-ve-max="' . (int)$all[$key]['max']
        . '" data-ve-label="' . ceAttr($all[$key]['label']) . '"' . ($multiline||in_array($key,homeRichKeys(),true) ? ' data-ve-multiline="1"' : '') . (in_array($key,homeRichKeys(),true)?' data-ve-rich="1"':''));
}

/** The editor's copy of every block (JSON-safe). */
function homeContentAdminPayload(): array {
    $out = [];
    foreach (getHomeContent() as $key => $b) {
        $out[$key] = ['type' => $b['type'], 'value' => $b['value'], 'max' => $b['max'], 'label' => $b['label'], 'default' => $b['default']];
    }
    return $out;
}

function homeContentEnsureTable(): void {
    // CREATE TABLE, even IF NOT EXISTS, implicitly commits an open MySQL transaction.
    // Existing installations only need a read here; never disturb their caller's transaction.
    try { getDB()->query('SELECT 1 FROM homepage_text LIMIT 1'); return; }
    catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;}
    if(getDB()->inTransaction())throw new RuntimeException('Create the homepage text table before starting a content transaction.');
    getDB()->exec("
        CREATE TABLE IF NOT EXISTS homepage_text (
            text_key   VARCHAR(60) NOT NULL PRIMARY KEY,
            value      TEXT NULL,
            media_id   INT NULL,
            updated_by INT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) DEFAULT CHARSET = utf8mb4
    ");
}

/**
 * Save edits to homepage blocks in one step.
 *   $texts  key => new text/link (only the keys that changed)
 *   $images key => uploaded file array, or the string 'reset' to go back to the original picture
 * Every key is checked against homeContentSchema(); unknown keys are refused. A blank text puts the original wording back.
 * Returns the refreshed editor payload. Throws Exception with a user-friendly message.
 */
function saveHomeContent(array $texts, array $images): array {
    $schema = homeContentSchema();
    $clean = [];
    foreach ($texts as $key => $val) {
        if (!isset($schema[$key]) || $schema[$key]['type'] === 'image') throw new Exception('That part of the homepage cannot be edited.');
        if(in_array($key,homeRichKeys(),true)){
            $val=richTextNormalize((string)$val);
        } elseif (in_array($key, homeMultilineKeys(), true)) {
            // keep the line break the editor typed (one at most per gap), tidy the spaces around it
            $val = trim(preg_replace('/[ \t]*\n[ \t]*/', "\n", preg_replace('/\n{2,}/', "\n", preg_replace('/[ \t]+/', ' ', str_replace("\r", '', (string)$val)))));
        } else {
            $val = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', (string)$val)));
        }
        $max = $schema[$key]['max'];
        if ($max && mb_strlen($val) > $max) throw new Exception("“{$schema[$key]['label']}” is too long. Please keep it under {$max} characters.");
        if ($schema[$key]['type'] === 'link' && $val !== '') {
            if (preg_match('/^\s*(javascript|data|vbscript):/i', $val)) throw new Exception('That kind of link is not allowed.');
        }
        if ($schema[$key]['type'] === 'icon' && $val !== '' && !homeIconIsValid($val)) {
            throw new Exception('That icon name is not valid. Use a Bootstrap Icons name such as bi-megaphone-fill.');
        }
        $clean[$key] = $val;
    }
    foreach ($images as $key => $file) {
        if (!isset($schema[$key]) || $schema[$key]['type'] !== 'image') throw new Exception('That picture cannot be changed.');
    }

    try { homeContentEnsureTable(); }
    catch (Throwable $e) {
        error_log('homepage_text create error: ' . $e->getMessage());
        throw new Exception('The homepage text table does not exist yet and could not be created automatically. Ask your developer to run the CREATE TABLE homepage_text statement shown in admin-functions.php.');
    }

    $db = getDB();
    $stored = homeContentStored();
    $userId = $_SESSION['user_id'] ?? null;
    $newMedia = []; $oldMedia = [];
    try {
        foreach ($images as $key => $file) {
            $old = $stored[$key]['media_id'] ?? null;
            if ($file === 'reset') {
                $db->prepare("DELETE FROM homepage_text WHERE text_key = ?")->execute([$key]);
            } else {
                $up = storeDepartmentUpload($file, 'homepage_image');
                $newMedia[] = $up;
                $db->prepare("INSERT INTO homepage_text (text_key, value, media_id, updated_by) VALUES (?, NULL, ?, ?)
                              ON DUPLICATE KEY UPDATE media_id = VALUES(media_id), updated_by = VALUES(updated_by)")
                   ->execute([$key, $up['media_id'], $userId]);
            }
            if ($old) $oldMedia[] = (int)$old;
        }
        foreach ($clean as $key => $val) {
            if ($val === '') {
                $db->prepare("DELETE FROM homepage_text WHERE text_key = ?")->execute([$key]);      // back to the original wording
            } else {
                $db->prepare("INSERT INTO homepage_text (text_key, value, updated_by) VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)")
                   ->execute([$key, $val, $userId]);
            }
        }
    } catch (Exception $e) {
        foreach ($newMedia as $up) discardStoredDepartmentUpload($up);
        if ($e instanceof PDOException) { error_log('Save homepage content error: ' . $e->getMessage()); throw new Exception('Could not save the homepage changes. Please try again.'); }
        throw $e;
    }
    foreach (array_unique($oldMedia) as $m) deleteMediaIfUnreferenced($m);

    logActivity((int)$userId, 'UPDATE', 'homepage_text', null, 'Edited homepage text: ' . implode(', ', array_merge(array_keys($clean), array_keys($images))));
    return homeContentAdminPayload();
}
