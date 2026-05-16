<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Vault - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .wizard {
            max-width: 600px;
            margin: 50px auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            margin-bottom: 30px;
            color: #333;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 12px 24px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .check-list {
            list-style: none;
            margin: 20px 0;
        }

        .check-list li {
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 4px;
        }

        .check-list li.pass {
            background: #d4edda;
            color: #155724;
        }

        .check-list li.fail {
            background: #f8d7da;
            color: #721c24;
        }

        .check-list li.pending {
            background: #e9ecef;
            color: #495057;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="wizard">
        <h1>🛡️ Project Vault Installation</h1>

        <!-- Step 1: Pre-Flight Check -->
        <div class="step active" id="step1">
            <h2>Step 1: Pre-Flight Check</h2>
            <p>Checking system requirements...</p>

            <ul class="check-list" id="checkList">
                <li class="pending">PDO Extension</li>
                <li class="pending">FileInfo Extension</li>
                <li class="pending">ImageMagick Binary</li>
                <li class="pending">Config Directory Writable</li>
                <li class="pending">Memory Limit (256MB+)</li>
                <li class="pending">POST Max Size (50MB+)</li>
                <li class="pending">Upload Max Filesize (50MB+)</li>
            </ul>

            <button class="btn" id="runChecks">Run Checks</button>
            <button class="btn" id="nextToDb" style="display: none;">Next: Database Setup</button>
        </div>

        <!-- Step 2: Database Configuration -->
        <div class="step" id="step2">
            <h2>Step 2: Database Configuration</h2>

            <div id="dbStatus"></div>

            <div class="form-group">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" value="localhost">
            </div>

            <div class="form-group">
                <label for="db_name">Database Name</label>
                <input type="text" id="db_name">
            </div>

            <div class="form-group">
                <label for="db_user">Database Username</label>
                <input type="text" id="db_user">
            </div>

            <div class="form-group">
                <label for="db_pass">Database Password</label>
                <input type="password" id="db_pass">
            </div>

            <button class="btn" id="testDb">Test Connection</button>
            <button class="btn" id="nextToAdmin" style="display: none;">Next: Create Admin</button>
        </div>

        <!-- Step 3: Admin Account -->
        <div class="step" id="step3">
            <h2>Step 3: Create Admin Account</h2>

            <div id="adminStatus"></div>

            <div class="form-group">
                <label for="admin_username">Admin Username</label>
                <input type="text" id="admin_username">
            </div>

            <div class="form-group">
                <label for="admin_password">Admin Password</label>
                <input type="password" id="admin_password">
            </div>

            <div class="form-group">
                <label for="admin_password_confirm">Confirm Password</label>
                <input type="password" id="admin_password_confirm">
            </div>

            <button class="btn" id="completeInstall">Complete Installation</button>
        </div>

        <!-- Step 4: Complete -->
        <div class="step" id="step4">
            <h2>Installation Complete!</h2>

            <div class="success">
                Project Vault has been successfully installed.
            </div>

            <p>Your secure image vault is now ready to use.</p>

            <a href="/admin" class="btn" style="display: inline-block; margin-top: 20px; text-decoration: none;">Go to Login</a>
        </div>
    </div>

    <script>
        let currentStep = 1;

        document.getElementById('runChecks').addEventListener('click', async () => {
            const response = await fetch('/install/preflight', { method: 'POST' });
            const result = await response.json();

            const checkList = document.getElementById('checkList');
            const checks = [
                'PDO Extension',
                'FileInfo Extension',
                'ImageMagick Binary',
                'Config Directory Writable',
                'Memory Limit (256MB+)',
                'POST Max Size (50MB+)',
                'Upload Max Filesize (50MB+)'
            ];

            const checkKeys = ['pdo', 'fileinfo', 'imagemagick', 'config_writable', 'memory_limit', 'post_max_size', 'upload_max_filesize'];

            checkList.innerHTML = '';
            checkKeys.forEach((key, index) => {
                const li = document.createElement('li');
                li.textContent = checks[index];
                li.className = result.checks[key] ? 'pass' : 'fail';
                checkList.appendChild(li);
            });

            if (result.success) {
                document.getElementById('nextToDb').style.display = 'inline-block';
            }
        });

        document.getElementById('nextToDb').addEventListener('click', () => {
            showStep(2);
        });

        document.getElementById('testDb').addEventListener('click', async () => {
            const dbStatus = document.getElementById('dbStatus');
            dbStatus.innerHTML = '<p>Testing connection...</p>';

            const formData = new FormData();
            formData.append('db_host', document.getElementById('db_host').value);
            formData.append('db_name', document.getElementById('db_name').value);
            formData.append('db_user', document.getElementById('db_user').value);
            formData.append('db_pass', document.getElementById('db_pass').value);

            const response = await fetch('/install/test-connection', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                dbStatus.innerHTML = '<div class="success">' + result.message + '</div>';
                document.getElementById('nextToAdmin').style.display = 'inline-block';
            } else {
                dbStatus.innerHTML = '<div class="error">' + result.message + '</div>';
            }
        });

        document.getElementById('nextToAdmin').addEventListener('click', () => {
            showStep(3);
        });

        document.getElementById('completeInstall').addEventListener('click', async () => {
            const adminStatus = document.getElementById('adminStatus');
            const password = document.getElementById('admin_password').value;
            const confirm = document.getElementById('admin_password_confirm').value;

            if (password !== confirm) {
                adminStatus.innerHTML = '<div class="error">Passwords do not match</div>';
                return;
            }

            if (password.length < 8) {
                adminStatus.innerHTML = '<div class="error">Password must be at least 8 characters</div>';
                return;
            }

            adminStatus.innerHTML = '<p>Installing...</p>';

            const formData = new FormData();
            formData.append('db_host', document.getElementById('db_host').value);
            formData.append('db_name', document.getElementById('db_name').value);
            formData.append('db_user', document.getElementById('db_user').value);
            formData.append('db_pass', document.getElementById('db_pass').value);
            formData.append('admin_username', document.getElementById('admin_username').value);
            formData.append('admin_password', password);

            const response = await fetch('/install/run', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showStep(4);
            } else {
                adminStatus.innerHTML = '<div class="error">' + result.message + '</div>';
            }
        });

        function showStep(step) {
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');
            currentStep = step;
        }
    </script>
</body>
</html>
