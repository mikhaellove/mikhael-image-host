<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuthMiddleware;
use App\Core\RateLimiter;
use App\Models\Image;
use App\Models\Setting;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\Uploader;

class AdminController
{
    public function showLogin(): void
    {
        if (Auth::isAuthenticated()) {
            header('Location: /admin');
            exit;
        }

        include __DIR__ . '/../../templates/admin/login.php';
    }

    public function handleLogin(): void
    {
        AuthMiddleware::requireCsrf();

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (Auth::login($username, $password)) {
            header('Location: /admin');
        } else {
            $cooldown = Auth::getLoginCooldownRemaining();
            $error = $cooldown > 0
                ? "Too many failed attempts. Try again in {$cooldown} seconds."
                : "Invalid credentials";

            include __DIR__ . '/../../templates/admin/login.php';
        }
    }

    public function handleLogout(): void
    {
        Auth::logout();
        header('Location: /');
        exit;
    }

    public function showDashboard(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::checkPasswordReset();

        $userId = Auth::getUserId();
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        $images = Image::getGalleryForUser($userId, $page);
        $totalImages = Image::getGalleryCountForUser($userId);
        $totalPages = ceil($totalImages / 50);

        $storageStats = User::getUserStorageStats($userId);
        $maxGalleryImages = (int) Setting::get('max_gallery_images', 5);

        include __DIR__ . '/../../templates/admin/dashboard.php';
    }

    public function handleUpload(): void
    {
        // Set content type first to ensure valid JSON response even on errors
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::checkPasswordReset();
            AuthMiddleware::requireCsrf();

            $userId = Auth::getUserId();
            $rawCaption = $_POST['caption'] ?? null;
            $caption = $rawCaption !== null && $rawCaption !== '' ? HtmlSanitizer::sanitizeCaption($rawCaption) : null;

            $result = Uploader::handleUpload($userId, $_FILES['image'], $caption);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            http_response_code(400);
            error_log("Upload error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // Catch fatal errors too
            http_response_code(500);
            error_log("Upload fatal error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
        }
    }

    public function handleDelete(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::requireCsrf();

            $imageId = (int)($_POST['image_id'] ?? 0);
            $userId = Auth::getUserId();

            // Verify ownership
            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            Image::softDelete($imageId);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Delete error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdateCaption(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireCsrf();

        $imageId = (int)($_POST['image_id'] ?? 0);
        $rawCaption = $_POST['caption'] ?? null;
        $caption = $rawCaption !== null && $rawCaption !== '' ? HtmlSanitizer::sanitizeCaption($rawCaption) : null;
        $userId = Auth::getUserId();

        // Verify ownership
        if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        Image::updateCaption($imageId, $caption);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function showResetPassword(): void
    {
        AuthMiddleware::requireAuth();

        include __DIR__ . '/../../templates/admin/reset-password.php';
    }

    public function handleResetPassword(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireCsrf();

        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || $newPassword !== $confirmPassword) {
            $error = "Passwords do not match or are empty";
            include __DIR__ . '/../../templates/admin/reset-password.php';
            return;
        }

        if (strlen($newPassword) < 8) {
            $error = "Password must be at least 8 characters";
            include __DIR__ . '/../../templates/admin/reset-password.php';
            return;
        }

        $userId = Auth::getUserId();
        User::updatePassword($userId, $newPassword, true);

        header('Location: /admin');
        exit;
    }

    public function showManage(): void
    {
        AuthMiddleware::requireAdmin();

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        $images = Image::getAllGallery($page);
        $totalImages = Image::getAllGalleryCount();
        $totalPages = ceil($totalImages / 50);

        $users = User::getAllUsers();
        $storageStats = User::getAllStorageStats();

        $db = \App\Core\Database::getInstance()->getConnection();

        // Load all landing page settings
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'landing_%'");
        $stmt->execute();
        $settings = $stmt->fetchAll();

        $landingSettings = [
            'html' => '',
            'bg_color' => '#f5f5f5',
            'text_color' => '#333333',
            'logo_slug' => '',
            'tagline' => ''
        ];

        foreach ($settings as $setting) {
            switch ($setting['setting_key']) {
                case 'landing_page_html':
                    $landingSettings['html'] = $setting['setting_value'];
                    break;
                case 'landing_bg_color':
                    $landingSettings['bg_color'] = $setting['setting_value'];
                    break;
                case 'landing_text_color':
                    $landingSettings['text_color'] = $setting['setting_value'];
                    break;
                case 'landing_logo_slug':
                    $landingSettings['logo_slug'] = $setting['setting_value'];
                    break;
                case 'landing_tagline':
                    $landingSettings['tagline'] = $setting['setting_value'];
                    break;
            }
        }

        include __DIR__ . '/../../templates/admin/manage.php';
    }

    public function handleCreateUser(): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $name = trim($_POST['name'] ?? '');

        try {
            User::create($username, $password, $role, true, $name !== '' ? $name : null);
            header('Location: /admin/manage');
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getUserForEdit(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();

            $userId = (int)($_GET['id'] ?? 0);
            $user = User::findById($userId);

            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'name' => $user['name'] ?? '',
                    'role' => $user['role'],
                    'has_token' => !empty($user['api_token']),
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdateUser(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();
            AuthMiddleware::requireCsrf();

            $userId = (int)($_POST['user_id'] ?? 0);
            $existing = User::findById($userId);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                return;
            }

            $username = trim($_POST['username'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $role = $_POST['role'] ?? $existing['role'];
            $password = $_POST['password'] ?? '';

            if ($username === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Username is required']);
                return;
            }

            // Username uniqueness — only check if changed
            if ($username !== $existing['username']) {
                $other = User::findByUsername($username);
                if ($other && (int)$other['id'] !== $userId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Username already taken']);
                    return;
                }
            }

            // Prevent demoting the only remaining admin
            if ($existing['role'] === 'admin' && $role !== 'admin') {
                $adminCount = 0;
                foreach (User::getAllUsers() as $u) {
                    if (($u['role'] ?? '') === 'admin') $adminCount++;
                }
                if ($adminCount <= 1) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Cannot demote the last admin']);
                    return;
                }
            }

            $data = [
                'name' => $name,
                'username' => $username,
                'role' => $role,
            ];
            if ($password !== '') {
                $data['password'] = $password;
            }

            User::update($userId, $data);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Update user error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleDeleteUser(): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        $userId = (int)($_POST['user_id'] ?? 0);

        // Prevent deleting self
        if ($userId === Auth::getUserId()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Cannot delete yourself']);
            return;
        }

        User::delete($userId);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function handleGenerateToken(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();
            AuthMiddleware::requireCsrf();

            $userId = (int)($_POST['user_id'] ?? 0);
            if (!User::findById($userId)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                return;
            }

            $token = User::createApiToken($userId);
            echo json_encode(['success' => true, 'token' => $token]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Generate token error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleRevokeToken(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();
            AuthMiddleware::requireCsrf();

            $userId = (int)($_POST['user_id'] ?? 0);
            if (!User::findById($userId)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                return;
            }

            User::revokeApiToken($userId);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Revoke token error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdateLandingPage(): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        $html = $_POST['landing_page_html'] ?? '';
        $bgColor = $_POST['landing_bg_color'] ?? '#f5f5f5';
        $textColor = $_POST['landing_text_color'] ?? '#333333';
        $logoSlug = $_POST['landing_logo_slug'] ?? '';
        $tagline = $_POST['landing_tagline'] ?? '';

        $db = \App\Core\Database::getInstance()->getConnection();

        // Update or insert all settings
        $settings = [
            'landing_page_html' => $html,
            'landing_bg_color' => $bgColor,
            'landing_text_color' => $textColor,
            'landing_logo_slug' => $logoSlug,
            'landing_tagline' => $tagline
        ];

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function handleRunMigrations(): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        try {
            $results = \App\Services\Migration::runPending();

            $hasErrors = false;
            $errorDetails = [];

            foreach ($results as $result) {
                if (!$result['success']) {
                    $hasErrors = true;
                    $errorDetails[] = $result['migration'] . ': ' . $result['error'];
                    error_log("Migration failed: " . $result['migration'] . " - " . $result['error']);
                }
            }

            if ($hasErrors) {
                $_SESSION['migration_error'] = 'Migration failed: ' . implode('; ', $errorDetails);
            } else {
                $_SESSION['migration_success'] = count($results) . ' migration(s) applied successfully.';
            }
        } catch (\Exception $e) {
            error_log("Migration exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $_SESSION['migration_error'] = 'Migration failed: ' . $e->getMessage();
        }

        header('Location: /admin/manage');
        exit;
    }

    public function handleRotateImage(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::requireCsrf();

            $imageId = (int)($_POST['image_id'] ?? 0);
            $userId = Auth::getUserId();

            // Verify ownership
            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Get current image
            $image = Image::findById($imageId);
            if (!$image) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Image not found']);
                return;
            }

            if (Image::isAudio($image) || Image::isVideo($image)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot rotate non-image files']);
                return;
            }

            $slotIndex = (int)($_POST['slot_index'] ?? 0);
            $slots = Image::decodeSlots($image['image_data'] ?? '');

            if ($slots !== null) {
                // Gallery format: rotate specific slot
                if (!isset($slots[$slotIndex])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid slot index']);
                    return;
                }
                $masterData = base64_decode($slots[$slotIndex]['data'] ?? '');
                $thumbData = base64_decode($slots[$slotIndex]['thumb'] ?? '');
                $slotMeta = $slots[$slotIndex]['metadata'] ?? [];
                $rotated = \App\Services\ImageProcessor::rotateImageData($masterData, $thumbData, json_encode($slotMeta));
                $slots[$slotIndex]['data'] = base64_encode($rotated['master']);
                $slots[$slotIndex]['thumb'] = base64_encode($rotated['thumbnail']);
                $slots[$slotIndex]['metadata'] = $rotated['metadata'];
                $newThumbData = $slotIndex === 0 ? $rotated['thumbnail'] : null;
                Image::updateSlots($imageId, $slots, $newThumbData);
            } else {
                // Legacy binary format
                $rotated = \App\Services\ImageProcessor::rotateImageData(
                    $image['image_data'],
                    $image['thumb_data'],
                    $image['metadata']
                );
                Image::updateRotatedImage($imageId, $rotated['master'], $rotated['thumbnail'], $rotated['metadata']);
            }

            echo json_encode([
                'success' => true,
                'thumbnail' => base64_encode($rotated['thumbnail'])
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Rotate error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleRevertToOriginal(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::requireCsrf();

            $imageId = (int)($_POST['image_id'] ?? 0);
            $userId = Auth::getUserId();

            // Verify ownership
            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $image = Image::findById($imageId);
            if (!$image) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Image not found']);
                return;
            }

            $slotIndex = (int)($_POST['slot_index'] ?? -1);
            $slots = Image::decodeSlots($image['image_data'] ?? '');

            if ($slots !== null && $slotIndex >= 0) {
                // Gallery format: revert specific slot to its original
                if (!isset($slots[$slotIndex])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid slot index']);
                    return;
                }
                if (empty($slots[$slotIndex]['original'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No original data for this slot']);
                    return;
                }
                $originalData = base64_decode($slots[$slotIndex]['original']);
                $imagick = new \Imagick();
                $imagick->readImageBlob($originalData);
                $thumb = clone $imagick;
                $thumb->cropThumbnailImage(300, 300);
                $thumb->setImageFormat('jpeg');
                $thumb->setImageCompressionQuality(85);
                $thumbBin = $thumb->getImageBlob();
                $slots[$slotIndex]['data'] = $slots[$slotIndex]['original'];
                $slots[$slotIndex]['thumb'] = base64_encode($thumbBin);
                $slots[$slotIndex]['file_size'] = strlen($originalData);
                $newThumbData = $slotIndex === 0 ? $thumbBin : null;
                Image::updateSlots($imageId, $slots, $newThumbData);
                echo json_encode(['success' => true]);
            } else {
                // Legacy binary format
                $success = Image::revertToOriginal($imageId);
                if ($success) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No original image data found']);
                }
            }
        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Revert error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getShareSettings(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();

            $imageId = (int)($_GET['image_id'] ?? 0);
            $userId = Auth::getUserId();

            // Verify ownership
            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $image = Image::findById($imageId);
            if (!$image) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Image not found']);
                return;
            }

            // Parse display_metadata JSON
            $displayMetadata = null;
            if (!empty($image['display_metadata'])) {
                $displayMetadata = json_decode($image['display_metadata'], true);
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'expires_at' => $image['expires_at'] ?? null,
                    'has_password' => !empty($image['link_password']),
                    'media_type' => $image['media_type'] ?? 'image',
                    'display_metadata' => $displayMetadata,
                    'caption' => $image['caption'] ?? ''
                ]
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function updateShareSettings(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::requireCsrf();

            $imageId = (int)($_POST['image_id'] ?? 0);
            $userId = Auth::getUserId();

            // Verify ownership
            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $expiration = $_POST['expiration'] ?? '';
            $customExpiration = $_POST['custom_expiration'] ?? '';
            $password = $_POST['password'] ?? '';
            $rawCaption = $_POST['caption'] ?? '';
            $caption = $rawCaption !== '' ? HtmlSanitizer::sanitizeCaption($rawCaption) : '';

            // Calculate expiration timestamp
            $expiresAt = null;
            if ($expiration) {
                switch ($expiration) {
                    case '1hour':
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        break;
                    case '24hours':
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                        break;
                    case '7days':
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
                        break;
                    case '30days':
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                        break;
                    case 'custom':
                        if ($customExpiration) {
                            $expiresAt = date('Y-m-d H:i:s', strtotime($customExpiration));
                        }
                        break;
                }
            }

            // Hash password if provided
            $passwordHash = null;
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            }

            // Build display metadata JSON
            $displayMetadata = [
                'show_display_name' => isset($_POST['show_display_name']),
                'show_caption'      => isset($_POST['show_caption']),
                'show_date'         => isset($_POST['show_date']),
                'show_views'        => isset($_POST['show_views']),
                'show_duration'     => isset($_POST['show_duration']),
                'show_download'     => isset($_POST['show_download']),
            ];

            // Update settings
            Image::updateShareSettings($imageId, $expiresAt, $passwordHash, $displayMetadata, $caption);

            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Share settings error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleEditImage(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::requireCsrf();

            $imageId = (int)($_POST['image_id'] ?? 0);
            $userId = Auth::getUserId();

            // Verify ownership
            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Get current image
            $image = Image::findById($imageId);
            if (!$image) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Image not found']);
                return;
            }

            if (Image::isAudio($image) || Image::isVideo($image)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cannot edit non-image files']);
                return;
            }

            // Get edit parameters
            $edits = [
                'brightness' => (int)($_POST['brightness'] ?? 0),
                'contrast' => (int)($_POST['contrast'] ?? 0),
                'filter' => $_POST['filter'] ?? 'original'
            ];

            if (!empty($_POST['crop'])) {
                $cropData = json_decode($_POST['crop'], true);
                if ($cropData && isset($cropData['x'], $cropData['y'], $cropData['width'], $cropData['height'])) {
                    $edits['crop'] = $cropData;
                }
            }

            // Detect gallery format
            $slots = Image::decodeSlots($image['image_data'] ?? '');
            if ($slots !== null) {
                // Gallery format: edit the specified slot
                $slotIndex = (int)($_POST['slot_index'] ?? 0);
                if (!isset($slots[$slotIndex])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid slot index']);
                    return;
                }
                $originalB64 = !empty($slots[$slotIndex]['original']) ? $slots[$slotIndex]['original'] : $slots[$slotIndex]['data'];
                $sourceData = base64_decode($originalB64);
                $edited = \App\Services\ImageEditor::applyEdits($sourceData, $edits);
                $slots[$slotIndex]['data'] = base64_encode($edited['master']);
                $slots[$slotIndex]['thumb'] = base64_encode($edited['thumbnail']);
                $slots[$slotIndex]['file_size'] = strlen($edited['master']);
                $newThumbData = $slotIndex === 0 ? $edited['thumbnail'] : null;
                Image::updateSlots($imageId, $slots, $newThumbData);
            } else {
                // Legacy binary format
                if (empty($image['original_image_data'])) {
                    $db = \App\Core\Database::getInstance()->getConnection();
                    try {
                        $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'original_image_data'");
                        if ($stmt->rowCount() > 0) {
                            $updateStmt = $db->prepare("UPDATE images SET original_image_data = image_data WHERE id = ?");
                            $updateStmt->execute([$imageId]);
                            $image = Image::findById($imageId);
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to save original image data: " . $e->getMessage());
                    }
                }
                $sourceData = !empty($image['original_image_data']) ? $image['original_image_data'] : $image['image_data'];
                $edited = \App\Services\ImageEditor::applyEdits($sourceData, $edits);
                $metadata = $image['metadata'] ? json_decode($image['metadata'], true) : [];
                if (!isset($metadata['processing'])) $metadata['processing'] = [];
                if (!isset($metadata['processing']['edits'])) $metadata['processing']['edits'] = [];
                $editRecord = ['timestamp' => date('Y-m-d H:i:s'), 'brightness' => $edits['brightness'], 'contrast' => $edits['contrast'], 'filter' => $edits['filter']];
                if (!empty($edits['crop'])) $editRecord['crop'] = $edits['crop'];
                $metadata['processing']['edits'][] = $editRecord;
                Image::updateRotatedImage($imageId, $edited['master'], $edited['thumbnail'], $metadata);
            }

            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Image edit error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function showLandingPages(): void
    {
        AuthMiddleware::requireAdmin();

        $landingPages = \App\Models\LandingPage::getAll();

        include __DIR__ . '/../../templates/admin/landing_pages.php';
    }

    public function getLandingPage(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();

            $id = (int)($_GET['id'] ?? 0);
            $page = \App\Models\LandingPage::findById($id);

            if ($page) {
                echo json_encode(['success' => true, 'data' => $page]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Landing page not found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function createLandingPage(): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        $name = $_POST['name'] ?? '';
        if (empty($name)) {
            $_SESSION['landing_page_error'] = 'Name is required';
            header('Location: /admin/landing-pages');
            return;
        }

        $settings = [
            'html_content' => $_POST['html_content'] ?? '',
            'bg_color' => $_POST['bg_color'] ?? '#f5f5f5',
            'text_color' => $_POST['text_color'] ?? '#333333',
            'logo_slug' => $_POST['logo_slug'] ?? null,
            'tagline' => $_POST['tagline'] ?? null
        ];

        \App\Models\LandingPage::create($name, $settings);

        $_SESSION['landing_page_success'] = 'Landing page created successfully';
        header('Location: /admin/landing-pages');
    }

    public function updateLandingPage(): void
    {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        $id = (int)($_POST['page_id'] ?? 0);
        $name = $_POST['name'] ?? '';

        if (empty($name)) {
            $_SESSION['landing_page_error'] = 'Name is required';
            header('Location: /admin/landing-pages');
            return;
        }

        $settings = [
            'html_content' => $_POST['html_content'] ?? '',
            'bg_color' => $_POST['bg_color'] ?? '#f5f5f5',
            'text_color' => $_POST['text_color'] ?? '#333333',
            'logo_slug' => $_POST['logo_slug'] ?? null,
            'tagline' => $_POST['tagline'] ?? null
        ];

        \App\Models\LandingPage::update($id, $name, $settings);

        $_SESSION['landing_page_success'] = 'Landing page updated successfully';
        header('Location: /admin/landing-pages');
    }

    public function deleteLandingPage(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();
            AuthMiddleware::requireCsrf();

            $id = (int)($_POST['page_id'] ?? 0);
            $success = \App\Models\LandingPage::delete($id);

            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Cannot delete active landing page']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function setActiveLandingPage(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();
            AuthMiddleware::requireCsrf();

            $id = (int)($_POST['page_id'] ?? 0);
            $success = \App\Models\LandingPage::setActive($id);

            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to set active']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleGalleryUpload(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::checkPasswordReset();
            AuthMiddleware::requireCsrf();

            $userId = Auth::getUserId();
            $rawCaption = $_POST['caption'] ?? null;
            $caption = $rawCaption !== null && $rawCaption !== '' ? HtmlSanitizer::sanitizeCaption($rawCaption) : null;

            $files = [];
            if (isset($_FILES['images'])) {
                $fileCount = count($_FILES['images']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $files[] = [
                            'name'     => $_FILES['images']['name'][$i],
                            'type'     => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error'    => $_FILES['images']['error'][$i],
                            'size'     => $_FILES['images']['size'][$i],
                        ];
                    }
                }
            }

            if (empty($files)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No images provided']);
                return;
            }

            $result = Uploader::handleGalleryUpload($userId, $files, $caption);
            echo json_encode(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            http_response_code(400);
            error_log("Gallery upload error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleProcessGalleryImage(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::requireCsrf();

            if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('No valid file provided');
            }

            $slot = \App\Services\ImageProcessor::processToSlot($_FILES['image']['tmp_name']);
            echo json_encode(['success' => true, 'slot' => $slot]);

        } catch (\Exception $e) {
            http_response_code(400);
            error_log("Process gallery image error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdateImages(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();
            AuthMiddleware::requireCsrf();

            $imageId = (int)($_POST['image_id'] ?? 0);
            $slotsJson = $_POST['slots'] ?? '';
            $userId = Auth::getUserId();

            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $slots = json_decode($slotsJson, true);
            if (!is_array($slots) || count($slots) === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid slots data']);
                return;
            }

            $maxImages = (int) Setting::get('max_gallery_images', 5);
            if (count($slots) > $maxImages) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Cannot have more than {$maxImages} images"]);
                return;
            }

            foreach ($slots as $slot) {
                if (empty($slot['data']) || empty($slot['thumb'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid slot data']);
                    return;
                }
            }

            $newThumbData = base64_decode($slots[0]['thumb']);
            Image::updateSlots($imageId, $slots, $newThumbData);

            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            http_response_code(500);
            error_log("Update images error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleSaveSettings(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAdmin();
            AuthMiddleware::requireCsrf();

            $maxImages = (int)($_POST['max_gallery_images'] ?? 5);

            if ($maxImages < 1 || $maxImages > 20) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Max images must be between 1 and 20']);
                return;
            }

            Setting::set('max_gallery_images', (string)$maxImages);

            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getGallerySlots(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();

            $imageId = (int)($_GET['image_id'] ?? 0);
            $userId = Auth::getUserId();

            if (!Image::isOwnedByUser($imageId, $userId) && !Auth::isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $image = Image::findById($imageId);
            if (!$image) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Not found']);
                return;
            }

            $slots = Image::decodeSlots($image['image_data'] ?? '');
            if ($slots === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Not a gallery image']);
                return;
            }

            // Return slots with thumb only (strip metadata to keep payload small)
            $lightSlots = array_map(fn($s) => [
                'data'      => $s['data'],
                'original'  => $s['original'] ?? $s['data'],
                'thumb'     => $s['thumb'],
                'mime_type' => $s['mime_type'] ?? 'image/jpeg',
                'file_size' => $s['file_size'] ?? 0,
            ], $slots);

            echo json_encode([
                'success'   => true,
                'slots'     => $lightSlots,
                'max_slots' => (int) Setting::get('max_gallery_images', 5),
                'caption'   => $image['caption'] ?? '',
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getGalleryPickerItems(): void
    {
        header('Content-Type: application/json');

        try {
            AuthMiddleware::requireAuth();

            $userId = Auth::getUserId();
            $page = max(1, (int)($_GET['page'] ?? 1));
            $images = Image::getGalleryForUser($userId, $page, 24);

            $items = [];
            foreach ($images as $img) {
                if (Image::isImage($img)) {
                    $items[] = [
                        'id'    => (int)$img['id'],
                        'slug'  => $img['slug'],
                        'thumb' => base64_encode($img['thumb_data']),
                    ];
                }
            }

            echo json_encode(['success' => true, 'items' => $items]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function showIpBlocks(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireAdmin();

        $blocks = RateLimiter::getAll();
        include __DIR__ . '/../../templates/admin/ip_blocks.php';
    }

    public function handleUnblockIp(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        header('Content-Type: application/json');

        $ip = $_POST['ip'] ?? '';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid IP address']);
            return;
        }

        RateLimiter::unblockIp($ip);
        echo json_encode(['success' => true]);
    }

    public function handleManualBlockIp(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireAdmin();
        AuthMiddleware::requireCsrf();

        header('Content-Type: application/json');

        $ip = $_POST['ip'] ?? '';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid IP address']);
            return;
        }

        RateLimiter::manualBlockIp($ip);
        echo json_encode(['success' => true]);
    }
}
