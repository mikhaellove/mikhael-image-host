<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $plainCaption = trim(strip_tags($image['caption'] ?? '')); ?>
    <title><?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Video') ?></title>

    <!-- OpenGraph Meta Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Shared Video') ?>">
    <meta property="og:type" content="video.other">
    <meta property="og:url" content="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($plainCaption) ?>">
    <meta property="og:video" content="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/raw/' . $image['slug']) ?>">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #1a1a1a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .video-container {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }

        video {
            width: 100%;
            display: block;
            max-height: 70vh;
        }

        .video-actions {
            background: #2d2d2d;
            padding: 12px 16px;
            text-align: right;
        }

        .download-btn {
            display: inline-block;
            padding: 8px 20px;
            background: #ff8a00;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.2s;
        }

        .download-btn:hover {
            background: #e67a00;
        }

        .metadata-section {
            width: 100%;
            max-width: 900px;
            margin-top: 30px;
            background: rgba(30, 30, 30, 0.9);
            border-radius: 8px;
            padding: 30px;
        }

        .metadata-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .metadata-item {
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .metadata-icon {
            font-size: 20px;
            opacity: 0.8;
        }

        .metadata-content {
            flex: 1;
        }

        .metadata-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .metadata-value {
            font-size: 16px;
            color: #fff;
        }

        .metadata-caption {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #fff;
        }

        .metadata-grid + .metadata-caption {
            margin-top: 20px;
        }

        .metadata-display-name {
            color: #fff;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .metadata-display-name:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <?php
    $displayMetadata = null;
    if (!empty($image['display_metadata'])) {
        $displayMetadata = json_decode($image['display_metadata'], true);
    }
    ?>

    <div class="video-container">
        <video controls controlsList="nodownload" preload="metadata">
            <source src="/raw/<?= htmlspecialchars($image['slug']) ?>" type="<?= htmlspecialchars($image['mime_type']) ?>">
            Your browser does not support the video element.
        </video>
        <?php if (!empty($displayMetadata['show_download'])): ?>
        <div class="video-actions">
            <a href="/raw/<?= htmlspecialchars($image['slug']) ?>" download class="download-btn">Download Video</a>
        </div>
        <?php endif; ?>
    </div>

    <?php

    $showMetadata = false;
    if ($displayMetadata) {
        foreach ($displayMetadata as $value) {
            if ($value) { $showMetadata = true; break; }
        }
    }

    $hasNonCaptionMetadata = (
        (!empty($displayMetadata['show_date']) && !empty($image['created_at']))
        || (!empty($displayMetadata['show_views']) && isset($image['view_count']))
        || (!empty($displayMetadata['show_size']) && !empty($image['file_size']))
        || (!empty($displayMetadata['show_duration']) && !empty($image['duration']))
        || (!empty($displayMetadata['show_format']) && !empty($image['mime_type']))
    );

    $owner = null;
    if (!empty($displayMetadata['show_display_name']) && !empty($image['user_id'])) {
        $owner = \App\Models\User::findById((int)$image['user_id']);
    }
    ?>

    <?php if ($showMetadata): ?>
    <div class="metadata-section">
        <?php if (!empty($displayMetadata['show_display_name']) && !empty($owner['name'])): ?>
            <div class="metadata-display-name"><?= htmlspecialchars($owner['name']) ?></div>
        <?php endif; ?>

        <?php if ($hasNonCaptionMetadata): ?>
        <div class="metadata-grid">
            <?php if (!empty($displayMetadata['show_date']) && !empty($image['created_at'])): ?>
                <div class="metadata-item">
                    <div class="metadata-icon">📅</div>
                    <div class="metadata-content">
                        <div class="metadata-label">Uploaded</div>
                        <div class="metadata-value"><?= date('M j, Y g:i A T', strtotime($image['created_at'])) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($displayMetadata['show_views']) && isset($image['view_count'])): ?>
                <div class="metadata-item">
                    <div class="metadata-icon">👁</div>
                    <div class="metadata-content">
                        <div class="metadata-label">Views</div>
                        <div class="metadata-value"><?= number_format($image['view_count']) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($displayMetadata['show_size']) && !empty($image['file_size'])): ?>
                <div class="metadata-item">
                    <div class="metadata-icon">💾</div>
                    <div class="metadata-content">
                        <div class="metadata-label">File Size</div>
                        <div class="metadata-value"><?= number_format($image['file_size'] / 1024 / 1024, 2) ?> MB</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($displayMetadata['show_duration']) && !empty($image['duration'])): ?>
                <div class="metadata-item">
                    <div class="metadata-icon">⏱</div>
                    <div class="metadata-content">
                        <div class="metadata-label">Duration</div>
                        <div class="metadata-value"><?= \App\Services\AudioProcessor::formatDuration($image['duration']) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($displayMetadata['show_format']) && !empty($image['mime_type'])): ?>
                <div class="metadata-item">
                    <div class="metadata-icon">📄</div>
                    <div class="metadata-content">
                        <div class="metadata-label">Format</div>
                        <div class="metadata-value"><?= strtoupper(str_replace('video/', '', $image['mime_type'])) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($displayMetadata['show_caption']) && !empty($image['caption'])): ?>
            <div class="metadata-caption">
                <div class="metadata-icon">💬</div>
                <div class="metadata-content">
                    <div class="metadata-label">Caption</div>
                    <div class="metadata-value"><?= \App\Services\HtmlSanitizer::renderCaption($image['caption']) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>
