<?php
use App\Core\Auth;
$pageTitle = 'Admin Management';
include __DIR__ . '/../layout/header.php';
?>

<style>
    nav {
        background: #fff;
        padding: 15px 0;
        margin-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }

    nav .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    table {
        width: 100%;
        background: #fff;
        border-collapse: collapse;
        margin-bottom: 30px;
    }

    table th,
    table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    table th {
        background: #f8f9fa;
        font-weight: 600;
    }

    .section {
        background: #fff;
        padding: 20px;
        border-radius: 4px;
        margin-bottom: 30px;
    }

    .gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .gallery-item {
        background: #fff;
        border-radius: 4px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .gallery-item img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        display: block;
    }

    .gallery-item-info {
        padding: 10px;
        font-size: 12px;
    }
</style>
<nav>
    <div class="container">
        <div>
            <strong>Admin Management</strong>
        </div>
        <div>
            <a href="/admin" class="btn">Dashboard</a>
            <a href="/admin/logout" class="btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['migration_success'])): ?>
        <div class="section" style="background: #d4edda; border-left: 4px solid #28a745; color: #155724;">
            <p><strong>✓ Success:</strong> <?= htmlspecialchars($_SESSION['migration_success']) ?></p>
        </div>
        <?php unset($_SESSION['migration_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['migration_error'])): ?>
        <div class="section" style="background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">
            <p><strong>✗ Error:</strong> <?= htmlspecialchars($_SESSION['migration_error']) ?></p>
        </div>
        <?php unset($_SESSION['migration_error']); ?>
    <?php endif; ?>

    <!-- Gallery Settings -->
    <div class="section">
        <h2>Gallery Settings</h2>
        <p style="color:#666; margin-bottom:15px;">Configure limits for multi-image galleries.</p>
        <form id="gallerySettingsForm">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">
            <div class="form-group" style="display:flex; align-items:center; gap:15px;">
                <label for="max_gallery_images" style="white-space:nowrap; font-weight:600;">Max images per gallery</label>
                <input type="number" id="max_gallery_images" name="max_gallery_images"
                       min="1" max="20"
                       value="<?= htmlspecialchars(\App\Models\Setting::get('max_gallery_images', 5)) ?>"
                       style="width:80px; padding:8px; border:1px solid #ddd; border-radius:4px;">
                <button type="submit" class="btn">Save</button>
                <span id="gallerySettingsStatus" style="color:#28a745;"></span>
            </div>
        </form>
        <script>
            document.getElementById('gallerySettingsForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const status = document.getElementById('gallerySettingsStatus');
                try {
                    const resp = await fetch('/admin/save-settings', { method: 'POST', body: formData });
                    const result = await resp.json();
                    status.textContent = result.success ? '✓ Saved' : '✗ ' + result.error;
                    status.style.color = result.success ? '#28a745' : '#dc3545';
                } catch (e) {
                    status.textContent = '✗ Failed';
                    status.style.color = '#dc3545';
                }
            });
        </script>
    </div>

    <!-- Database Migrations -->
    <?php
    use App\Services\Migration;
    $pendingMigrations = Migration::getPending();
    $appliedMigrations = Migration::getApplied();
    ?>
    <?php if (!empty($pendingMigrations)): ?>
    <div class="section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <h2>⚠️ Database Migrations Required</h2>
        <p><strong>Pending migrations:</strong> <?= count($pendingMigrations) ?></p>
        <ul>
            <?php foreach ($pendingMigrations as $migration): ?>
                <li><code><?= htmlspecialchars($migration) ?></code></li>
            <?php endforeach; ?>
        </ul>
        <form method="POST" action="/admin/run-migrations" style="margin-top: 15px;">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">
            <button type="submit" class="btn" style="background: #ffc107; color: #000;">Run Migrations Now</button>
        </form>
        <p style="margin-top: 10px; font-size: 14px; color: #856404;">
            <strong>Note:</strong> Running migrations will update the database schema. Regular users can continue using the site, but new features requiring these migrations will not be available until migrations are completed.
        </p>
    </div>
    <?php endif; ?>

    <?php if (!empty($appliedMigrations)): ?>
    <div class="section">
        <h2>Applied Migrations</h2>
        <table>
            <thead>
                <tr>
                    <th>Migration</th>
                    <th>Applied At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($appliedMigrations) as $migration): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($migration['migration_name']) ?></code></td>
                        <td><?= htmlspecialchars($migration['applied_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- User Management -->
    <div class="section">
        <h2>User Management</h2>

        <h3>Create New User</h3>
        <form method="POST" action="/admin/create-user">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" placeholder="Display name (optional)">
            </div>

            <div class="form-group">
                <label for="password">Temporary Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" class="btn">Create User</button>
        </form>

        <h3 style="margin-top: 30px;">Existing Users</h3>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Must Reset</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td><?= $user['must_reset'] ? 'Yes' : 'No' ?></td>
                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                        <td>
                            <a href="#" onclick="openEditUser(<?= $user['id'] ?>); return false;" class="btn">Edit</a>
                            <a href="#" onclick="deleteUser(<?= $user['id'] ?>); return false;" class="btn btn-danger">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Storage Statistics -->
    <div class="section">
        <h2>Storage Statistics</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Image Count</th>
                    <th>Total Size</th>
                    <th>Deleted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($storageStats as $stat): ?>
                    <tr>
                        <td><?= htmlspecialchars($stat['username']) ?></td>
                        <td><?= htmlspecialchars($stat['role']) ?></td>
                        <td><?= number_format($stat['image_count']) ?></td>
                        <td><?= number_format(($stat['total_size'] ?? 0) / 1024 / 1024, 2) ?> MB</td>
                        <td><?= number_format($stat['deleted_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Global Gallery -->
    <div class="section">
        <h2>Global Gallery (<?= $totalImages ?> images)</h2>

        <div class="gallery">
            <?php foreach ($images as $image): ?>
                <div class="gallery-item">
                    <img src="data:image/<?= \App\Models\Image::isAudio($image) ? 'png' : 'jpeg' ?>;base64,<?= base64_encode($image['thumb_data']) ?>" alt="">
                    <div class="gallery-item-info">
                        <strong><?= htmlspecialchars($image['username']) ?></strong><br>
                        <a href="/v/<?= htmlspecialchars($image['slug']) ?>" target="_blank">View</a> |
                        <a href="#" onclick="deleteImage(<?= $image['id'] ?>); return false;">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="btn <?= $i === $page ? 'btn-danger' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    async function deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user? All their images will also be deleted.')) return;

        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/delete-user', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('Delete failed: ' + result.error);
            }
        } catch (error) {
            alert('Delete failed: ' + error.message);
        }
    }

    async function deleteImage(imageId) {
        if (!confirm('Are you sure you want to delete this image?')) return;

        const formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/delete', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('Delete failed: ' + result.error);
            }
        } catch (error) {
            alert('Delete failed: ' + error.message);
        }
    }
</script>

<?php include __DIR__ . '/edit_user_modal.php'; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
