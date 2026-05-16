<?php
use App\Core\Auth;
$pageTitle = 'IP Blocks';
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

    .section h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
        color: #333;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    select,
    textarea {
        width: 100%;
        max-width: 400px;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: inherit;
        font-size: 14px;
    }

    .btn {
        display: inline-block;
        padding: 8px 12px;
        background: #007bff;
        color: #fff;
        text-decoration: none;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        margin-right: 5px;
    }

    .btn:hover {
        background: #0056b3;
    }

    .btn-danger {
        background: #dc3545;
    }

    .btn-danger:hover {
        background: #c82333;
    }

    .btn-small {
        padding: 5px 10px;
        font-size: 12px;
        margin: 0 3px;
    }

    .status-active {
        background: #f8d7da;
        color: #721c24;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-expired {
        background: #d1ecf1;
        color: #0c5460;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-watching {
        background: #fff3cd;
        color: #856404;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }

    .empty-message {
        text-align: center;
        padding: 20px;
        color: #666;
    }
</style>

<nav>
    <div class="container">
        <div>
            <strong>IP Blocks</strong>
        </div>
        <div>
            <a href="/admin" class="btn">Dashboard</a>
            <a href="/admin/logout" class="btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="section">
        <h3>Block IP Address</h3>
        <form id="block-form">
            <div class="form-group">
                <label for="block-ip">IP Address:</label>
                <input type="text" id="block-ip" name="ip" placeholder="192.168.1.100" required>
            </div>
            <button type="submit" class="btn">Block IP</button>
        </form>
    </div>

    <div class="section">
        <h3>Active IP Blocks</h3>
        <?php if (empty($blocks)): ?>
            <div class="empty-message">No IP blocks recorded yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Failed Attempts</th>
                        <th>Status</th>
                        <th>Blocked At</th>
                        <th>Expires At</th>
                        <th>Last Attempt</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocks as $block): ?>
                        <?php
                            $now = time();
                            $blockedAt = $block['blocked_at'] ? strtotime($block['blocked_at']) : null;
                            $expiresAt = $block['block_expires_at'] ? strtotime($block['block_expires_at']) : null;
                            $isActive = $block['is_blocked'] && (!$expiresAt || $expiresAt > $now);
                            $isExpired = $block['is_blocked'] && $expiresAt && $expiresAt <= $now;

                            if ($isActive) {
                                $status = 'Active Block';
                                $statusClass = 'status-active';
                            } elseif ($isExpired) {
                                $status = 'Expired';
                                $statusClass = 'status-expired';
                            } else {
                                $status = 'Watching';
                                $statusClass = 'status-watching';
                            }
                        ?>
                        <tr>
                            <td><code><?= htmlspecialchars($block['ip_address']) ?></code></td>
                            <td><?= (int)$block['failed_attempts'] ?></td>
                            <td><span class="<?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td><?= $block['blocked_at'] ? date('Y-m-d H:i:s', strtotime($block['blocked_at'])) : '—' ?></td>
                            <td><?= $block['block_expires_at'] ? date('Y-m-d H:i:s', strtotime($block['block_expires_at'])) : 'Never' ?></td>
                            <td><?= $block['last_attempt_at'] ? date('Y-m-d H:i:s', strtotime($block['last_attempt_at'])) : '—' ?></td>
                            <td>
                                <button class="btn btn-small btn-danger unblock-btn" data-ip="<?= htmlspecialchars($block['ip_address']) ?>">Unblock</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';

    document.getElementById('block-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const ip = document.getElementById('block-ip').value.trim();

        if (!ip) {
            alert('Please enter an IP address');
            return;
        }

        try {
            const response = await fetch('/admin/manual-block-ip', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    ip: ip,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('IP blocked successfully');
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to block IP'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while blocking the IP');
        }
    });

    document.querySelectorAll('.unblock-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const ip = btn.getAttribute('data-ip');

            if (!confirm(`Are you sure you want to unblock ${ip}?`)) {
                return;
            }

            try {
                const response = await fetch('/admin/unblock-ip', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken
                    },
                    body: new URLSearchParams({
                        ip: ip,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('IP unblocked successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to unblock IP'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while unblocking the IP');
            }
        });
    });
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
