<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $plainCaption = trim(strip_tags($image['caption'] ?? '')); ?>
    <title><?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Audio') ?></title>

    <!-- OpenGraph Meta Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Shared Audio') ?>">
    <meta property="og:type" content="music.song">
    <meta property="og:url" content="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($plainCaption) ?>">

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

        .audio-container {
            background: #2d2d2d;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .audio-icon {
            font-size: 80px;
            margin-bottom: 20px;
            color: #64b5f6;
        }

        .duration {
            color: #aaa;
            font-size: 14px;
            margin-bottom: 20px;
        }

        audio {
            width: 100%;
            margin-bottom: 20px;
            outline: none;
        }

        .caption {
            color: #fff;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .download-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #64b5f6;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .download-btn:hover {
            background: #42a5f5;
        }

        .metadata-section {
            width: 100%;
            max-width: 600px;
            margin-top: 40px;
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
    <div class="audio-container">
        <div class="audio-icon">♪</div>

        <audio controls preload="metadata">
            <source src="/raw/<?= htmlspecialchars($image['slug']) ?>" type="<?= htmlspecialchars($image['mime_type']) ?>">
            Your browser does not support the audio element.
        </audio>

        <a href="/raw/<?= htmlspecialchars($image['slug']) ?>" download class="download-btn">
            Download Audio
        </a>
    </div>

    <?php
    // Parse display metadata settings
    $displayMetadata = null;
    if (!empty($image['display_metadata'])) {
        $displayMetadata = json_decode($image['display_metadata'], true);
    }

    // Check if any metadata should be displayed
    $showMetadata = false;
    if ($displayMetadata) {
        foreach ($displayMetadata as $key => $value) {
            if ($value) {
                $showMetadata = true;
                break;
            }
        }
    }
    ?>

    <?php
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
                        <div class="metadata-value"><?= date('M j, Y g:i A', strtotime($image['created_at'])) ?></div>
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
                        <div class="metadata-value"><?= strtoupper(str_replace('audio/', '', $image['mime_type'])) ?></div>
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
