<?php
use App\Core\Auth;
$pageTitle = 'Reset Password';
include __DIR__ . '/../layout/header.php';
?>

<div class="container" style="max-width: 400px; margin-top: 100px;">
    <h1 style="margin-bottom: 20px;">Reset Password</h1>

    <p style="margin-bottom: 20px; color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;">
        You must reset your password before continuing.
    </p>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/reset-password">
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">

        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8" autofocus>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>

        <button type="submit" class="btn">Reset Password</button>
    </form>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
