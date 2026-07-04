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
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                destroySession();
                header('Location: admin/login.php?timeout=1');
                exit;
            }
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
            'view_logs', 'manage_approvals'
        ],
        'Content Admin' => [
            'create_announcement', 'edit_announcement', 'delete_announcement', 'publish_announcement',
            'create_news', 'edit_news', 'delete_news', 'publish_news',
            'create_event', 'edit_event', 'delete_event', 'publish_event',
            'approve_content', 'reject_content', 'request_revision',
            'manage_approvals'
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
        
        $stmt = $db->prepare("
            INSERT INTO announcements 
            (user_id, title, slug, content, category_id, featured_image, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $data['title'],
            $slug,
            $data['content'],
            $data['category_id'] ?? null,
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
        
        $stmt = $db->prepare("
            UPDATE announcements
            SET title = ?, slug = ?, content = ?, category_id = ?, featured_image = ?, status = ?, updated_at = NOW()
            WHERE announcement_id = ?
        ");
        
        $stmt->execute([
            $data['title'],
            $slug,
            $data['content'],
            $data['category_id'] ?? null,
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
            SELECT a.*, u.first_name, u.last_name, c.category_name,
                m.file_path as image_path, m.media_id
            FROM announcements a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN categories c ON a.category_id = c.category_id
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
            SELECT a.*, u.first_name, u.last_name, c.category_name,
                   m.file_path as image_path, m.media_id
            FROM announcements a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN categories c ON a.category_id = c.category_id
            LEFT JOIN media_library m ON a.featured_image = m.media_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $query .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search'])) {
            $query .= " AND (a.title LIKE ? OR a.content LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
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
function uploadHeroMedia(?array $file): ?array {
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
    } elseif (isset($allowedVideos[$mime])) {
        $type = 'video';
        $ext  = $allowedVideos[$mime];
        $maxBytes = 50 * 1024 * 1024; // 50MB
    } else {
        throw new Exception('Unsupported file type. Allowed: JPG, PNG, GIF, WEBP, MP4, WEBM, MOV.');
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
 * $data keys: title, subtitle, btn1_text, btn1_link, btn2_text, btn2_link, status, show_on_mobile
 * $media: ['type' => 'image'|'video', 'path' => '...'] from uploadHeroMedia()
 */
function createHeroSlide(array $data, array $media): int|false {
    try {
        $db = getDB();

        $stmt = $db->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM hero_slides");
        $nextOrder = (int)$stmt->fetch()['next_order'];

        $stmt = $db->prepare("
            INSERT INTO hero_slides
                (media_type, media_path, title, subtitle, btn1_text, btn1_link, btn2_text, btn2_link,
                 display_order, status, show_on_mobile, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $media['type'],
            $media['path'],
            $data['title'] !== '' ? $data['title'] : null,
            $data['subtitle'] !== '' ? $data['subtitle'] : null,
            $data['btn1_text'] !== '' ? $data['btn1_text'] : null,
            $data['btn1_link'] !== '' ? $data['btn1_link'] : null,
            $data['btn2_text'] !== '' ? $data['btn2_text'] : null,
            $data['btn2_link'] !== '' ? $data['btn2_link'] : null,
            $nextOrder,
            $data['status'] ?? 'published',
            !empty($data['show_on_mobile']) ? 1 : 0,
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
 * Update an existing hero slide. $media is null if no new file was uploaded
 * (keeps the existing media_path/media_type).
 */
function updateHeroSlide(int $id, array $data, ?array $media = null): bool {
    try {
        $db = getDB();
        $existing = getHeroSlideById($id);
        if (!$existing) return false;

        $mediaType = $media['type'] ?? $existing['media_type'];
        $mediaPath = $media['path'] ?? $existing['media_path'];

        $stmt = $db->prepare("
            UPDATE hero_slides SET
                media_type = ?, media_path = ?, title = ?, subtitle = ?,
                btn1_text = ?, btn1_link = ?, btn2_text = ?, btn2_link = ?,
                status = ?, show_on_mobile = ?
            WHERE slide_id = ?
        ");
        $stmt->execute([
            $mediaType,
            $mediaPath,
            ($data['title'] ?? '') !== '' ? $data['title'] : null,
            ($data['subtitle'] ?? '') !== '' ? $data['subtitle'] : null,
            ($data['btn1_text'] ?? '') !== '' ? $data['btn1_text'] : null,
            ($data['btn1_link'] ?? '') !== '' ? $data['btn1_link'] : null,
            ($data['btn2_text'] ?? '') !== '' ? $data['btn2_text'] : null,
            ($data['btn2_link'] ?? '') !== '' ? $data['btn2_link'] : null,
            $data['status'] ?? 'published',
            !empty($data['show_on_mobile']) ? 1 : 0,
            $id,
        ]);

        // Remove the old file if it was replaced
        if ($media !== null && $existing['media_path'] && $existing['media_path'] !== $mediaPath) {
            $oldFile = __DIR__ . '/..' . $existing['media_path'];
            if (is_file($oldFile)) @unlink($oldFile);
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