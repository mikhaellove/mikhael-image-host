<!-- Edit User Modal -->
<div id="editUserModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0;">Edit User</h2>
            <button type="button" onclick="closeEditUser()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>

        <form id="editUserForm" style="padding: 20px;">
            <input type="hidden" id="editUserId" name="user_id">
            <input type="hidden" name="csrf_token" value="<?= \App\Core\Auth::generateCsrfToken() ?>">

            <div class="form-group">
                <label for="editUsername">Username</label>
                <input type="text" id="editUsername" name="username" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div class="form-group">
                <label for="editName">Name</label>
                <input type="text" id="editName" name="name" placeholder="Display name (optional)" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>

            <div class="form-group">
                <label for="editRole">Role</label>
                <select id="editRole" name="role" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label for="editPassword">New Password</label>
                <input type="password" id="editPassword" name="password" placeholder="Leave empty to keep current password" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666; display: block; margin-top: 5px;">If set, the user's password is replaced and "Must Reset" is cleared.</small>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">API Token</label>
                <div id="tokenStatus" style="font-size: 14px; color: #666; margin-bottom: 10px;"></div>
                <div id="tokenValue" style="display: none; margin-bottom: 10px;">
                    <input type="text" id="tokenInput" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px; box-sizing: border-box;">
                    <small style="color: #856404; display: block; margin-top: 4px;">Copy this token now — it will not be shown again after you close this dialog.</small>
                    <button type="button" onclick="copyToken()" class="btn" style="margin-top: 8px; background: #17a2b8;">Copy to Clipboard</button>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="generateTokenBtn" onclick="generateToken()" class="btn" style="background: #28a745;">Generate New Token</button>
                    <button type="button" id="revokeTokenBtn" onclick="revokeToken()" class="btn btn-danger" style="display: none;">Revoke Token</button>
                </div>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn" style="flex: 1;">Save Changes</button>
                <button type="button" onclick="closeEditUser()" class="btn" style="flex: 1; background: #6c757d;">Cancel</button>
            </div>

            <div id="editUserStatus" style="margin-top: 15px;"></div>
        </form>
    </div>
</div>

<script>
    function openEditUser(userId) {
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUserStatus').innerHTML = '';
        document.getElementById('tokenValue').style.display = 'none';
        document.getElementById('tokenStatus').textContent = 'Loading...';
        document.getElementById('editUserModal').style.display = 'flex';

        fetch('/admin/get-user?id=' + encodeURIComponent(userId))
            .then(r => r.json())
            .then(result => {
                if (!result.success) {
                    document.getElementById('editUserStatus').innerHTML =
                        '<div style="color: #dc3545;">' + (result.error || 'Failed to load user') + '</div>';
                    return;
                }
                const u = result.data;
                document.getElementById('editUsername').value = u.username || '';
                document.getElementById('editName').value = u.name || '';
                document.getElementById('editRole').value = u.role || 'user';
                document.getElementById('editPassword').value = '';
                renderTokenSection(u.has_token);
            })
            .catch(err => {
                document.getElementById('editUserStatus').innerHTML =
                    '<div style="color: #dc3545;">Failed to load user: ' + err.message + '</div>';
            });
    }

    let tokenIsActive = false;

    function renderTokenSection(hasToken) {
        tokenIsActive = hasToken;
        document.getElementById('tokenStatus').textContent = hasToken ? 'Status: Active' : 'Status: None';
        document.getElementById('tokenStatus').style.color = hasToken ? '#28a745' : '#6c757d';
        document.getElementById('revokeTokenBtn').style.display = hasToken ? 'inline-block' : 'none';
        document.getElementById('generateTokenBtn').textContent = hasToken ? 'Regenerate Token' : 'Generate New Token';
    }

    async function generateToken() {
        const userId = document.getElementById('editUserId').value;
        if (tokenIsActive && !confirm('This will invalidate the existing token. Continue?')) return;

        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/generate-token', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                document.getElementById('tokenInput').value = result.token;
                document.getElementById('tokenValue').style.display = 'block';
                renderTokenSection(true);
            } else {
                alert('Failed to generate token: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Failed to generate token: ' + err.message);
        }
    }

    async function revokeToken() {
        if (!confirm('Revoke this user\'s API token? Any active CLI or mobile clients using it will stop working.')) return;

        const userId = document.getElementById('editUserId').value;
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('csrf_token', '<?= \App\Core\Auth::generateCsrfToken() ?>');

        try {
            const response = await fetch('/admin/revoke-token', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                document.getElementById('tokenValue').style.display = 'none';
                renderTokenSection(false);
            } else {
                alert('Failed to revoke token: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Failed to revoke token: ' + err.message);
        }
    }

    function copyToken() {
        const input = document.getElementById('tokenInput');
        input.select();
        navigator.clipboard.writeText(input.value);
    }

    function closeEditUser() {
        document.getElementById('editUserModal').style.display = 'none';
        document.getElementById('editUserForm').reset();
        document.getElementById('editUserStatus').innerHTML = '';
        document.getElementById('tokenValue').style.display = 'none';
    }

    document.getElementById('editUserModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditUser();
    });

    document.getElementById('editUserForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const statusDiv = document.getElementById('editUserStatus');
        statusDiv.innerHTML = '<div style="color: #007bff;">Saving...</div>';

        try {
            const response = await fetch('/admin/update-user', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                statusDiv.innerHTML = '<div style="color: #28a745;">✓ Saved</div>';
                setTimeout(() => { closeEditUser(); location.reload(); }, 800);
            } else {
                statusDiv.innerHTML = '<div style="color: #dc3545;">✗ ' + (result.error || 'Save failed') + '</div>';
            }
        } catch (err) {
            statusDiv.innerHTML = '<div style="color: #dc3545;">✗ ' + err.message + '</div>';
        }
    });
</script>
