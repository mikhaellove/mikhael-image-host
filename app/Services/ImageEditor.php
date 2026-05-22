<?php

namespace App\Services;

class ImageEditor
{
    /**
     * Apply all edits to an image
     */
    public static function applyEdits(string $imageData, array $edits): array
    {
        $image = null;
        $thumb = null;

        try {
            $image = new \Imagick();
            $image->readImageBlob($imageData);

            // Apply crop if specified
            if (!empty($edits['crop'])) {
                $crop = $edits['crop'];
                $image->cropImage(
                    $crop['width'],
                    $crop['height'],
                    $crop['x'],
                    $crop['y']
                );
                $image->setImagePage(0, 0, 0, 0); // Reset canvas
            }

            // Apply brightness if specified
            if (isset($edits['brightness']) && $edits['brightness'] != 0) {
                self::adjustBrightness($image, $edits['brightness']);
            }

            // Apply contrast if specified
            if (isset($edits['contrast']) && $edits['contrast'] != 0) {
                self::adjustContrast($image, $edits['contrast']);
            }

            // Apply filter if specified
            if (!empty($edits['filter']) && $edits['filter'] !== 'original') {
                self::applyFilter($image, $edits['filter']);
            }

            // Apply mosaic last so pixel data is stable after all other transforms
            if (!empty($edits['mosaic_boxes']) && is_array($edits['mosaic_boxes'])) {
                $mosaicScale   = (int)\App\Models\Setting::get('mosaic_scale', 5);
                $workingSize   = (int)\App\Models\Setting::get('mosaic_working_size', 400);
                self::applyMosaic($image, $edits['mosaic_boxes'], $mosaicScale, $workingSize);
            }

            // Ensure JPEG format
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(98);

            $masterData = $image->getImageBlob();

            // Generate new thumbnail
            $thumb = clone $image;
            $thumb->cropThumbnailImage(300, 300);
            $thumb->setImageCompressionQuality(85);
            $thumbData = $thumb->getImageBlob();

            return [
                'master' => $masterData,
                'thumbnail' => $thumbData
            ];

        } catch (\ImagickException $e) {
            throw new \RuntimeException("Image editing failed: " . $e->getMessage());
        } finally {
            if ($image !== null) {
                $image->clear();
                $image->destroy();
            }
            if ($thumb !== null) {
                $thumb->clear();
                $thumb->destroy();
            }
        }
    }

    /**
     * Adjust brightness
     */
    private static function adjustBrightness(\Imagick $image, int $level): void
    {
        // Level: -100 to +100
        // Convert to Imagick brightness: 0-200 (100 = no change)
        $brightness = 100 + $level;
        $image->modulateImage($brightness, 100, 100);
    }

    /**
     * Adjust contrast
     */
    private static function adjustContrast(\Imagick $image, int $level): void
    {
        // Level: -100 to +100
        // Imagick uses sharpen value
        $sharpen = $level > 0 ? $level / 10 : $level / 10;
        $image->brightnessContrastImage(0, $sharpen);
    }

    /**
     * Apply filter to image
     */
    private static function applyFilter(\Imagick $image, string $filter): void
    {
        switch ($filter) {
            case 'bw':
                // Black and white
                $image->setImageType(\Imagick::IMGTYPE_GRAYSCALE);
                break;

            case 'sepia':
                // Sepia tone
                $image->sepiaToneImage(80);
                break;

            case 'vintage':
                // Vintage (cool tone with slight desaturation)
                $image->modulateImage(100, 70, 100); // Desaturate
                $image->colorizeImage('#4169E1', 0.1); // Cool blue tint
                break;

            case 'warm':
                // Warm tone
                $image->colorizeImage('#FFA500', 0.15); // Warm orange tint
                break;
        }
    }

    private static function applyMosaic(\Imagick $image, array $boxes, int $mosaicScale, int $workingSize): void
    {
        $scaleFactor = $mosaicScale / 100;

        foreach ($boxes as $box) {
            $x  = max(0, (int)($box['x'] ?? 0));
            $y  = max(0, (int)($box['y'] ?? 0));
            $rw = (int)($box['width'] ?? 0);
            $rh = (int)($box['height'] ?? 0);

            $imgW = $image->getImageWidth();
            $imgH = $image->getImageHeight();
            if ($x + $rw > $imgW) $rw = $imgW - $x;
            if ($y + $rh > $imgH) $rh = $imgH - $y;
            if ($rw <= 0 || $rh <= 0) continue;

            $region = clone $image;
            $region->cropImage($rw, $rh, $x, $y);

            $origRw = $rw;
            $origRh = $rh;

            if ($rw > $workingSize || $rh > $workingSize) {
                $region->resizeImage($workingSize, $workingSize, \Imagick::FILTER_TRIANGLE, 1, true);
                $rw = $region->getImageWidth();
                $rh = $region->getImageHeight();
            }

            $region->scaleImage(max(1, (int)round($rw * $scaleFactor)), max(1, (int)round($rh * $scaleFactor)));
            $region->scaleImage($rw, $rh);

            if ($origRw !== $rw || $origRh !== $rh) {
                $region->resizeImage($origRw, $origRh, \Imagick::FILTER_POINT, 1);
            }

            $image->compositeImage($region, \Imagick::COMPOSITE_COPY, $x, $y);
            $region->clear();
            $region->destroy();
        }
    }

    /**
     * Get image dimensions for crop preview
     */
    public static function getImageDimensions(string $imageData): array
    {
        try {
            $image = new \Imagick();
            $image->readImageBlob($imageData);

            $dimensions = [
                'width' => $image->getImageWidth(),
                'height' => $image->getImageHeight()
            ];

            $image->clear();
            $image->destroy();

            return $dimensions;
        } catch (\ImagickException $e) {
            throw new \RuntimeException("Failed to get image dimensions: " . $e->getMessage());
        }
    }
}
