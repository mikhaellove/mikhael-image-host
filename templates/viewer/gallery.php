<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $plainCaption = trim(strip_tags($image['caption'] ?? '')); ?>
    <title><?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Gallery') ?></title>

    <meta property="og:title" content="<?= htmlspecialchars($plainCaption !== '' ? $plainCaption : 'Shared Gallery') ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/raw/' . $image['slug']) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($plainCaption) ?>">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #000;
            overflow-x: hidden;
            overflow-y: auto;
            min-height: 100vh;
        }

        .gallery-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            gap: 20px;
        }

        .gallery-image-wrap {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .gallery-image-wrap img {
            max-width: 90vw;
            max-height: 90vh;
            display: block;
            object-fit: contain;
        }

        .gallery-divider {
            width: 90vw;
            max-width: 900px;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }

        .metadata-section {
            width: 100%;
            padding: 20px;
        }

        .metadata-container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(30,30,30,0.9);
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

        .metadata-icon { font-size: 20px; opacity: 0.8; }

        .metadata-content { flex: 1; }

        .metadata-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .metadata-value { font-size: 16px; color: #fff; }

        .metadata-caption {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #fff;
            margin-top: 20px;
        }

        .metadata-display-name {
            color: #fff;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="gallery-container">
        <?php foreach ($slots as $i => $slot): ?>
            <?php if ($i > 0): ?>
                <div class="gallery-divider"></div>
            <?php endif; ?>
            <div class="gallery-image-wrap">
                <img src="/raw/<?= htmlspecialchars($image['slug']) ?>/<?= $i ?>"
                     alt="<?= htmlspecialchars($plainCaption) ?> (<?= $i + 1 ?> of <?= count($slots) ?>)">
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $displayMetadata = null;
    if (!empty($image['display_metadata'])) {
        $displayMetadata = json_decode($image['display_metadata'], true);
    }

    $showMetadata = false;
    if ($displayMetadata) {
        foreach ($displayMetadata as $value) {
            if ($value) { $showMetadata = true; break; }
        }
    }

    $hasNonCaptionMetadata = (
        (!empty($displayMetadata['show_date']) && !empty($image['created_at']))
        || (!empty($displayMetadata['show_views']) && isset($image['view_count']))
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
