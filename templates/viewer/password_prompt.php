<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Required</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .password-form {
            background: #1a1a1a;
            padding: 40px;
            border-radius: 8px;
            max-width: 400px;
            width: 100%;
        }

        .password-form h1 {
            color: #fff;
            font-size: 24px;
            margin-bottom: 10px;
            text-align: center;
        }

        .password-form p {
            color: #999;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 4px;
            color: #fff;
            font-size: 16px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #007bff;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #0056b3;
        }

        .error {
            color: #ff4444;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="password-form">
        <h1>🔒 Password Required</h1>
        <p>This image is password-protected</p>

        <?php if (isset($_GET['error'])): ?>
            <div class="error">Incorrect password. Please try again.</div>
        <?php endif; ?>

        <form method="POST" action="/v/<?= htmlspecialchars($image['slug']) ?>/verify">
            <div class="form-group">
                <input type="password" name="password" placeholder="Enter password" required autofocus>
            </div>
            <button type="submit">View Image</button>
        </form>
    </div>
</body>
</html>
