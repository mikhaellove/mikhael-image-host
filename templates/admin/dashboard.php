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
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
        right: 88px;
        font-size: 16px;
    }

    .copy-link-btn {
        right: 48px;
        font-size: 16px;
    }

    .edit-btn {
        right: 8px;
        font-size: 16px;
    }

    .gallery-meta-badges {
        position: absolute;
        bottom: 50px;
        left: 8px;
        display: flex;
        gap: 4px;
        z-index: 10;
    }

    .slot-count-badge,
    .view-count-badge {
        background: rgba(0, 0, 0, 0.7);
        color: #fff;
        font-size: 12px;
        padding: 3px 8px;
        border-radius: 12px;
    }

    .pagination {
        margin-top: 30px;
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    /* Upload zone */
    .upload-zone {
        background: #fff;
        padding: 30px;
        border-radius: 4px;
        margin-bottom: 30px;
    }

    /* Upload tabs */
    .upload-tabs {
        display: flex;
        gap: 0;
        margin-bottom: 20px;
        border-bottom: 2px solid #ddd;
    }

    .upload-tab-btn {
        padding: 10px 20px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        color: #666;
        transition: all 0.15s;
    }

    .upload-tab-btn.active {
        color: #007bff;
        border-bottom-color: #007bff;
    }

    .upload-tab-btn:hover:not(.active) {
        color: #333;
    }

    .upload-tab-panel {
        display: none;
    }

    .upload-tab-panel.active {
        display: block;
    }

    /* Gallery slot tray */
    .slot-tray {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .slot-box {
        width: 110px;
        height: 110px;
        border: 2px dashed #ccc;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        background: #fafafa;
        transition: border-color 0.15s, background 0.15s;
        overflow: hidden;
        flex-shrink: 0;
    }

    .slot-box:hover {
        border-color: #007bff;
        background: #f0f7ff;
    }

    .slot-box.filled {
        border-style: solid;
        border-color: #28a745;
        cursor: default;
    }

    .slot-box.drag-over {
        border-color: #007bff;
        background: #e3f2fd;
    }

    .slot-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .slot-plus {
        font-size: 28px;
        color: #aaa;
        pointer-events: none;
    }

    .slot-remove {
        position: absolute;
        top: 2px;
        right: 2px;
        background: rgba(220, 53, 69, 0.85);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 5;
    }

    .slot-counter {
        font-size: 13px;
        color: #666;
        margin-bottom: 12px;
    }
</style>

<nav>
    <div class="container">
        <div>
            <strong>Project Vault</strong> -
            Welcome, <?= htmlspecialchars(!empty($user['name'] ?? null) ? $user['name'] : $user['username']) ?>
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
        <h2 style="margin-bottom: 16px;">Upload Media</h2>

        <!-- Tabs -->
        <div class="upload-tabs">
            <button class="upload-tab-btn active" onclick="switchTab('images', this)">📷 Images</button>
            <button class="upload-tab-btn" onclick="switchTab('other', this)">🎵 Other Media</button>
        </div>

        <!-- Images tab -->
        <div class="upload-tab-panel active" id="tab-images">
            <div class="slot-counter">
                <span id="slotCountLabel">0</span> / <?= $maxGalleryImages ?> images added
            </div>
            <div class="slot-tray" id="slotTray">
                <?php for ($i = 0; $i < $maxGalleryImages; $i++): ?>
                    <div class="slot-box" id="slot-<?= $i ?>" onclick="slotClick(<?= $i ?>)"
                         ondragover="slotDragOver(event, <?= $i ?>)"
                         ondragleave="slotDragLeave(event, <?= $i ?>)"
                         ondrop="slotDrop(event, <?= $i ?>)">
                        <span class="slot-plus">+</span>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="form-group">
                <label>Caption (optional)</label>
                <?php $prefix = 'gallery';
                include __DIR__ . '/wysiwyg_caption.php'; ?>
            </div>

            <input type="hidden" id="galleryCsrf" value="<?= Auth::generateCsrfToken() ?>">

            <button class="btn" id="saveGalleryBtn" onclick="saveGallery()" disabled>Save Gallery</button>
            <div id="galleryUploadStatus" style="margin-top: 10px;"></div>

            <!-- Hidden file input -->
            <input type="file" id="slotFileInput" accept="image/*" style="display:none" onchange="slotFileChosen(this)">
        </div>

        <!-- Other Media tab -->
        <div class="upload-tab-panel" id="tab-other">
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">
                <div class="form-group">
                    <label for="image">Select Audio or Video (or drag & drop / paste)</label>
                    <input type="file" id="image" name="image" accept="audio/*,video/*">
                </div>
                <div class="form-group">
                    <label>Caption (optional)</label>
                    <?php $prefix = 'upload';
                    include __DIR__ . '/wysiwyg_caption.php'; ?>
                </div>
                <button type="submit" class="btn">Upload</button>
                <div id="uploadStatus"></div>
            </form>
        </div>
    </div>

    <div style="background: #fff; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
        <h3>Storage Stats</h3>
        <p>Total Items: <?= number_format($storageStats['image_count']) ?></p>
        <p>Total Size: <?= number_format($storageStats['total_size'] / 1024 / 1024, 2) ?> MB</p>
        <p>Deleted: <?= number_format($storageStats['deleted_count']) ?></p>
    </div>

    <h2>Your Gallery (<?= $totalImages ?> items)</h2>

    <div class="gallery">
        <?php foreach ($images as $image): ?>

            <?php $isImageType = \App\Models\Image::isImage($image); ?>
            <?php $thumbMime = \App\Models\Image::isAudio($image) ? 'png' : 'jpeg'; ?>
            <?php $slotCount = (int)($image['slot_count'] ?? 1); ?>
            <div class="gallery-item" id="gallery-item-<?= $image['id'] ?>">
                <button class="image-action-btn share-btn" onclick="openShareSettings(<?= $image['id'] ?>)"
                        title="Share Settings">⚙️
                </button>
                <button class="image-action-btn copy-link-btn"
                        onclick="copyImageLink('<?= htmlspecialchars($image['slug']) ?>', event)" title="Copy Link">🔗
                </button>
                <?php if ($isImageType): ?>
                    <button class="image-action-btn edit-btn"
                            onclick="openGalleryEditor(<?= $image['id'] ?>, '<?= htmlspecialchars($image['slug']) ?>')"
                            title="Edit Gallery">✏️
                    </button>
                <?php endif; ?>
                <img id="img-<?= $image['id'] ?>"
                     src="data:image/<?= $thumbMime ?>;base64,<?= base64_encode($image['thumb_data']) ?>" alt="">
                <div class="gallery-meta-badges">
                    <?php if ($isImageType && $slotCount > 1): ?>
                        <div class="slot-count-badge"><?= $slotCount ?> photos</div>
                    <?php endif; ?>
                    <div class="view-count-badge"><?= $image['view_count'] ?> views</div>
                </div>
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
    // --- Tab switching ---
    function switchTab(name, btn) {
        document.querySelectorAll('.upload-tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.upload-tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // --- Slot tray state ---
    const maxSlots = <?= $maxGalleryImages ?>;
    const slotFiles = new Array(maxSlots).fill(null); // File objects per slot
    let pendingSlotIndex = null;

    function slotClick(index) {
        if (slotFiles[index] !== null) return; // filled, use remove btn
        pendingSlotIndex = index;
        document.getElementById('slotFileInput').value = '';
        document.getElementById('slotFileInput').click();
    }

    function slotFileChosen(input) {
        if (!input.files[0] || pendingSlotIndex === null) return;
        setSlotFile(pendingSlotIndex, input.files[0]);
        pendingSlotIndex = null;
    }

    function setSlotFile(index, file) {
        slotFiles[index] = file;
        const box = document.getElementById('slot-' + index);
        const reader = new FileReader();
        reader.onload = e => {
            box.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">
                <button class="slot-remove" onclick="removeSlot(event,${index})">✕</button>`;
            box.classList.add('filled');
        };
        reader.readAsDataURL(file);
        updateSlotCounter();
    }

    function removeSlot(event, index) {
        event.stopPropagation();
        slotFiles[index] = null;
        const box = document.getElementById('slot-' + index);
        box.innerHTML = '<span class="slot-plus">+</span>';
        box.classList.remove('filled');
        updateSlotCounter();
    }

    function updateSlotCounter() {
        const count = slotFiles.filter(f => f !== null).length;
        document.getElementById('slotCountLabel').textContent = count;
        document.getElementById('saveGalleryBtn').disabled = count === 0;
    }

    // Drag-and-drop onto slots
    function slotDragOver(e, index) {
        e.preventDefault();
        document.getElementById('slot-' + index).classList.add('drag-over');
    }

    function slotDragLeave(e, index) {
        document.getElementById('slot-' + index).classList.remove('drag-over');
    }

    function slotDrop(e, index) {
        e.preventDefault();
        document.getElementById('slot-' + index).classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        setSlotFile(index, file);
    }

    // Global paste: if Images tab active, fill next empty slot; otherwise use legacy upload
    document.addEventListener('paste', async (e) => {
        const items = e.clipboardData?.items;
        if (!items) return;

        const isImagesTab = document.getElementById('tab-images').classList.contains('active');

        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                const blob = items[i].getAsFile();

                if (isImagesTab) {
                    // Add to next empty slot
                    const nextEmpty = slotFiles.findIndex(f => f === null);
                    if (nextEmpty !== -1) {
                        setSlotFile(nextEmpty, blob);
                    }
                } else {
                    // Other Media tab: auto-upload (original behavior)
                    await uploadSingleFile(blob, 'pasted-image.png');
                }
                break;
            }
        }
    });

    // Drag-drop onto the whole zone routes by active tab
    const uploadZone = document.getElementById('uploadZone');

    uploadZone.addEventListener('dragover', (e) => e.preventDefault());

    uploadZone.addEventListener('drop', async (e) => {
        e.preventDefault();
        const files = e.dataTransfer.files;
        if (!files || files.length === 0) return;

        const isImagesTab = document.getElementById('tab-images').classList.contains('active');

        if (isImagesTab) {
            let slotIdx = 0;
            for (let i = 0; i < files.length; i++) {
                if (!files[i].type.startsWith('image/')) continue;
                // Find next empty slot from slotIdx onwards
                while (slotIdx < maxSlots && slotFiles[slotIdx] !== null) slotIdx++;
                if (slotIdx >= maxSlots) break;
                setSlotFile(slotIdx, files[i]);
                slotIdx++;
            }
        } else {
            const file = files[0];
            if (file) await uploadSingleFile(file, file.name);
        }
    });

    async function saveGallery() {
        const filled = slotFiles.filter(f => f !== null);
        if (filled.length === 0) return;

        const btn = document.getElementById('saveGalleryBtn');
        const statusDiv = document.getElementById('galleryUploadStatus');
        btn.disabled = true;
        statusDiv.innerHTML = '<div style="color:#007bff;">Processing and uploading...</div>';

        const formData = new FormData();
        formData.append('csrf_token', document.getElementById('galleryCsrf').value);

        let idx = 0;
        for (const file of filled) {
            formData.append('images[]', file, file.name || 'image.jpg');
            idx++;
        }

        const capEl = document.getElementById('galleryCaptionHidden');
        if (capEl && capEl.value.trim()) formData.append('caption', capEl.value.trim());

        try {
            const response = await fetch('/admin/gallery-upload', {method: 'POST', body: formData});
            const result = await response.json();

            if (result.success) {
                statusDiv.innerHTML = `<div class="success">Gallery saved! <a href="${result.data.url}" target="_blank">View</a></div>`;
                setTimeout(() => location.reload(), 1500);
            } else {
                statusDiv.innerHTML = `<div class="error">${result.error}</div>`;
                btn.disabled = false;
            }
        } catch (error) {
            statusDiv.innerHTML = `<div class="error">Upload failed: ${error.message}</div>`;
            btn.disabled = false;
        }
    }

    // --- Other Media form ---
    document.getElementById('uploadForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const statusDiv = document.getElementById('uploadStatus');
        statusDiv.innerHTML = 'Uploading...';

        try {
            const response = await fetch('/admin/upload', {method: 'POST', body: formData});
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
        if (!confirm('Are you sure you want to delete this item?')) return;

        const formData = new FormData();
        formData.append('image_id', imageId);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/delete', {method: 'POST', body: formData});
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

    async function copyImageLink(slug, event) {
        const btn = event.target;
        if (btn.classList.contains('copying')) return;
        const url = window.location.protocol + '//' + window.location.host + '/v/' + slug;
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.left = '-999999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        btn.classList.add('copying');
        btn.innerHTML = '✓';
        setTimeout(() => {
            btn.classList.remove('copying');
            btn.innerHTML = '🔗';
        }, 1500);
    }

    async function uploadSingleFile(file, filename) {
        const statusDiv = document.getElementById('uploadStatus');
        const formData = new FormData();
        formData.append('image', file, filename);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');
        try {
            const response = await fetch('/admin/upload', {method: 'POST', body: formData});
            const result = await response.json();
            if (result.success) {
                statusDiv.innerHTML = `<div class="success">✓ Upload successful! <a href="${result.data.url}" target="_blank">View</a></div>`;
                setTimeout(() => location.reload(), 1500);
            } else {
                statusDiv.innerHTML = `<div class="error">✗ ${result.error}</div>`;
            }
        } catch (error) {
            statusDiv.innerHTML = `<div class="error">✗ Upload failed: ${error.message}</div>`;
        }
    }

    // Initialize WYSIWYG caption for gallery tab
    if (window.initWysiwygCaption) initWysiwygCaption('gallery', '');
    if (window.initWysiwygCaption) initWysiwygCaption('upload', '');
</script>

<?php include __DIR__ . '/share_settings_modal.php'; ?>
<?php include __DIR__ . '/editor_modal.php'; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
