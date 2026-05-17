<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $plainCaption = trim(strip_tags($image['caption'] ?? '')); ?>
    <title><?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Image') ?></title>

    <!-- OpenGraph Meta Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Shared Image') ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/raw/' . $image['slug']) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($plainCaption) ?>">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            overflow-y: auto;
            min-height: 100vh;
        }

        .image-container {
            width: 100%;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        img {
            max-width: 90vw;
            max-height: 90vh;
            display: block;
        }

        .metadata-section {
            width: 100%;
            background: #000;
            padding: 40px 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .metadata-container {
            max-width: 800px;
            margin: 0 auto;
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
    <div class="image-container">
        <img src="/raw/<?= htmlspecialchars($image['slug']) ?>" alt="<?= htmlspecialchars($plainCaption) ?>">
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
        || (!empty($displayMetadata['show_dimensions']) && !empty($image['metadata']))
        || (!empty($displayMetadata['show_format']) && !empty($image['mime_type']))
    );

    $owner = null;
    if (!empty($displayMetadata['show_display_name']) && !empty($image['user_id'])) {
        $owner = \App\Models\User::findById((int)$image['user_id']);
    }
    ?>

    <?php if ($showMetadata): ?>
    <div class="metadata-section">
        <div class="metadata-container">
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

                            <div class="metadata-value"><?= (new DateTime($image['created_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y g:i A T') ?></div>
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

                <?php if (!empty($displayMetadata['show_dimensions']) && !empty($image['metadata'])): ?>
                    <?php
                    $metadata = json_decode($image['metadata'], true);
                    if (!empty($metadata['width']) && !empty($metadata['height'])):
                    ?>
                        <div class="metadata-item">
                            <div class="metadata-icon">📐</div>
                            <div class="metadata-content">
                                <div class="metadata-label">Dimensions</div>
                                <div class="metadata-value"><?= $metadata['width'] ?> × <?= $metadata['height'] ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($displayMetadata['show_format']) && !empty($image['mime_type'])): ?>
                    <div class="metadata-item">
                        <div class="metadata-icon">📄</div>
                        <div class="metadata-content">
                            <div class="metadata-label">Format</div>
                            <div class="metadata-value"><?= strtoupper(str_replace('image/', '', $image['mime_type'])) ?></div>
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
    </div>
    <?php endif; ?>

</body>
</html>
