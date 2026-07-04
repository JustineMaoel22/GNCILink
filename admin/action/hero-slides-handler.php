<?php
/**
 * GNC Admin Panel - AJAX handler for the homepage Slideshow (Section Editor)
 * Path on server: /admin/action/hero-slides-handler.php
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

initSession();
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// All state-changing actions require a valid CSRF token
$stateChanging = ['save', 'delete', 'reorder'];
if (in_array($action, $stateChanging, true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or expired session token. Please refresh the page and try again.']);
        exit;
    }
}

try {
    switch ($action) {

        // ── LIST all slides (used to rebuild the left-hand slide order list) ──
        case 'list': {
            $slides = getHeroSlides(false);
            echo json_encode(['success' => true, 'slides' => $slides]);
            break;
        }

        // ── GET a single slide's full details ──
        case 'get': {
            $id = (int)($_GET['id'] ?? 0);
            $slide = getHeroSlideById($id);
            if (!$slide) {
                http_response_code(404);
                echo json_encode(['error' => 'Slide not found.']);
                break;
            }
            echo json_encode(['success' => true, 'slide' => $slide]);
            break;
        }

        // ── SAVE (create when no id, update when id is present) ──
        case 'save': {
            $id = (int)($_POST['slide_id'] ?? 0);

            $data = [
                'title'          => trim($_POST['title'] ?? ''),
                'subtitle'       => trim($_POST['subtitle'] ?? ''),
                'btn1_text'      => trim($_POST['btn1_text'] ?? ''),
                'btn1_link'      => trim($_POST['btn1_link'] ?? ''),
                'btn2_text'      => trim($_POST['btn2_text'] ?? ''),
                'btn2_link'      => trim($_POST['btn2_link'] ?? ''),
                'status'         => in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'published',
                'show_on_mobile' => isset($_POST['show_on_mobile']) && $_POST['show_on_mobile'] === '1',
            ];

            // Basic length guards to match the reference UI's character limits
            if (mb_strlen($data['title']) > 100)    $data['title']    = mb_substr($data['title'], 0, 100);
            if (mb_strlen($data['subtitle']) > 150) $data['subtitle'] = mb_substr($data['subtitle'], 0, 150);
            if (mb_strlen($data['btn1_text']) > 30) $data['btn1_text'] = mb_substr($data['btn1_text'], 0, 30);
            if (mb_strlen($data['btn2_text']) > 30) $data['btn2_text'] = mb_substr($data['btn2_text'], 0, 30);

            $media = isset($_FILES['media']) ? uploadHeroMedia($_FILES['media']) : null;

            if ($id > 0) {
                // Updating an existing slide
                if ($media === null && empty(getHeroSlideById($id)['media_path'] ?? null)) {
                    echo json_encode(['error' => 'A background image or video is required.']);
                    break;
                }
                $ok = updateHeroSlide($id, $data, $media);
                if (!$ok) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Could not update the slide.']);
                    break;
                }
                echo json_encode(['success' => true, 'slide_id' => $id]);
            } else {
                // Creating a new slide — media is required
                if ($media === null) {
                    echo json_encode(['error' => 'Please upload a background image or video for the new slide.']);
                    break;
                }
                $newId = createHeroSlide($data, $media);
                if ($newId === false) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Could not create the slide.']);
                    break;
                }
                echo json_encode(['success' => true, 'slide_id' => $newId]);
            }
            break;
        }

        // ── DELETE a slide ──
        case 'delete': {
            $id = (int)($_POST['slide_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['error' => 'Missing slide id.']);
                break;
            }
            $ok = deleteHeroSlide($id);
            if (!$ok) {
                http_response_code(400);
                echo json_encode(['error' => 'Could not delete the slide.']);
                break;
            }
            echo json_encode(['success' => true]);
            break;
        }

        // ── REORDER slides (drag & drop) ──
        case 'reorder': {
            $orderJson = $_POST['order'] ?? '[]';
            $orderedIds = json_decode($orderJson, true);
            if (!is_array($orderedIds) || empty($orderedIds)) {
                echo json_encode(['error' => 'Invalid order data.']);
                break;
            }
            $ok = reorderHeroSlides($orderedIds);
            if (!$ok) {
                http_response_code(400);
                echo json_encode(['error' => 'Could not save the new order.']);
                break;
            }
            echo json_encode(['success' => true]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action.']);
    }
} catch (Exception $e) {
    error_log('Hero slides handler error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}