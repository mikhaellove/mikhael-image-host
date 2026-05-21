<?php

namespace App\Controllers;

use App\Core\RateLimiter;
use App\Models\Image;
use App\Models\LandingPage;

class ViewerController
{
    public function showLandingPage(): void
    {
        // Try to load from landing_pages table (new multi-landing page system)
        if (LandingPage::tableExists()) {
            $activePage = LandingPage::getActive();

            if ($activePage) {
                $this->renderLandingPage($activePage);
                return;
            }
        }

        // Fallback to old settings table for backwards compatibility
        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'landing_%'");
        $stmt->execute();
        $settings = $stmt->fetchAll();

        $landingSettings = [
            'html' => '<h1>Welcome to Project Vault</h1>',
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

        // Sanitize HTML to prevent XSS
        $cleanHtml = \App\Services\HtmlSanitizer::sanitize($landingSettings['html']);

        include __DIR__ . '/../../templates/viewer/landing.php';
    }

    public function previewLandingPage(int $id): void
    {
        // Require authentication - only logged in users can preview
        session_start();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo "403 - Unauthorized. Please log in to preview landing pages.";
            return;
        }

        // Load the specific landing page
        $page = LandingPage::findById($id);

        if (!$page) {
            http_response_code(404);
            echo "404 - Landing Page Not Found";
            return;
        }

        // Render it
        $this->renderLandingPage($page);
    }

    private function renderLandingPage(array $page): void
    {
        $landingSettings = [
            'html' => $page['html_content'] ?? '<h1>Welcome to Project Vault</h1>',
            'bg_color' => $page['bg_color'] ?? '#f5f5f5',
            'text_color' => $page['text_color'] ?? '#333333',
            'logo_slug' => $page['logo_slug'] ?? '',
            'tagline' => $page['tagline'] ?? ''
        ];

        // Sanitize HTML to prevent XSS
        $cleanHtml = \App\Services\HtmlSanitizer::sanitize($landingSettings['html']);

        include __DIR__ . '/../../templates/viewer/landing.php';
    }

    public function showImage(string $slug): void
    {
        $ip = RateLimiter::getClientIp();
        if ($ip === null || RateLimiter::isBlocked($ip)) {
            http_response_code(403);
            echo "403 - Forbidden";
            return;
        }

        $image = Image::findBySlug($slug);

        if (!$image) {
            RateLimiter::recordFailedAttempt($ip);
            http_response_code(404);
            echo "404 - Media Not Found";
            return;
        }

        // Check if image has expired
        if (Image::isExpired($image)) {
            http_response_code(410);
            include __DIR__ . '/../../templates/viewer/expired.php';
            return;
        }

        // Check if password required
        if (!empty($image['link_password'])) {
            session_start();
            $sessionKey = 'image_password_' . $image['id'];

            // Check if password already verified in session
            if (!isset($_SESSION[$sessionKey]) || $_SESSION[$sessionKey] !== true) {
                // Show password prompt
                include __DIR__ . '/../../templates/viewer/password_prompt.php';
                return;
            }
        }

        // Increment view counter (deduplicated per-IP per hour)
        Image::incrementViewCount($image['id'], $ip);

        // Route to appropriate template based on media type
        if (Image::isAudio($image)) {
            include __DIR__ . '/../../templates/viewer/audio.php';
        } elseif (Image::isVideo($image)) {
            include __DIR__ . '/../../templates/viewer/video.php';
        } else {
            $slots = Image::decodeSlots($image['image_data'] ?? '');
            if ($slots !== null) {
                include __DIR__ . '/../../templates/viewer/gallery.php';
            } else {
                include __DIR__ . '/../../templates/viewer/image.php';
            }
        }
    }

    public function serveRawImage(string $slug): void
    {
        $ip = RateLimiter::getClientIp();
        if ($ip === null || RateLimiter::isBlocked($ip)) {
            http_response_code(403);
            echo "403 - Forbidden";
            return;
        }

        $image = Image::findBySlug($slug);

        if (!$image) {
            RateLimiter::recordFailedAttempt($ip);
            http_response_code(404);
            echo "404 - Image Not Found";
            return;
        }

        // Detect gallery format and serve first slot
        $slots = Image::decodeSlots($image['image_data'] ?? '');
        if ($slots !== null) {
            $data = base64_decode($slots[0]['data'] ?? '');
            $mimeType = $slots[0]['mime_type'] ?? 'image/jpeg';
        } else {
            $data = $image['image_data'];
            $mimeType = $image['mime_type'];
        }

        $totalLength = strlen($data);
        $start = 0;
        $end = $totalLength - 1;

        if (isset($_SERVER['HTTP_RANGE'])
            && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)
        ) {
            $start = (int)$m[1];
            $end = ($m[2] !== '') ? (int)$m[2] : $totalLength - 1;

            if ($start > $end || $start >= $totalLength) {
                http_response_code(416);
                header('Content-Range: bytes */' . $totalLength);
                return;
            }

            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $totalLength);
        }

        header('Accept-Ranges: bytes');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . ($end - $start + 1));
        header('Cache-Control: public, max-age=31536000');

        echo substr($data, $start, $end - $start + 1);
    }

    public function serveRawImageByIndex(string $slug, int $index): void
    {
        $ip = RateLimiter::getClientIp();
        if ($ip === null || RateLimiter::isBlocked($ip)) {
            http_response_code(403);
            echo "403 - Forbidden";
            return;
        }

        $image = Image::findBySlug($slug);

        if (!$image) {
            RateLimiter::recordFailedAttempt($ip);
            http_response_code(404);
            echo "404 - Image Not Found";
            return;
        }

        $slots = Image::decodeSlots($image['image_data'] ?? '');
        if ($slots === null || !isset($slots[$index])) {
            http_response_code(404);
            echo "404 - Slot Not Found";
            return;
        }

        $data = base64_decode($slots[$index]['data'] ?? '');
        $mimeType = $slots[$index]['mime_type'] ?? 'image/jpeg';

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: public, max-age=31536000');

        echo $data;
    }

    public function serveThumbnail(string $slug): void
    {
        $ip = RateLimiter::getClientIp();
        if ($ip === null || RateLimiter::isBlocked($ip)) {
            http_response_code(403);
            return;
        }

        $image = Image::findBySlug($slug);

        if (!$image) {
            RateLimiter::recordFailedAttempt($ip);
            http_response_code(404);
            return;
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($image['thumb_data']));
        header('Cache-Control: public, max-age=31536000');

        echo $image['thumb_data'];
    }

    public function verifyPassword(string $slug): void
    {
        $image = Image::findBySlug($slug);

        if (!$image) {
            http_response_code(404);
            echo "404 - Image Not Found";
            return;
        }

        $password = $_POST['password'] ?? '';

        if (Image::verifyPassword($image, $password)) {
            // Password correct - set session and redirect
            session_start();
            $sessionKey = 'image_password_' . $image['id'];
            $_SESSION[$sessionKey] = true;

            header('Location: /v/' . $slug);
            exit;
        } else {
            // Password incorrect - redirect back with error
            header('Location: /v/' . $slug . '?error=1');
            exit;
        }
    }
}
