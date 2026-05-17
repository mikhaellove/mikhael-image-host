<?php
use App\Core\Auth;
$pageTitle = 'Dashboard';
include __DIR__ . '/../layout/header.php';
$user = Auth::getUser();
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
        position: relative;
    }

    .gallery-item img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        display: block;
    }

    .gallery-item-info {
        padding: 10px;
    }

    .image-action-btn {
        position: absolute;
        top: 8px;
        background: rgba(0, 0, 0, 0.6);
        color: white;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: background 0.2s;
        z-index: 10;
    }

    .image-action-btn:hover {
        background: rgba(0, 0, 0, 0.8);
    }

    .image-action-btn.rotating,
    .image-action-btn.copying {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .share-btn {
        right: 128px;
        font-size: 16px;
    }

    .copy-link-btn {
        right: 88px;
        font-size: 16px;
    }

    .edit-btn {
        right: 48px;
        font-size: 16px;
    }

    .rotate-btn {
        right: 8px;
    }

    .pagination {
        margin-top: 30px;
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .upload-zone {
        background: #fff;
        padding: 30px;
        border-radius: 4px;
        margin-bottom: 30px;
        position: relative;
        transition: all 0.3s ease;
    }

    .upload-zone.drag-over {
        background: #e3f2fd;
        border: 3px dashed #2196f3;
        transform: scale(1.02);
    }

    .upload-zone .drag-overlay {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(33, 150, 243, 0.1);
        border-radius: 4px;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #2196f3;
        font-weight: bold;
        pointer-events: none;
    }

    .upload-zone.drag-over .drag-overlay {
        display: flex;
    }

</style>

<nav>
    <div class="container">
        <div>
            <strong>Project Vault</strong> - Welcome, <?= htmlspecialchars(!empty($user['name'] ?? null) ? $user['name'] : $user['username']) ?>
        </div>
        <div>
            <?php if (Auth::isAdmin()): ?>
                <a href="/admin/landing-pages" class="btn">Landing Pages</a>
                <a href="/admin/manage" class="btn">Manage</a>
            <?php endif; ?>
            <a href="/admin/logout" class="btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="upload-zone" id="uploadZone">
        <div class="drag-overlay">📁 Drop files here to upload</div>
        <h2>Upload Media</h2>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">

            <div class="form-group">
                <label for="image">Select Image or Audio (or drag & drop / paste)</label>
                <input type="file" id="image" name="image" accept="image/*,audio/*" multiple>
            </div>

            <div class="form-group">
                <label>Caption (optional)</label>
                <?php $prefix = 'upload'; include __DIR__ . '/wysiwyg_caption.php'; ?>
            </div>

            <button type="submit" class="btn">Upload</button>
            <div id="uploadStatus"></div>
        </form>
        <script>initWysiwygCaption('upload', '');</script>
    </div>

    <div style="background: #fff; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
        <h3>Storage Stats</h3>
        <p>Total Images: <?= number_format($storageStats['image_count']) ?></p>
        <p>Total Size: <?= number_format($storageStats['total_size'] / 1024 / 1024, 2) ?> MB</p>
        <p>Deleted: <?= number_format($storageStats['deleted_count']) ?></p>
    </div>

    <h2>Your Gallery (<?= $totalImages ?> items)</h2>

    <div class="gallery">
        <?php foreach ($images as $image): ?>
            <?php $isImageOnly = \App\Models\Image::isImage($image); ?>
            <?php $thumbMime = \App\Models\Image::isAudio($image) ? 'png' : 'jpeg'; ?>
            <div class="gallery-item" id="gallery-item-<?= $image['id'] ?>">
                <button class="image-action-btn share-btn" onclick="openShareSettings(<?= $image['id'] ?>)" title="Share Settings">⚙️</button>
                <button class="image-action-btn copy-link-btn" onclick="copyImageLink('<?= htmlspecialchars($image['slug']) ?>', event)" title="Copy Link">🔗</button>
                <?php if ($isImageOnly): ?>
                    <button class="image-action-btn edit-btn" onclick="openImageEditor(<?= $image['id'] ?>, '<?= htmlspecialchars($image['slug']) ?>')" title="Edit Image">✏️</button>
                    <button class="image-action-btn rotate-btn" onclick="rotateImage(<?= $image['id'] ?>)" title="Rotate 90° clockwise">↻</button>
                <?php endif; ?>
                <img id="img-<?= $image['id'] ?>" src="data:image/<?= $thumbMime ?>;base64,<?= base64_encode($image['thumb_data']) ?>" alt="">
                <div class="gallery-item-info">
                    <a href="/v/<?= htmlspecialchars($image['slug']) ?>" target="_blank">View</a> |
                    <a href="#" onclick="deleteImage(<?= $image['id'] ?>); return false;">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="btn <?= $i === $page ? 'btn-danger' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('uploadForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const statusDiv = document.getElementById('uploadStatus');

        statusDiv.innerHTML = 'Uploading...';

        try {
            const response = await fetch('/admin/upload', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.innerHTML = `<div class="success">Upload successful! <a href="${result.data.url}" target="_blank">View</a></div>`;
                setTimeout(() => location.reload(), 1500);
            } else {
                statusDiv.innerHTML = `<div class="error">${result.error}</div>`;
            }
        } catch (error) {
            statusDiv.innerHTML = `<div class="error">Upload failed: ${error.message}</div>`;
        }
    });

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

    async function rotateImage(imageId) {
        const btn = event.target;
        const img = document.getElementById('img-' + imageId);

        // Prevent multiple clicks
        if (btn.classList.contains('rotating')) return;

        btn.classList.add('rotating');
        btn.innerHTML = '⟳';

        const formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/rotate-image', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Update thumbnail with new rotated image
                img.src = 'data:image/jpeg;base64,' + result.thumbnail;
                btn.classList.remove('rotating');
                btn.innerHTML = '↻';
            } else {
                alert('Rotation failed: ' + result.error);
                btn.classList.remove('rotating');
                btn.innerHTML = '↻';
            }
        } catch (error) {
            alert('Rotation failed: ' + error.message);
            btn.classList.remove('rotating');
            btn.innerHTML = '↻';
        }
    }

    async function copyImageLink(slug, event) {
        const btn = event.target;
        const originalContent = btn.innerHTML;

        // Prevent multiple clicks
        if (btn.classList.contains('copying')) return;

        const url = window.location.protocol + '//' + window.location.host + '/v/' + slug;

        try {
            await navigator.clipboard.writeText(url);

            // Show success feedback
            btn.classList.add('copying');
            btn.innerHTML = '✓';

            setTimeout(() => {
                btn.classList.remove('copying');
                btn.innerHTML = originalContent;
            }, 1500);
        } catch (error) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = url;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                btn.classList.add('copying');
                btn.innerHTML = '✓';

                setTimeout(() => {
                    btn.classList.remove('copying');
                    btn.innerHTML = originalContent;
                }, 1500);
            } catch (err) {
                alert('Failed to copy link: ' + err.message);
            }

            document.body.removeChild(textArea);
        }
    }

    // Paste from clipboard support
    document.addEventListener('paste', async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;

        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();

                const blob = items[i].getAsFile();
                await uploadSingleFile(blob, 'pasted-image.png');

                break; // Only handle first image
            }
        }
    });

    // Drag and drop support
    const uploadZone = document.getElementById('uploadZone');

    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('drag-over');
    });

    uploadZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('drag-over');
    });

    uploadZone.addEventListener('drop', async (e) => {
        e.preventDefault();
        uploadZone.classList.remove('drag-over');

        const files = e.dataTransfer.files;
        if (!files || files.length === 0) return;

        const statusDiv = document.getElementById('uploadStatus');
        statusDiv.innerHTML = `<div style="color: #007bff;">📤 Uploading ${files.length} file(s)...</div>`;

        // Upload files sequentially
        for (let i = 0; i < files.length; i++) {
            const file = files[i];

            // Check if it's an image
            if (!file.type.startsWith('image/')) {
                statusDiv.innerHTML = `<div class="error">✗ Skipped non-image file: ${file.name}</div>`;
                continue;
            }

            await uploadSingleFile(file, file.name);

            // Show progress
            statusDiv.innerHTML = `<div style="color: #007bff;">📤 Uploaded ${i + 1} of ${files.length}...</div>`;
        }

        // Reload after all uploads complete
        setTimeout(() => location.reload(), 1000);
    });

    // Helper function to upload a single file
    async function uploadSingleFile(file, filename) {
        const statusDiv = document.getElementById('uploadStatus');
        const formData = new FormData();
        formData.append('image', file, filename);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/upload', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.innerHTML = `<div class="success">✓ Upload successful! <a href="${result.data.url}" target="_blank">View</a></div>`;
                return true;
            } else {
                statusDiv.innerHTML = `<div class="error">✗ ${result.error}</div>`;
                return false;
            }
        } catch (error) {
            statusDiv.innerHTML = `<div class="error">✗ Upload failed: ${error.message}</div>`;
            return false;
        }
    }
</script>

<?php include __DIR__ . '/share_settings_modal.php'; ?>
<?php include __DIR__ . '/editor_modal.php'; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
