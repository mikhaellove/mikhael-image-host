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
            })
            .catch(err => {
                document.getElementById('editUserStatus').innerHTML =
                    '<div style="color: #dc3545;">Failed to load user: ' + err.message + '</div>';
            });
    }

    function closeEditUser() {
        document.getElementById('editUserModal').style.display = 'none';
        document.getElementById('editUserForm').reset();
        document.getElementById('editUserStatus').innerHTML = '';
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
