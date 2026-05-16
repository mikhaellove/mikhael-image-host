<?php

namespace App\Core;

class AuthMiddleware
{
    public static function requireAuth(): void
    {
        if (!Auth::isAuthenticated()) {
            http_response_code(401);
            header('Location: /admin/login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();

        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo "Access denied. Admin privileges required.";
            exit;
        }
    }

    public static function checkPasswordReset(): void
    {
        if (Auth::isAuthenticated() && Auth::mustResetPassword()) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            // Allow access only to reset password page and logout
            if ($currentPath !== '/admin/reset-password' && $currentPath !== '/admin/logout') {
                header('Location: /admin/reset-password');
                exit;
            }
        }
    }

    public static function requireCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

            if (!Auth::verifyCsrfToken($token)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
        }
    }

    public static function requireApiAuth(): ?int
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing or invalid Authorization header']);
            exit;
        }

        $token = $matches[1];
        $userId = \App\Models\User::validateApiToken($token);

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API token']);
            exit;
        }

        return $userId;
    }
}
