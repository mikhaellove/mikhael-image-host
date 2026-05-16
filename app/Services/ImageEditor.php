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
