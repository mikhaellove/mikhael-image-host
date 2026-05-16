<?php

namespace App\Services;

class ImageProcessor
{
    private const MAX_RESOLUTION = 8000;
    private const QUALITY = 98;
    private const THUMB_SIZE = 300;
    private const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/heic',
    ];

    public static function process(string $tempFilePath): array
    {
        try {
            // Validate file exists
            if (!file_exists($tempFilePath)) {
                throw new \RuntimeException("Uploaded file not found");
            }

            // Validate MIME type using finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tempFilePath);
            finfo_close($finfo);

            if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                throw new \RuntimeException("Invalid file type: {$mimeType}. Only PNG, JPEG, WebP, and HEIC are allowed.");
            }

            // Extract metadata BEFORE processing/stripping
            $metadata = self::extractMetadata($tempFilePath);

            // Generate high-fidelity master
            $masterData = self::createMaster($tempFilePath);

            // Generate thumbnail
            $thumbData = self::createThumbnail($tempFilePath);

            return [
                'master' => $masterData,
                'thumbnail' => $thumbData,
                'metadata' => $metadata,
            ];
        } catch (\ImagickException $e) {
            throw new \RuntimeException("Image processing failed: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \RuntimeException("Image processing error: " . $e->getMessage());
        }
    }

    private static function createMaster(string $inputPath): string
    {
        $image = null;
        try {
            $image = new \Imagick($inputPath);

            // For multi-frame images (GIF, etc), use only first frame
            if ($image->getNumberImages() > 1) {
                $image = $image->coalesceImages();
                $image->setFirstIterator();
            }

            // Auto-rotate based on EXIF orientation BEFORE stripping metadata
            $image = self::autoRotateImage($image);

            // Strip all EXIF/metadata
            $image->stripImage();

            // Flatten image with white background (removes alpha)
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $image->setImageFormat('jpeg');

            // Resize if larger than max resolution (maintain aspect ratio)
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();

            if ($width > self::MAX_RESOLUTION || $height > self::MAX_RESOLUTION) {
                $image->resizeImage(self::MAX_RESOLUTION, self::MAX_RESOLUTION, \Imagick::FILTER_LANCZOS, 1, true);
            }

            // Set 4:4:4 chroma subsampling and 98% quality
            $image->setImageCompressionQuality(self::QUALITY);
            $image->setSamplingFactors(['2x2', '1x1', '1x1']); // 4:4:4 in Imagick format

            $data = $image->getImageBlob();

            if (empty($data)) {
                throw new \RuntimeException("Failed to generate image data - output is empty");
            }

            return $data;
        } catch (\ImagickException $e) {
            throw new \RuntimeException("Failed to create master image: " . $e->getMessage());
        } finally {
            if ($image !== null) {
                $image->clear();
                $image->destroy();
            }
        }
    }

    private static function createThumbnail(string $inputPath): string
    {
        $image = null;
        try {
            $image = new \Imagick($inputPath);

            // For multi-frame images, use only first frame
            if ($image->getNumberImages() > 1) {
                $image = $image->coalesceImages();
                $image->setFirstIterator();
            }

            // Auto-rotate based on EXIF orientation BEFORE stripping metadata
            $image = self::autoRotateImage($image);

            // Strip metadata
            $image->stripImage();

            // Set background white and flatten
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $image->setImageFormat('jpeg');

            // Crop to square from center
            $image->cropThumbnailImage(self::THUMB_SIZE, self::THUMB_SIZE);

            // Set quality
            $image->setImageCompressionQuality(85);

            $data = $image->getImageBlob();

            if (empty($data)) {
                throw new \RuntimeException("Failed to generate thumbnail data - output is empty");
            }

            return $data;
        } catch (\ImagickException $e) {
            throw new \RuntimeException("Failed to create thumbnail: " . $e->getMessage());
        } finally {
            if ($image !== null) {
                $image->clear();
                $image->destroy();
            }
        }
    }

    public static function isMagickAvailable(): bool
    {
        return extension_loaded('imagick');
    }

    /**
     * Rotate existing image data 90 degrees clockwise
     */
    public static function rotateImageData(string $masterData, string $thumbData, ?string $metadataJson): array
    {
        $metadata = $metadataJson ? json_decode($metadataJson, true) : [];

        // Rotate master image
        $rotatedMaster = self::rotateBlob($masterData);

        // Rotate thumbnail
        $rotatedThumb = self::rotateBlob($thumbData);

        // Update metadata to track manual rotation
        if (!isset($metadata['processing'])) {
            $metadata['processing'] = [];
        }

        $currentRotation = $metadata['processing']['manual_rotation'] ?? 0;
        $metadata['processing']['manual_rotation'] = ($currentRotation + 90) % 360; // 90 degrees clockwise

        return [
            'master' => $rotatedMaster,
            'thumbnail' => $rotatedThumb,
            'metadata' => $metadata
        ];
    }

    /**
     * Rotate a BLOB image 90 degrees clockwise
     */
    private static function rotateBlob(string $imageData): string
    {
        $image = null;
        try {
            $image = new \Imagick();
            $image->readImageBlob($imageData);

            // Rotate 90 degrees clockwise
            $image->rotateImage(new \ImagickPixel('white'), 90);

            $data = $image->getImageBlob();

            if (empty($data)) {
                throw new \RuntimeException("Failed to generate rotated image data");
            }

            return $data;

        } catch (\ImagickException $e) {
            throw new \RuntimeException("Failed to rotate image: " . $e->getMessage());
        } finally {
            if ($image !== null) {
                $image->clear();
                $image->destroy();
            }
        }
    }

    /**
     * Auto-rotate image based on EXIF orientation
     */
    private static function autoRotateImage(\Imagick $image): \Imagick
    {
        $orientation = $image->getImageOrientation();

        switch ($orientation) {
            case \Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateImage(new \ImagickPixel('none'), 180);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage(new \ImagickPixel('none'), 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage(new \ImagickPixel('none'), -90);
                break;
            case \Imagick::ORIENTATION_TOPRIGHT:
                $image->flopImage();
                break;
            case \Imagick::ORIENTATION_BOTTOMLEFT:
                $image->flipImage();
                break;
            case \Imagick::ORIENTATION_LEFTTOP:
                $image->flopImage();
                $image->rotateImage(new \ImagickPixel('none'), -90);
                break;
            case \Imagick::ORIENTATION_RIGHTBOTTOM:
                $image->flopImage();
                $image->rotateImage(new \ImagickPixel('none'), 90);
                break;
        }

        // Set orientation to normal after rotation
        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);

        return $image;
    }

    /**
     * Extract ALL metadata from image before processing
     * Includes EXIF, IPTC, XMP, PNG chunks (tEXt, zTXt, iTXt, etc.)
     */
    private static function extractMetadata(string $inputPath): ?array
    {
        $image = null;
        $metadata = [];

        try {
            $image = new \Imagick($inputPath);

            // For multi-frame images, use only first frame
            if ($image->getNumberImages() > 1) {
                $image = $image->coalesceImages();
                $image->setFirstIterator();
            }

            // Capture original orientation before any processing
            $orientation = $image->getImageOrientation();

            // Get image properties (includes EXIF, IPTC, XMP)
            $properties = $image->getImageProperties();

            // Organize properties by type
            foreach ($properties as $key => $value) {
                // EXIF data
                if (strpos($key, 'exif:') === 0) {
                    $metadata['exif'][substr($key, 5)] = $value;
                }
                // IPTC data
                elseif (strpos($key, 'iptc:') === 0) {
                    $metadata['iptc'][substr($key, 5)] = $value;
                }
                // XMP data
                elseif (strpos($key, 'xmp:') === 0) {
                    $metadata['xmp'][substr($key, 4)] = $value;
                }
                // PNG text chunks (tEXt, zTXt, iTXt)
                elseif (strpos($key, 'png:') === 0) {
                    $chunkName = substr($key, 4);
                    // Store as array to handle multiple chunks of same type
                    if (!isset($metadata['png'][$chunkName])) {
                        $metadata['png'][$chunkName] = [];
                    }
                    $metadata['png'][$chunkName][] = $value;
                }
                // Date/time stamps
                elseif (strpos($key, 'date:') === 0) {
                    $metadata['dates'][substr($key, 5)] = $value;
                }
                // ICC color profile
                elseif (strpos($key, 'icc:') === 0) {
                    $metadata['icc'][substr($key, 4)] = $value;
                }
                // Other metadata
                else {
                    $metadata['other'][$key] = $value;
                }
            }

            // Get image profiles (ICC, EXIF, IPTC as raw data)
            $profiles = $image->getImageProfiles('*', false);
            if (!empty($profiles)) {
                $metadata['profiles'] = $profiles;
            }

            // Get basic image info
            $metadata['image_info'] = [
                'width' => $image->getImageWidth(),
                'height' => $image->getImageHeight(),
                'format' => $image->getImageFormat(),
                'colorspace' => $image->getImageColorspace(),
                'depth' => $image->getImageDepth(),
                'compression' => $image->getImageCompression(),
                'quality' => $image->getImageCompressionQuality(),
            ];

            // Store processing information
            $metadata['processing'] = [
                'original_orientation' => $orientation,
                'auto_rotated' => ($orientation !== \Imagick::ORIENTATION_TOPLEFT && $orientation !== \Imagick::ORIENTATION_UNDEFINED),
            ];

            // Return null if no metadata was found
            return empty($metadata) ? null : $metadata;

        } catch (\ImagickException $e) {
            // If metadata extraction fails, log but don't fail the upload
            error_log("Metadata extraction failed: " . $e->getMessage());
            return null;
        } finally {
            if ($image !== null) {
                $image->clear();
                $image->destroy();
            }
        }
    }
}
