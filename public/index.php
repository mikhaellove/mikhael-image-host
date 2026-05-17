<?php

// Bootstrap the application
require_once __DIR__ . '/../autoload.php';

use App\Core\Router;
use App\Core\Database;
use App\Core\Cache;
use App\Core\Auth;
use App\Controllers\InstallController;
use App\Controllers\ViewerController;
use App\Controllers\AdminController;
use App\Controllers\ApiController;

// Start session
Auth::startSession();

// Check if installation is required
$configPath = __DIR__ . '/../config/config.php';

if (!file_exists($configPath)) {
    // Installation mode - only allow installer routes
    $router = new Router();

    $router->get('/install', function() {
        $controller = new InstallController();
        $controller->showInstaller();
    });

    $router->post('/install/preflight', function() {
        $controller = new InstallController();
        $controller->preFlightCheck();
    });

    $router->post('/install/test-connection', function() {
        $controller = new InstallController();
        $controller->testConnection();
    });

    $router->post('/install/run', function() {
        $controller = new InstallController();
        $controller->install();
    });

    // Redirect everything else to installer
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (!str_starts_with($currentPath, '/install')) {
        header('Location: /install');
        exit;
    }

    $router->dispatch();
    exit;
}

// Load configuration
$config = require $configPath;

// Initialize database
Database::getInstance($config['database']);

// Initialize Redis cache (used for per-IP view-count deduplication).
// Optional config block; defaults to 127.0.0.1:6379 if absent.
Cache::init($config['redis'] ?? []);

// Define routes
$router = new Router();

// Public routes
$router->get('/', function() {
    $controller = new ViewerController();
    $controller->showLandingPage();
});

$router->get('/preview/{id}', function($id) {
    $controller = new ViewerController();
    $controller->previewLandingPage((int)$id);
});

$router->get('/v/{slug}', function($slug) {
    $controller = new ViewerController();
    $controller->showImage($slug);
});

$router->post('/v/{slug}/verify', function($slug) {
    $controller = new ViewerController();
    $controller->verifyPassword($slug);
});

$router->get('/raw/{slug}', function($slug) {
    $controller = new ViewerController();
    $controller->serveRawImage($slug);
});

$router->get('/thumb/{slug}', function($slug) {
    $controller = new ViewerController();
    $controller->serveThumbnail($slug);
});

// Admin routes
$router->get('/admin/login', function() {
    $controller = new AdminController();
    $controller->showLogin();
});

$router->post('/admin/login', function() {
    $controller = new AdminController();
    $controller->handleLogin();
});

$router->get('/admin/logout', function() {
    $controller = new AdminController();
    $controller->handleLogout();
});

$router->get('/admin', function() {
    $controller = new AdminController();
    $controller->showDashboard();
});

$router->post('/admin/upload', function() {
    $controller = new AdminController();
    $controller->handleUpload();
});

$router->post('/admin/delete', function() {
    $controller = new AdminController();
    $controller->handleDelete();
});

$router->post('/admin/update-caption', function() {
    $controller = new AdminController();
    $controller->handleUpdateCaption();
});

$router->get('/admin/reset-password', function() {
    $controller = new AdminController();
    $controller->showResetPassword();
});

$router->post('/admin/reset-password', function() {
    $controller = new AdminController();
    $controller->handleResetPassword();
});

$router->get('/admin/manage', function() {
    $controller = new AdminController();
    $controller->showManage();
});

$router->post('/admin/create-user', function() {
    $controller = new AdminController();
    $controller->handleCreateUser();
});

$router->post('/admin/delete-user', function() {
    $controller = new AdminController();
    $controller->handleDeleteUser();
});

$router->get('/admin/get-user', function() {
    $controller = new AdminController();
    $controller->getUserForEdit();
});

$router->post('/admin/update-user', function() {
    $controller = new AdminController();
    $controller->handleUpdateUser();
});

$router->post('/admin/generate-token', function() {
    $controller = new AdminController();
    $controller->handleGenerateToken();
});

$router->post('/admin/revoke-token', function() {
    $controller = new AdminController();
    $controller->handleRevokeToken();
});

$router->post('/admin/update-landing-page', function() {
    $controller = new AdminController();
    $controller->handleUpdateLandingPage();
});

$router->post('/admin/run-migrations', function() {
    $controller = new AdminController();
    $controller->handleRunMigrations();
});

$router->post('/admin/rotate-image', function() {
    $controller = new AdminController();
    $controller->handleRotateImage();
});

$router->get('/admin/get-share-settings', function() {
    $controller = new AdminController();
    $controller->getShareSettings();
});

$router->post('/admin/update-share-settings', function() {
    $controller = new AdminController();
    $controller->updateShareSettings();
});

$router->post('/admin/edit-image', function() {
    $controller = new AdminController();
    $controller->handleEditImage();
});

$router->post('/admin/revert-to-original', function() {
    $controller = new AdminController();
    $controller->handleRevertToOriginal();
});

$router->get('/admin/landing-pages', function() {
    $controller = new AdminController();
    $controller->showLandingPages();
});

$router->get('/admin/landing-page-get', function() {
    $controller = new AdminController();
    $controller->getLandingPage();
});

$router->post('/admin/landing-page-create', function() {
    $controller = new AdminController();
    $controller->createLandingPage();
});

$router->post('/admin/landing-page-update', function() {
    $controller = new AdminController();
    $controller->updateLandingPage();
});

$router->post('/admin/landing-page-delete', function() {
    $controller = new AdminController();
    $controller->deleteLandingPage();
});

$router->post('/admin/landing-page-set-active', function() {
    $controller = new AdminController();
    $controller->setActiveLandingPage();
});

// API routes
$router->post('/api/upload', function() {
    $controller = new ApiController();
    $controller->handleUpload();
});

// IP blocks management routes
$router->get('/admin/ip-blocks', function() {
    $controller = new AdminController();
    $controller->showIpBlocks();
});

$router->post('/admin/unblock-ip', function() {
    $controller = new AdminController();
    $controller->handleUnblockIp();
});

$router->post('/admin/manual-block-ip', function() {
    $controller = new AdminController();
    $controller->handleManualBlockIp();
});

// Dispatch the router
$router->dispatch();
