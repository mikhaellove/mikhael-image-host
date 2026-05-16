<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    private const SESSION_KEY = 'user_id';
    private const USER_AGENT_KEY = 'user_agent';
    private const CSRF_TOKEN_KEY = 'csrf_token';
    private const LOGIN_ATTEMPTS_KEY = 'login_attempts';
    private const LOGIN_COOLDOWN_KEY = 'login_cooldown';
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const COOLDOWN_SECONDS = 300; // 5 minutes

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(string $username, string $password): bool
    {
        self::startSession();

        // Check rate limiting
        if (self::isLoginCooldown()) {
            return false;
        }

        $user = User::findByUsername($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordFailedLogin();
            return false;
        }

        // Reset failed attempts on successful login
        unset($_SESSION[self::LOGIN_ATTEMPTS_KEY]);
        unset($_SESSION[self::LOGIN_COOLDOWN_KEY]);

        $_SESSION[self::SESSION_KEY] = $user['id'];
        $_SESSION[self::USER_AGENT_KEY] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        self::regenerateCsrfToken();

        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        session_destroy();
    }

    public static function isAuthenticated(): bool
    {
        self::startSession();

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        // Verify User-Agent hasn't changed (session hijacking prevention)
        $storedAgent = $_SESSION[self::USER_AGENT_KEY] ?? '';
        $currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($storedAgent !== $currentAgent) {
            self::logout();
            return false;
        }

        return true;
    }

    public static function getUserId(): ?int
    {
        self::startSession();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function getUser(): ?array
    {
        $userId = self::getUserId();
        if (!$userId) {
            return null;
        }
        return User::findById($userId);
    }

    public static function isAdmin(): bool
    {
        $user = self::getUser();
        return $user && $user['role'] === 'admin';
    }

    public static function mustResetPassword(): bool
    {
        $user = self::getUser();
        return $user && $user['must_reset'] == 1;
    }

    public static function generateCsrfToken(): string
    {
        self::startSession();

        if (!isset($_SESSION[self::CSRF_TOKEN_KEY])) {
            $_SESSION[self::CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    public static function regenerateCsrfToken(): string
    {
        self::startSession();
        $_SESSION[self::CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    public static function verifyCsrfToken(string $token): bool
    {
        self::startSession();
        $storedToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? '';
        return hash_equals($storedToken, $token);
    }

    private static function recordFailedLogin(): void
    {
        $attempts = $_SESSION[self::LOGIN_ATTEMPTS_KEY] ?? 0;
        $attempts++;
        $_SESSION[self::LOGIN_ATTEMPTS_KEY] = $attempts;

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $_SESSION[self::LOGIN_COOLDOWN_KEY] = time() + self::COOLDOWN_SECONDS;
        }
    }

    private static function isLoginCooldown(): bool
    {
        if (!isset($_SESSION[self::LOGIN_COOLDOWN_KEY])) {
            return false;
        }

        if (time() < $_SESSION[self::LOGIN_COOLDOWN_KEY]) {
            return true;
        }

        // Cooldown expired, reset
        unset($_SESSION[self::LOGIN_ATTEMPTS_KEY]);
        unset($_SESSION[self::LOGIN_COOLDOWN_KEY]);
        return false;
    }

    public static function getLoginCooldownRemaining(): int
    {
        if (!isset($_SESSION[self::LOGIN_COOLDOWN_KEY])) {
            return 0;
        }

        $remaining = $_SESSION[self::LOGIN_COOLDOWN_KEY] - time();
        return max(0, $remaining);
    }
}
