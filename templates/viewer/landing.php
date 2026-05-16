<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            background-color: <?= htmlspecialchars($landingSettings['bg_color']) ?>;
            color: <?= htmlspecialchars($landingSettings['text_color']) ?>;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            width: 100%;
            text-align: center;
        }

        .logo {
            margin-bottom: 30px;
        }

        .logo img {
            max-width: 500px;
            max-height: 500px;
            height: auto;
        }

        .tagline {
            font-size: 1.5rem;
            margin-bottom: 40px;
            font-weight: 300;
        }

        .content {
            text-align: left;
        }

        .content h1 {
            margin-bottom: 20px;
        }

        .content h2 {
            margin-bottom: 15px;
            margin-top: 30px;
        }

        .content p {
            margin-bottom: 15px;
        }

        .content a {
            color: <?= htmlspecialchars($landingSettings['text_color']) ?>;
            text-decoration: underline;
        }

        .content ul, .content ol {
            margin-left: 20px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($landingSettings['logo_slug'])): ?>
            <div class="logo">
                <img src="/raw/<?= htmlspecialchars($landingSettings['logo_slug']) ?>" alt="Logo">
            </div>
        <?php endif; ?>

        <?php if (!empty($landingSettings['tagline'])): ?>
            <div class="tagline">
                <?= htmlspecialchars($landingSettings['tagline']) ?>
            </div>
        <?php endif; ?>

        <div class="content">
            <?= $cleanHtml ?>
        </div>
    </div>
</body>
</html>
