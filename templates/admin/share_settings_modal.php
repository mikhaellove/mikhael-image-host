<!-- Share Settings Modal -->
<div id="shareSettingsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0;">Share Settings</h2>
            <button onclick="closeShareSettings()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>

        <form id="shareSettingsForm" style="padding: 20px;">
            <input type="hidden" id="shareImageId" name="image_id">
            <input type="hidden" name="csrf_token" value="<?= \App\Core\Auth::generateCsrfToken() ?>">

            <div class="form-group">
                <label>Caption</label>
                <?php $prefix = 'share'; include __DIR__ . '/wysiwyg_caption.php'; ?>
            </div>

            <div class="form-group">
                <label for="expiration">Link Expiration</label>
                <select id="expiration" name="expiration" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Never expires</option>
                    <option value="1hour">1 Hour</option>
                    <option value="24hours">24 Hours</option>
                    <option value="7days">7 Days</option>
                    <option value="30days">30 Days</option>
                    <option value="custom">Custom Date/Time</option>
                </select>
            </div>

            <div id="customExpirationGroup" class="form-group" style="display: none;">
                <label for="customExpiration">Custom Expiration Date/Time</label>
                <input type="datetime-local" id="customExpiration" name="custom_expiration" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div class="form-group">
                <label for="password">Password Protection (optional)</label>
                <input type="password" id="password" name="password" placeholder="Leave empty for no password" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666; display: block; margin-top: 5px;">Set a password to require viewers to enter it before seeing the image</small>
            </div>

            <div class="form-group">
                <label>Metadata Display Options</label>
                <small style="color: #666; display: block; margin-bottom: 10px;">Choose what information appears below the shared media</small>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="show_display_name" id="show_display_name" value="1">
                        <span>Display Name</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="show_caption" id="show_caption" value="1">
                        <span>Caption</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="show_date" id="show_date" value="1">
                        <span>Upload Date</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="show_views" id="show_views" value="1">
                        <span>View Count</span>
                    </label>
                    <label id="duration_option" style="display: none; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="show_duration" id="show_duration" value="1">
                        <span>Duration</span>
                    </label>
                    <label id="download_option" style="display: none; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="show_download" id="show_download" value="1">
                        <span>Download Button</span>
                    </label>
                </div>
            </div>

            <div id="currentSettings" style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-bottom: 15px; display: none;">
                <strong>Current Settings:</strong>
                <div id="currentExpirationDisplay" style="margin-top: 5px;"></div>
                <div id="currentPasswordDisplay" style="margin-top: 5px;"></div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn" style="flex: 1;">Save Settings</button>
                <button type="button" onclick="closeShareSettings()" class="btn" style="flex: 1; background: #6c757d;">Cancel</button>
            </div>

            <div id="shareSettingsStatus" style="margin-top: 15px;"></div>
        </form>
    </div>
</div>

<style>
    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
</style>

<script>
    let currentShareImageId = null;

    function openShareSettings(imageId) {
        currentShareImageId = imageId;
        document.getElementById('shareImageId').value = imageId;
        document.getElementById('shareSettingsModal').style.display = 'flex';

        // Initialize editor empty until settings load
        if (window.initWysiwygCaption) initWysiwygCaption('share', '');

        // Load current settings
        loadCurrentShareSettings(imageId);
    }

    function closeShareSettings() {
        document.getElementById('shareSettingsModal').style.display = 'none';
        document.getElementById('shareSettingsForm').reset();
        if (window.initWysiwygCaption) initWysiwygCaption('share', '');
        document.getElementById('currentSettings').style.display = 'none';
        document.getElementById('shareSettingsStatus').innerHTML = '';
    }

    async function loadCurrentShareSettings(imageId) {
        try {
            const response = await fetch('/admin/get-share-settings?image_id=' + imageId);
            const result = await response.json();

            if (result.success && result.data) {
                const settings = result.data;
                const currentSettingsDiv = document.getElementById('currentSettings');

                // Load caption into WYSIWYG editor
                initWysiwygCaption('share', settings.caption || '');

                // Show/hide duration and download based on media type
                const isNonImage = settings.media_type === 'audio' || settings.media_type === 'video';
                const isVideo = settings.media_type === 'video';
                document.getElementById('duration_option').style.display = isNonImage ? 'flex' : 'none';
                document.getElementById('download_option').style.display = isVideo ? 'flex' : 'none';

                // Load display metadata checkboxes
                if (settings.display_metadata) {
                    const metadata = settings.display_metadata;
                    document.getElementById('show_display_name').checked = metadata.show_display_name || false;
                    document.getElementById('show_caption').checked = metadata.show_caption || false;
                    document.getElementById('show_date').checked = metadata.show_date || false;
                    document.getElementById('show_views').checked = metadata.show_views || false;
                    document.getElementById('show_duration').checked = metadata.show_duration || false;
                    document.getElementById('show_download').checked = metadata.show_download || false;
                }

                if (settings.expires_at || settings.has_password) {
                    currentSettingsDiv.style.display = 'block';

                    if (settings.expires_at) {
                        document.getElementById('currentExpirationDisplay').innerHTML =
                            '🕒 Expires: ' + new Date(settings.expires_at).toLocaleString();
                    }

                    if (settings.has_password) {
                        document.getElementById('currentPasswordDisplay').innerHTML =
                            '🔒 Password protected';
                    }
                }
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        }
    }

    // Show/hide custom expiration field
    document.getElementById('expiration').addEventListener('change', function() {
        const customGroup = document.getElementById('customExpirationGroup');
        customGroup.style.display = this.value === 'custom' ? 'block' : 'none';
    });

    // Handle form submission
    document.getElementById('shareSettingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const statusDiv = document.getElementById('shareSettingsStatus');

        statusDiv.innerHTML = '<div style="color: #007bff;">Saving...</div>';

        try {
            const response = await fetch('/admin/update-share-settings', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.innerHTML = '<div style="color: #28a745;">✓ Settings saved successfully!</div>';
                setTimeout(() => {
                    closeShareSettings();
                    location.reload();
                }, 1500);
            } else {
                statusDiv.innerHTML = '<div style="color: #dc3545;">✗ ' + result.error + '</div>';
            }
        } catch (error) {
            statusDiv.innerHTML = '<div style="color: #dc3545;">✗ Failed to save settings</div>';
        }
    });

    // Close modal when clicking outside
    document.getElementById('shareSettingsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeShareSettings();
        }
    });
</script>
