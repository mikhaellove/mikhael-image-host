<?php
use App\Core\Auth;
$pageTitle = 'Login';
include __DIR__ . '/../layout/header.php';
?>

<div class="container" style="max-width: 400px; margin-top: 100px;">
    <h1 style="margin-bottom: 20px;">Login</h1>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/login">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn">Login</button>
    </form>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
