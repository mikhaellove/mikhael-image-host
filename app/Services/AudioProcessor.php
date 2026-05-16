<?php

namespace App\Services;

class AudioProcessor
{
    // Maximum audio file size: 50MB
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;

    // Supported audio MIME types
    private const SUPPORTED_MIMES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/ogg',
        'audio/mp4',
        'audio/x-m4a',
        'audio/flac',
        'audio/x-flac'
    ];

    /**
     * Process uploaded audio file
     */
    public static function process(string $inputPath): array
    {
        // Validate file size
        $fileSize = filesize($inputPath);
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Audio file too large. Maximum size is 50MB.');
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $inputPath);
        finfo_close($finfo);

        if (!in_array($mimeType, self::SUPPORTED_MIMES)) {
            throw new \RuntimeException('Unsupported audio format. Supported: MP3, WAV, OGG, M4A, FLAC');
        }

        // Read audio file into blob
        $audioData = file_get_contents($inputPath);
        if ($audioData === false) {
            throw new \RuntimeException('Failed to read audio file');
        }

        // Try to extract duration
        $duration = self::extractDuration($inputPath);

        // Generate thumbnail (generic audio icon)
        $thumbnail = self::generateThumbnail();

        return [
            'audio_data' => $audioData,
            'thumbnail' => $thumbnail,
            'mime_type' => $mimeType,
            'duration' => $duration,
            'file_size' => $fileSize
        ];
    }

    /**
     * Extract audio duration in seconds
     * Returns null since we don't use external dependencies
     */
    private static function extractDuration(string $filePath): ?int
    {
        // Duration extraction requires external tools (ffprobe/getID3)
        // which we're not including to keep the app dependency-free
        // Audio will still work fine, just won't display duration
        return null;
    }

    /**
     * Generate generic audio thumbnail
     */
    private static function generateThumbnail(): string
    {
        $width = 600;
        $height = 400;

        $img = imagecreatetruecolor($width, $height);

        // Dark background
        $bgColor = imagecolorallocate($img, 45, 45, 45);
        $iconColor = imagecolorallocate($img, 100, 181, 246); // Light blue
        $textColor = imagecolorallocate($img, 200, 200, 200);

        imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);

        // Draw musical note symbol (♪) or "AUDIO" text
        $fontSize = 80;
        $centerX = $width / 2;
        $centerY = $height / 2;

        // Try to use a font if available, otherwise use built-in
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (file_exists($font)) {
            // Draw "♪" musical note
            imagettftext($img, $fontSize, 0, $centerX - 30, $centerY + 30, $iconColor, $font, '♪');
        } else {
            // Fallback: draw simple text
            $text = "AUDIO";
            imagestring($img, 5, $centerX - 40, $centerY - 10, $text, $textColor);
        }

        // Convert to PNG blob
        ob_start();
        imagepng($img, null, 6);
        $thumbnailData = ob_get_clean();
        imagedestroy($img);

        return $thumbnailData;
    }


    /**
     * Format duration from seconds to MM:SS or HH:MM:SS
     */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }
}
