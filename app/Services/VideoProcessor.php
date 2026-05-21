<?php

namespace App\Services;

class VideoProcessor
{
    private const MAX_FILE_SIZE = 262144000; // 250MB

    private const SUPPORTED_MIMES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/ogg',
        'video/3gpp',
    ];

    public static function process(string $inputPath): array
    {
        $fileSize = filesize($inputPath);
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Video file too large. Maximum size is 250MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $inputPath);
        finfo_close($finfo);

        if (!in_array($mimeType, self::SUPPORTED_MIMES)) {
            throw new \RuntimeException('Unsupported video format. Supported: MP4, WebM, MOV, AVI, MKV');
        }

        $videoData = file_get_contents($inputPath);
        if ($videoData === false) {
            throw new \RuntimeException('Failed to read video file');
        }

        $duration = self::extractDuration($inputPath);
        $thumbnail = self::generateThumbnail($inputPath);

        return [
            'video_data' => $videoData,
            'thumbnail' => $thumbnail,
            'mime_type' => $mimeType,
            'duration' => $duration,
            'file_size' => $fileSize,
        ];
    }

    private static function extractDuration(string $filePath): ?int
    {
        $cmd = 'ffprobe -v quiet -print_format json -show_format ' . escapeshellarg($filePath) . ' 2>/dev/null';
        $output = shell_exec($cmd);
        if ($output) {
            $data = json_decode($output, true);
            if (isset($data['format']['duration'])) {
                return (int)round((float)$data['format']['duration']);
            }
        }
        return null;
    }

    private static function generateThumbnail(string $filePath): string
    {
        $tmpThumb = tempnam(sys_get_temp_dir(), 'vault_vthumb_') . '.jpg';
        $cmd = 'ffmpeg -i ' . escapeshellarg($filePath)
            . ' -ss 00:00:01 -vframes 1'
            . ' -vf "scale=600:400:force_original_aspect_ratio=decrease,pad=600:400:(ow-iw)/2:(oh-ih)/2"'
            . ' -y ' . escapeshellarg($tmpThumb) . ' 2>/dev/null';
        exec($cmd, $out, $returnCode);

        if ($returnCode === 0 && file_exists($tmpThumb) && filesize($tmpThumb) > 0) {
            $data = file_get_contents($tmpThumb);
            unlink($tmpThumb);
            return $data;
        }

        if (file_exists($tmpThumb)) {
            unlink($tmpThumb);
        }

        return self::generatePlaceholderThumbnail();
    }

    private static function generatePlaceholderThumbnail(): string
    {
        $width = 600;
        $height = 400;
        $img = imagecreatetruecolor($width, $height);

        $bgColor = imagecolorallocate($img, 30, 30, 30);
        $iconColor = imagecolorallocate($img, 255, 138, 0);
        $textColor = imagecolorallocate($img, 200, 200, 200);

        imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);

        // Play triangle
        $cx = (int)($width / 2);
        $cy = (int)($height / 2);
        $s = 55;
        imagefilledpolygon($img, [$cx - $s, $cy - $s, $cx - $s, $cy + $s, $cx + $s, $cy], $iconColor);

        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (file_exists($font)) {
            imagettftext($img, 14, 0, $cx - 28, $cy + $s + 30, $textColor, $font, 'VIDEO');
        } else {
            imagestring($img, 5, $cx - 20, $cy + $s + 20, 'VIDEO', $textColor);
        }

        ob_start();
        imagejpeg($img, null, 85);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }
}
