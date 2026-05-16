<!-- Image Editor Modal -->
<div id="imageEditorModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #000; z-index: 2000;">
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

    async function openImageEditor(imageId, slug) {
        editorState.imageId = imageId;
        editorState.slug = slug;
        document.getElementById('editorImageId').value = imageId;
        document.getElementById('imageEditorModal').style.display = 'block';

        // Initialize canvas
        editorState.canvas = document.getElementById('editorCanvas');
        editorState.ctx = editorState.canvas.getContext('2d');

        // Load image
        await loadEditorImage(slug);

        document.getElementById('editorStatus').textContent = 'Image loaded. Make your edits and click Apply Changes.';
    }

    function closeImageEditor() {
        document.getElementById('imageEditorModal').style.display = 'none';
        resetAllEdits();
    }

    async function loadEditorImage(slug) {
        try {
            // Load master image from /raw/{slug} endpoint
            const imageSrc = '/raw/' + slug;

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

        // Add crop data if available
        if (editorState.cropData) {
            formData.append('crop', JSON.stringify(editorState.cropData));
        }

        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/edit-image', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.textContent = 'Changes applied successfully!';
                setTimeout(() => {
                    closeImageEditor();
                    location.reload();
                }, 1000);
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
        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/revert-to-original', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.textContent = 'Reverted to original successfully!';
                setTimeout(() => {
                    closeImageEditor();
                    location.reload();
                }, 1000);
            } else {
                statusDiv.textContent = 'Error: ' + result.error;
            }
        } catch (error) {
            statusDiv.textContent = 'Error reverting: ' + error.message;
        }
    }
</script>
