"?<?php
use App\Core\Auth;
$pageTitle = 'Landing Pages';
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

    .landing-pages-list {
        background: #fff;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .landing-page-item {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .landing-page-item:last-child {
        border-bottom: none;
    }

    .landing-page-info {
        flex: 1;
    }

    .landing-page-name {
        font-weight: bold;
        font-size: 16px;
        margin-bottom: 5px;
    }

    .landing-page-meta {
        font-size: 14px;
        color: #666;
    }

    .active-badge {
        display: inline-block;
        background: #28a745;
        color: white;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
        margin-left: 10px;
    }

    .landing-page-actions {
        display: flex;
        gap: 10px;
    }

    .btn-small {
        padding: 6px 12px;
        font-size: 14px;
    }
</style>

<nav>
    <div class="container">
        <div>
            <h1 style="margin: 0;">Landing Pages</h1>
        </div>
        <div>
            <a href="/admin" class="btn">← Back to Dashboard</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="/admin/manage" class="btn">Manage Users</a>
            <?php endif; ?>
            <a href="/admin/logout" class="btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <p>Create and manage multiple landing pages. Switch between them based on your mood, occasion, or purpose.</p>
        <button onclick="showCreateModal()" class="btn" style="background: #28a745;">+ Create New Landing Page</button>
    </div>

    <?php if (isset($_SESSION['landing_page_success'])): ?>
        <div class="success" style="margin-bottom: 20px;">
            <?= htmlspecialchars($_SESSION['landing_page_success']) ?>
        </div>
        <?php unset($_SESSION['landing_page_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['landing_page_error'])): ?>
        <div class="error" style="margin-bottom: 20px;">
            <?= htmlspecialchars($_SESSION['landing_page_error']) ?>
        </div>
        <?php unset($_SESSION['landing_page_error']); ?>
    <?php endif; ?>

    <div class="landing-pages-list">
        <?php if (empty($landingPages)): ?>
            <div class="landing-page-item">
                <p style="color: #666; margin: 0;">No landing pages yet. Create your first one!</p>
            </div>
        <?php else: ?>
            <?php foreach ($landingPages as $page): ?>
                <div class="landing-page-item">
                    <div class="landing-page-info">
                        <div class="landing-page-name">
                            <?= htmlspecialchars($page['name']) ?>
                            <?php if ($page['is_active']): ?>
                                <span class="active-badge">ACTIVE</span>
                            <?php endif; ?>
                        </div>
                        <div class="landing-page-meta">
                            Created: <?= date('M j, Y g:i A', strtotime($page['created_at'])) ?>
                            <?php if ($page['tagline']): ?>
                                • <?= htmlspecialchars($page['tagline']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="landing-page-actions">
                        <?php if (!$page['is_active']): ?>
                            <button onclick="setActive(<?= $page['id'] ?>)" class="btn btn-small" style="background: #28a745;">Set Active</button>
                        <?php endif; ?>
                        <button onclick="editLandingPage(<?= $page['id'] ?>)" class="btn btn-small">Edit</button>
                        <a href="/preview/<?= $page['id'] ?>" target="_blank" class="btn btn-small" style="background: #17a2b8;">Preview</a>
                        <?php if (!$page['is_active']): ?>
                            <button onclick="deleteLandingPage(<?= $page['id'] ?>)" class="btn btn-small" style="background: #dc3545;">Delete</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="landingPageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 800px; margin: 50px auto; background: #fff; border-radius: 8px; padding: 30px;">
        <h2 id="modalTitle">Create Landing Page</h2>

        <form id="landingPageForm" method="POST" action="/admin/landing-page-save">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">
            <input type="hidden" id="pageId" name="page_id" value="">

            <div class="form-group">
                <label>Page Name (Admin Only)</label>
                <input type="text" name="name" id="pageName" required class="form-control" placeholder="e.g., Default, Vacation Mode, Holiday Theme">
            </div>

            <div class="form-group">
                <label>Tagline</label>
                <input type="text" name="tagline" id="pageTagline" class="form-control" placeholder="Optional tagline">
            </div>

            <div class="form-group">
                <label>Logo (Image Slug)</label>
                <input type="text" name="logo_slug" id="pageLogoSlug" class="form-control" placeholder="Optional - use slug from your uploaded images">
            </div>

            <div class="form-group">
                <label>Background Color</label>
                <input type="color" name="bg_color" id="pageBgColor" value="#f5f5f5" class="form-control">
            </div>

            <div class="form-group">
                <label>Text Color</label>
                <input type="color" name="text_color" id="pageTextColor" value="#333333" class="form-control">
            </div>

            <div class="form-group">
                <label>HTML Content</label>
                <?php $prefix = 'landingPage'; $inputName = 'html_content'; include __DIR__ . '/wysiwyg_caption.php'; ?>
                <small style="color: #666;">Use the toolbar to format text.</small>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn" style="background: #28a745;">Save Landing Page</button>
                <button type="button" onclick="closeModal()" class="btn" style="background: #6c757d;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showCreateModal() {
        document.getElementById('modalTitle').textContent = 'Create Landing Page';
        document.getElementById('landingPageForm').action = '/admin/landing-page-create';
        document.getElementById('pageId').value = '';
        document.getElementById('pageName').value = '';
        document.getElementById('pageTagline').value = '';
        document.getElementById('pageLogoSlug').value = '';
        document.getElementById('pageBgColor').value = '#f5f5f5';
        document.getElementById('pageTextColor').value = '#333333';
        initWysiwygCaption('landingPage', '');
        document.getElementById('landingPageModal').style.display = 'block';
    }

    async function editLandingPage(id) {
        // Load landing page data
        const response = await fetch('/admin/landing-page-get?id=' + id);
        const result = await response.json();

        if (result.success) {
            document.getElementById('modalTitle').textContent = 'Edit Landing Page';
            document.getElementById('landingPageForm').action = '/admin/landing-page-update';
            document.getElementById('pageId').value = result.data.id;
            document.getElementById('pageName').value = result.data.name;
            document.getElementById('pageTagline').value = result.data.tagline || '';
            document.getElementById('pageLogoSlug').value = result.data.logo_slug || '';
            document.getElementById('pageBgColor').value = result.data.bg_color;
            document.getElementById('pageTextColor').value = result.data.text_color;
            initWysiwygCaption('landingPage', result.data.html_content || '');
            document.getElementById('landingPageModal').style.display = 'block';
        } else {
            alert('Failed to load landing page');
        }
    }

    function closeModal() {
        document.getElementById('landingPageModal').style.display = 'none';
    }

    async function setActive(id) {
        if (!confirm('Set this landing page as active? It will replace the current active page.')) return;

        const formData = new FormData();
        formData.append('page_id', id);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');

        const response = await fetch('/admin/landing-page-set-active', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert('Failed to set active: ' + result.error);
        }
    }

    async function deleteLandingPage(id) {
        if (!confirm('Are you sure you want to delete this landing page?')) return;

        const formData = new FormData();
        formData.append('page_id', id);
        formData.append('csrf_token', '<?= Auth::generateCsrfToken() ?>');

        const response = await fetch('/admin/landing-page-delete', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert('Failed to delete: ' + result.error);
        }
    }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
