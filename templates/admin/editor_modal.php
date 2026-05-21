<!-- Image Editor Modal -->
<div id="imageEditorModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #000; z-index: 100000;">
    <div style="height: 100%; display: flex; flex-direction: column;">
        <!-- Header -->
        <div style="background: #1a1a1a; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333;">
            <h2 style="margin: 0; color: #fff;">Image Editor</h2>
            <div style="display: flex; gap: 10px;">
                <button onclick="applyImageEdits()" class="btn" style="background: #28a745;">Apply Changes</button>
                <button onclick="closeImageEditor()" class="btn" style="background: #6c757d;">Cancel</button>
            </div>
        </div>

        <!-- Main Content -->
        <div style="flex: 1; display: flex; overflow: hidden;">
            <!-- Sidebar Controls -->
            <div style="width: 300px; background: #1a1a1a; padding: 20px; overflow-y: auto; border-right: 1px solid #333;">
                <input type="hidden" id="editorImageId">

                <!-- Crop Tool -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #fff; margin-bottom: 15px;">Crop</h3>

                    <label style="color: #aaa; display: block; margin-bottom: 5px;">Aspect Ratio</label>
                    <select id="cropAspectRatio" style="width: 100%; padding: 8px; background: #2a2a2a; color: #fff; border: 1px solid #444; border-radius: 4px; margin-bottom: 15px;">
                        <option value="free">Free</option>
                        <option value="1:1">Square (1:1)</option>
                        <option value="4:3">4:3</option>
                        <option value="16:9">16:9</option>
                        <option value="original">Original</option>
                    </select>

                    <button onclick="enableCropMode()" class="btn" style="width: 100%; background: #007bff;">Enable Crop</button>
                    <button onclick="clearCrop()" class="btn" style="width: 100%; background: #dc3545; margin-top: 10px;">Clear Crop</button>
                </div>

                <!-- Brightness -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #fff; margin-bottom: 15px;">Brightness</h3>
                    <input type="range" id="brightnessSlider" min="-100" max="100" value="0" style="width: 100%;">
                    <div style="color: #aaa; text-align: center; margin-top: 5px;">
                        <span id="brightnessValue">0</span>
                    </div>
                </div>

                <!-- Contrast -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #fff; margin-bottom: 15px;">Contrast</h3>
                    <input type="range" id="contrastSlider" min="-100" max="100" value="0" style="width: 100%;">
                    <div style="color: #aaa; text-align: center; margin-top: 5px;">
                        <span id="contrastValue">0</span>
                    </div>
                </div>

                <!-- Filters -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: #fff; margin-bottom: 15px;">Filters</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button onclick="setFilter('original')" class="filter-btn active" data-filter="original">Original</button>
                        <button onclick="setFilter('bw')" class="filter-btn" data-filter="bw">B&W</button>
                        <button onclick="setFilter('sepia')" class="filter-btn" data-filter="sepia">Sepia</button>
                        <button onclick="setFilter('vintage')" class="filter-btn" data-filter="vintage">Vintage</button>
                        <button onclick="setFilter('warm')" class="filter-btn" data-filter="warm">Warm</button>
                    </div>
                </div>

                <!-- Reset -->
                <button onclick="resetAllEdits()" class="btn" style="width: 100%; background: #ffc107; color: #000; margin-bottom: 10px;">Reset Preview</button>
                <button onclick="revertToOriginal()" class="btn" style="width: 100%; background: #dc3545; color: #fff;">Revert to Original</button>
            </div>

            <!-- Image Preview Area -->
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 20px; position: relative; overflow: hidden;" id="imagePreviewContainer">
                <canvas id="editorCanvas" style="max-width: 100%; max-height: 100%; cursor: crosshair;"></canvas>
                <div id="cropOverlay" style="display: none; position: absolute; border: 2px dashed #fff; background: rgba(255,255,255,0.1); cursor: move;"></div>
            </div>
        </div>

        <!-- Status Bar -->
        <div style="background: #1a1a1a; padding: 10px 20px; border-top: 1px solid #333; color: #aaa;" id="editorStatus">
            Ready
        </div>
    </div>
</div>

<style>
    .filter-btn {
        padding: 10px;
        background: #2a2a2a;
        color: #fff;
        border: 2px solid #444;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-btn:hover {
        background: #3a3a3a;
        border-color: #666;
    }

    .filter-btn.active {
        background: #007bff;
        border-color: #007bff;
    }

    #brightnessSlider,
    #contrastSlider {
        -webkit-appearance: none;
        appearance: none;
        height: 6px;
        background: #444;
        border-radius: 3px;
        outline: none;
    }

    #brightnessSlider::-webkit-slider-thumb,
    #contrastSlider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        background: #007bff;
        border-radius: 50%;
        cursor: pointer;
    }

    #brightnessSlider::-moz-range-thumb,
    #contrastSlider::-moz-range-thumb {
        width: 20px;
        height: 20px;
        background: #007bff;
        border-radius: 50%;
        cursor: pointer;
        border: none;
    }
</style>

<script>
    let editorState = {
        imageId: null,
        slug: null,
        slotIndex: null,
        originalImage: null,
        currentFilter: 'original',
        brightness: 0,
        contrast: 0,
        cropData: null,
        canvas: null,
        ctx: null,
        cropMode: false,
        cropStart: null,
        cropEnd: null
    };

    async function openImageEditor(imageId, slug, slotIndex = null) {
        editorState.imageId = imageId;
        editorState.slug = slug;
        editorState.slotIndex = slotIndex;
        document.getElementById('editorImageId').value = imageId;
        document.getElementById('imageEditorModal').style.display = 'block';

        // Initialize canvas
        editorState.canvas = document.getElementById('editorCanvas');
        editorState.ctx = editorState.canvas.getContext('2d');

        // Load image from slot-aware URL
        const imgSrc = slotIndex !== null ? `/raw/${slug}/${slotIndex}` : `/raw/${slug}`;
        await loadEditorImage(imgSrc);

        document.getElementById('editorStatus').textContent = 'Image loaded. Make your edits and click Apply Changes.';
    }

    function closeImageEditor() {
        document.getElementById('imageEditorModal').style.display = 'none';
        resetAllEdits();
    }

    async function loadEditorImage(imageSrc) {
        try {

            const image = new Image();
            image.onload = function() {
                editorState.originalImage = image;
                renderCanvas();
            };
            image.onerror = function() {
                document.getElementById('editorStatus').textContent = 'Error loading master image';
            };
            image.src = imageSrc;

        } catch (error) {
            document.getElementById('editorStatus').textContent = 'Error loading image: ' + error.message;
        }
    }

    function renderCanvas() {
        if (!editorState.originalImage) return;

        const canvas = editorState.canvas;
        const ctx = editorState.ctx;
        const img = editorState.originalImage;

        canvas.width = img.width;
        canvas.height = img.height;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw image
        ctx.filter = getFilterCSS();
        ctx.drawImage(img, 0, 0);
        ctx.filter = 'none';

        // Draw crop overlay if in crop mode
        if (editorState.cropMode && editorState.cropStart && editorState.cropEnd) {
            const x = Math.min(editorState.cropStart.x, editorState.cropEnd.x);
            const y = Math.min(editorState.cropStart.y, editorState.cropEnd.y);
            const width = Math.abs(editorState.cropEnd.x - editorState.cropStart.x);
            const height = Math.abs(editorState.cropEnd.y - editorState.cropStart.y);

            // Darken outside crop area
            ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
            ctx.fillRect(0, 0, canvas.width, y); // Top
            ctx.fillRect(0, y, x, height); // Left
            ctx.fillRect(x + width, y, canvas.width - (x + width), height); // Right
            ctx.fillRect(0, y + height, canvas.width, canvas.height - (y + height)); // Bottom

            // Draw crop border
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.strokeRect(x, y, width, height);

            // Draw corner handles
            const handleSize = 10;
            ctx.fillStyle = '#fff';
            ctx.fillRect(x - handleSize/2, y - handleSize/2, handleSize, handleSize); // Top-left
            ctx.fillRect(x + width - handleSize/2, y - handleSize/2, handleSize, handleSize); // Top-right
            ctx.fillRect(x - handleSize/2, y + height - handleSize/2, handleSize, handleSize); // Bottom-left
            ctx.fillRect(x + width - handleSize/2, y + height - handleSize/2, handleSize, handleSize); // Bottom-right
        }
    }

    function getFilterCSS() {
        let filters = [];

        // Brightness
        if (editorState.brightness !== 0) {
            const brightnessPercent = 100 + editorState.brightness;
            filters.push(`brightness(${brightnessPercent}%)`);
        }

        // Contrast
        if (editorState.contrast !== 0) {
            const contrastPercent = 100 + editorState.contrast;
            filters.push(`contrast(${contrastPercent}%)`);
        }

        // Filters
        switch (editorState.currentFilter) {
            case 'bw':
                filters.push('grayscale(100%)');
                break;
            case 'sepia':
                filters.push('sepia(100%)');
                break;
            case 'vintage':
                filters.push('saturate(70%) hue-rotate(-10deg)');
                break;
            case 'warm':
                filters.push('sepia(30%) saturate(120%)');
                break;
        }

        return filters.length > 0 ? filters.join(' ') : 'none';
    }

    function setFilter(filter) {
        editorState.currentFilter = filter;

        // Update button states
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.filter === filter) {
                btn.classList.add('active');
            }
        });

        renderCanvas();
    }

    // Brightness slider
    document.getElementById('brightnessSlider').addEventListener('input', function() {
        editorState.brightness = parseInt(this.value);
        document.getElementById('brightnessValue').textContent = this.value;
        renderCanvas();
    });

    // Contrast slider
    document.getElementById('contrastSlider').addEventListener('input', function() {
        editorState.contrast = parseInt(this.value);
        document.getElementById('contrastValue').textContent = this.value;
        renderCanvas();
    });

    // Canvas mouse events for crop tool
    let isDragging = false;

    document.getElementById('editorCanvas').addEventListener('mousedown', function(e) {
        if (!editorState.cropMode) return;

        const rect = this.getBoundingClientRect();
        const scaleX = editorState.canvas.width / rect.width;
        const scaleY = editorState.canvas.height / rect.height;

        editorState.cropStart = {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
        editorState.cropEnd = { ...editorState.cropStart };
        isDragging = true;
    });

    document.getElementById('editorCanvas').addEventListener('mousemove', function(e) {
        if (!editorState.cropMode || !isDragging) return;

        const rect = this.getBoundingClientRect();
        const scaleX = editorState.canvas.width / rect.width;
        const scaleY = editorState.canvas.height / rect.height;

        editorState.cropEnd = {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };

        // Apply aspect ratio constraint if selected
        const aspectRatio = document.getElementById('cropAspectRatio').value;
        if (aspectRatio !== 'free') {
            const width = Math.abs(editorState.cropEnd.x - editorState.cropStart.x);
            let ratio;

            switch(aspectRatio) {
                case '1:1': ratio = 1; break;
                case '4:3': ratio = 4/3; break;
                case '16:9': ratio = 16/9; break;
                case 'original':
                    ratio = editorState.originalImage.width / editorState.originalImage.height;
                    break;
            }

            if (ratio) {
                const height = width / ratio;
                const direction = editorState.cropEnd.y > editorState.cropStart.y ? 1 : -1;
                editorState.cropEnd.y = editorState.cropStart.y + (height * direction);
            }
        }

        renderCanvas();
    });

    document.getElementById('editorCanvas').addEventListener('mouseup', function() {
        if (!editorState.cropMode || !isDragging) return;

        isDragging = false;

        // Calculate final crop data
        const x = Math.min(editorState.cropStart.x, editorState.cropEnd.x);
        const y = Math.min(editorState.cropStart.y, editorState.cropEnd.y);
        const width = Math.abs(editorState.cropEnd.x - editorState.cropStart.x);
        const height = Math.abs(editorState.cropEnd.y - editorState.cropStart.y);

        if (width > 10 && height > 10) {
            editorState.cropData = {
                x: Math.round(x),
                y: Math.round(y),
                width: Math.round(width),
                height: Math.round(height)
            };
            document.getElementById('editorStatus').textContent = `Crop selected: ${Math.round(width)}x${Math.round(height)}`;
        }
    });

    document.getElementById('editorCanvas').addEventListener('mouseleave', function() {
        isDragging = false;
    });

    function enableCropMode() {
        editorState.cropMode = true;
        editorState.cropStart = null;
        editorState.cropEnd = null;
        document.getElementById('editorCanvas').style.cursor = 'crosshair';
        document.getElementById('editorStatus').textContent = 'Click and drag to select crop area';
    }

    function clearCrop() {
        editorState.cropMode = false;
        editorState.cropData = null;
        editorState.cropStart = null;
        editorState.cropEnd = null;
        document.getElementById('editorCanvas').style.cursor = 'default';
        renderCanvas();
        document.getElementById('editorStatus').textContent = 'Crop cleared';
    }

    function resetAllEdits() {
        editorState.brightness = 0;
        editorState.contrast = 0;
        editorState.currentFilter = 'original';
        editorState.cropData = null;

        document.getElementById('brightnessSlider').value = 0;
        document.getElementById('brightnessValue').textContent = '0';
        document.getElementById('contrastSlider').value = 0;
        document.getElementById('contrastValue').textContent = '0';

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector('[data-filter="original"]').classList.add('active');

        if (editorState.originalImage) {
            renderCanvas();
        }
    }

    async function applyImageEdits() {
        const statusDiv = document.getElementById('editorStatus');
        statusDiv.textContent = 'Applying changes...';

        const formData = new FormData();
        formData.append('image_id', editorState.imageId);
        formData.append('brightness', editorState.brightness);
        formData.append('contrast', editorState.contrast);
        formData.append('filter', editorState.currentFilter);

        if (editorState.cropData) {
            formData.append('crop', JSON.stringify(editorState.cropData));
        }

        if (editorState.slotIndex !== null) {
            formData.append('slot_index', editorState.slotIndex);
        }

        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/edit-image', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                statusDiv.textContent = 'Changes applied successfully!';
                setTimeout(() => {
                    closeImageEditor();
                    // If called from gallery editor, reload gallery editor; otherwise reload page
                    if (galleryEditorState.isOpen) {
                        openGalleryEditor(galleryEditorState.imageId, galleryEditorState.slug);
                    } else {
                        location.reload();
                    }
                }, 800);
            } else {
                statusDiv.textContent = 'Error: ' + result.error;
            }
        } catch (error) {
            statusDiv.textContent = 'Error applying changes: ' + error.message;
        }
    }

    async function revertToOriginal() {
        const statusDiv = document.getElementById('editorStatus');

        if (!confirm('This will permanently revert the image to its original unedited version. Continue?')) {
            return;
        }

        statusDiv.textContent = 'Reverting to original...';

        const formData = new FormData();
        formData.append('image_id', editorState.imageId);
        if (editorState.slotIndex !== null) {
            formData.append('slot_index', editorState.slotIndex);
        }
        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/revert-to-original', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                statusDiv.textContent = 'Reverted to original successfully!';
                setTimeout(() => {
                    closeImageEditor();
                    if (galleryEditorState.isOpen) {
                        openGalleryEditor(galleryEditorState.imageId, galleryEditorState.slug);
                    } else {
                        location.reload();
                    }
                }, 800);
            } else {
                statusDiv.textContent = 'Error: ' + result.error;
            }
        } catch (error) {
            statusDiv.textContent = 'Error reverting: ' + error.message;
        }
    }
</script>

<!-- Gallery Editor Modal -->
<div id="galleryEditorModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:#000; z-index:2000;">
    <div style="height:100%; display:flex; flex-direction:column;">
        <!-- Header -->
        <div style="background:#1a1a1a; padding:15px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #333; flex-shrink:0;">
            <div style="display:flex; align-items:center; gap:15px;">
                <h2 style="margin:0; color:#fff;">Gallery Editor</h2>
                <span id="gallerySlotCountBadge" style="color:#aaa; font-size:14px;"></span>
            </div>
            <div style="display:flex; gap:10px;">
                <button onclick="saveGalleryEdits()" class="btn" style="background:#28a745;">Save</button>
                <button onclick="closeGalleryEditor()" class="btn" style="background:#6c757d;">Cancel</button>
            </div>
        </div>

        <!-- Slot strip -->
        <div style="background:#111; padding:20px; flex-shrink:0; overflow-x:auto; border-bottom:1px solid #333;">
            <div id="gallerySlotStrip" style="display:flex; gap:12px; align-items:flex-start; min-height:160px;"></div>
        </div>

        <!-- Caption -->
        <div style="background:#1a1a1a; padding:15px 20px; flex-shrink:0; border-bottom:1px solid #333;">
            <label style="color:#aaa; font-size:13px; display:block; margin-bottom:6px;">Caption (optional)</label>
            <textarea id="galleryEditorCaption" style="width:100%; background:#2a2a2a; color:#fff; border:1px solid #444; border-radius:4px; padding:8px; font-size:14px; resize:vertical; min-height:60px;"></textarea>
        </div>

        <!-- Status -->
        <div style="background:#1a1a1a; padding:10px 20px; color:#aaa; flex-shrink:0;" id="galleryEditorStatus">Ready</div>
    </div>
</div>

<style>
    .gallery-slot-card {
        position: relative;
        flex-shrink: 0;
        cursor: grab;
        user-select: none;
    }

    .gallery-slot-card:active { cursor: grabbing; }

    .gallery-slot-card.drag-over { outline: 2px dashed #fff; }

    .gallery-slot-card img {
        width: 120px;
        height: 120px;
        object-fit: cover;
        display: block;
        border-radius: 4px;
    }

    .gallery-slot-actions {
        display: flex;
        gap: 4px;
        margin-top: 6px;
        justify-content: center;
    }

    .gallery-slot-actions button {
        background: #2a2a2a;
        color: #fff;
        border: 1px solid #444;
        border-radius: 4px;
        padding: 4px 8px;
        cursor: pointer;
        font-size: 13px;
        transition: background 0.15s;
    }

    .gallery-slot-actions button:hover { background: #3a3a3a; }

    .gallery-add-btn {
        width: 120px;
        height: 120px;
        border: 2px dashed #555;
        border-radius: 4px;
        background: #1e1e1e;
        color: #777;
        font-size: 32px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: border-color 0.15s, color 0.15s;
    }

    .gallery-add-btn:hover { border-color: #28a745; color: #28a745; }
</style>

<script>
    let galleryEditorState = {
        isOpen: false,
        imageId: null,
        slug: null,
        slots: [],        // array of slot objects fetched from server
        maxSlots: <?= (int)\App\Models\Setting::get('max_gallery_images', 5) ?>,
        dragSrcIndex: null,
    };

    let addSlotFileInput = null;

    function ensureAddSlotInput() {
        if (!addSlotFileInput) {
            addSlotFileInput = document.createElement('input');
            addSlotFileInput.type = 'file';
            addSlotFileInput.accept = 'image/*';
            addSlotFileInput.style.display = 'none';
            addSlotFileInput.addEventListener('change', async function() {
                if (!this.files[0]) return;
                await processAndAddSlot(this.files[0]);
            });
            document.body.appendChild(addSlotFileInput);
        }
        return addSlotFileInput;
    }

    async function openGalleryEditor(imageId, slug) {
        galleryEditorState.imageId = imageId;
        galleryEditorState.slug = slug;
        galleryEditorState.isOpen = true;
        galleryEditorState.slots = [];

        document.getElementById('galleryEditorStatus').textContent = 'Loading...';
        document.getElementById('galleryEditorModal').style.display = 'block';

        // Fetch current slots from a lightweight endpoint
        try {
            const resp = await fetch('/admin/gallery-get-slots?image_id=' + imageId);
            const data = await resp.json();
            if (data.success) {
                galleryEditorState.slots = data.slots;
                galleryEditorState.maxSlots = data.max_slots;
                document.getElementById('galleryEditorCaption').value = data.caption || '';
                renderGallerySlotStrip();
                document.getElementById('galleryEditorStatus').textContent = 'Ready';
            } else {
                document.getElementById('galleryEditorStatus').textContent = 'Error: ' + data.error;
            }
        } catch (e) {
            document.getElementById('galleryEditorStatus').textContent = 'Failed to load gallery data.';
        }
    }

    function closeGalleryEditor() {
        document.getElementById('galleryEditorModal').style.display = 'none';
        galleryEditorState.isOpen = false;
        galleryEditorState.slots = [];
    }

    function renderGallerySlotStrip() {
        const strip = document.getElementById('gallerySlotStrip');
        const slots = galleryEditorState.slots;
        strip.innerHTML = '';

        document.getElementById('gallerySlotCountBadge').textContent =
            slots.length + ' / ' + galleryEditorState.maxSlots + ' images';

        slots.forEach((slot, i) => {
            const card = document.createElement('div');
            card.className = 'gallery-slot-card';
            card.draggable = true;
            card.dataset.index = i;

            card.innerHTML = `
                <img src="data:image/jpeg;base64,${slot.thumb}" alt="Slot ${i+1}">
                <div class="gallery-slot-actions">
                    <button onclick="galleryEditSlotCanvas(${i})" title="Edit">✏️</button>
                    <button onclick="galleryRotateSlot(${i})" title="Rotate">↻</button>
                    <button onclick="galleryRemoveSlot(${i})" title="Remove" style="background:#6c2a2a;">✕</button>
                </div>`;

            card.addEventListener('dragstart', e => {
                galleryEditorState.dragSrcIndex = i;
                e.dataTransfer.effectAllowed = 'move';
            });
            card.addEventListener('dragover', e => {
                e.preventDefault();
                card.classList.add('drag-over');
            });
            card.addEventListener('dragleave', () => card.classList.remove('drag-over'));
            card.addEventListener('drop', e => {
                e.preventDefault();
                card.classList.remove('drag-over');
                const src = galleryEditorState.dragSrcIndex;
                if (src === null || src === i) return;
                const arr = galleryEditorState.slots;
                const [moved] = arr.splice(src, 1);
                arr.splice(i, 0, moved);
                galleryEditorState.dragSrcIndex = null;
                renderGallerySlotStrip();
            });

            strip.appendChild(card);
        });

        // Add button
        if (slots.length < galleryEditorState.maxSlots) {
            const addBtn = document.createElement('button');
            addBtn.className = 'gallery-add-btn';
            addBtn.textContent = '+';
            addBtn.title = 'Add image';
            addBtn.onclick = () => {
                const inp = ensureAddSlotInput();
                inp.value = '';
                inp.click();
            };
            strip.appendChild(addBtn);
        }
    }

    function galleryEditSlotCanvas(slotIndex) {
        openImageEditor(galleryEditorState.imageId, galleryEditorState.slug, slotIndex);
    }

    async function galleryRotateSlot(slotIndex) {
        document.getElementById('galleryEditorStatus').textContent = 'Rotating...';
        const formData = new FormData();
        formData.append('image_id', galleryEditorState.imageId);
        formData.append('slot_index', slotIndex);
        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/rotate-image', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                // Reload the gallery editor to pick up new thumbnail
                openGalleryEditor(galleryEditorState.imageId, galleryEditorState.slug);
            } else {
                document.getElementById('galleryEditorStatus').textContent = 'Error: ' + result.error;
            }
        } catch (e) {
            document.getElementById('galleryEditorStatus').textContent = 'Rotation failed.';
        }
    }

    function galleryRemoveSlot(slotIndex) {
        if (galleryEditorState.slots.length <= 1) {
            alert('A gallery must have at least one image.');
            return;
        }
        galleryEditorState.slots.splice(slotIndex, 1);
        renderGallerySlotStrip();
    }

    async function processAndAddSlot(file) {
        document.getElementById('galleryEditorStatus').textContent = 'Processing new image...';
        const formData = new FormData();
        formData.append('image', file, file.name || 'image.jpg');
        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/process-gallery-image', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                galleryEditorState.slots.push(result.slot);
                renderGallerySlotStrip();
                document.getElementById('galleryEditorStatus').textContent = 'Image added. Click Save to keep changes.';
            } else {
                document.getElementById('galleryEditorStatus').textContent = 'Error: ' + result.error;
            }
        } catch (e) {
            document.getElementById('galleryEditorStatus').textContent = 'Failed to process image.';
        }
    }

    async function saveGalleryEdits() {
        const statusDiv = document.getElementById('galleryEditorStatus');
        statusDiv.textContent = 'Saving...';

        // Strip metadata from slots before sending (keeps payload manageable)
        const slotsToSave = galleryEditorState.slots.map(s => ({
            data:      s.data,
            original:  s.original,
            thumb:     s.thumb,
            mime_type: s.mime_type,
            file_size: s.file_size,
        }));

        const formData = new FormData();
        formData.append('image_id', galleryEditorState.imageId);
        formData.append('slots', JSON.stringify(slotsToSave));
        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        // Save caption update via share settings endpoint
        const caption = document.getElementById('galleryEditorCaption').value.trim();
        if (caption !== '') {
            const capForm = new FormData();
            capForm.append('image_id', galleryEditorState.imageId);
            capForm.append('caption', caption);
            capForm.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');
            await fetch('/admin/update-caption', { method: 'POST', body: capForm });
        }

        try {
            const response = await fetch('/admin/update-images', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                statusDiv.textContent = 'Saved!';
                setTimeout(() => {
                    closeGalleryEditor();
                    location.reload();
                }, 800);
            } else {
                statusDiv.textContent = 'Error: ' + result.error;
            }
        } catch (e) {
            statusDiv.textContent = 'Save failed: ' + e.message;
        }
    }
</script>
