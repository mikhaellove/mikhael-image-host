<?php

namespace App\Services;

use App\Models\Image;
use App\Core\Auth;

class Uploader
{
    private const MAX_FILE_SIZE = 104857600; // 100MB sanity limit
    private const CHUNK_UPLOAD_DIR = '/tmp/vault_chunks/';

    public static function handleUpload(int $userId, array $file, ?string $caption = null): array
    {
        // Verify session and role before accepting binary data
        if (!Auth::isAuthenticated() || Auth::getUserId() !== $userId) {
            throw new \RuntimeException("Unauthorized upload attempt");
        }

        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new \RuntimeException("File exceeds maximum size limit of 100MB");
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("File upload error: " . $file['error']);
        }

        // Detect file type (image or audio)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $isAudio = strpos($mimeType, 'audio/') === 0;

        // Process based on file type
        if ($isAudio) {
            $processed = AudioProcessor::process($file['tmp_name']);
            $mediaType = 'audio';
            $duration = $processed['duration'];
            $fileSize = $processed['file_size'];
            $masterData = $processed['audio_data'];
            $thumbData = $processed['thumbnail'];
            $metadata = null; // Audio doesn't need EXIF metadata
        } else {
            $processed = ImageProcessor::process($file['tmp_name']);
            $mediaType = 'image';
            $duration = null;
            $fileSize = $file['size'];
            $masterData = $processed['master'];
            $thumbData = $processed['thumbnail'];
            $metadata = $processed['metadata'] ?? null;
        }

        // Generate unique slug
        $slug = SlugGenerator::generate();

        // Save to database
        $imageId = Image::create(
            $userId,
            $slug,
            $masterData,
            $thumbData,
            $caption,
            $metadata,
            $mediaType,
            $duration,
            $fileSize,
            $mimeType
        );

        return [
            'id' => $imageId,
            'slug' => $slug,
            'url' => "/v/{$slug}",
        ];
    }

    public static function handleChunkedUpload(int $userId, string $chunkId, int $chunkIndex, int $totalChunks, string $chunkData): ?array
    {
        // Verify session and role
        if (!Auth::isAuthenticated() || Auth::getUserId() !== $userId) {
            throw new \RuntimeException("Unauthorized upload attempt");
        }

        // Ensure chunk directory exists
        if (!is_dir(self::CHUNK_UPLOAD_DIR)) {
            mkdir(self::CHUNK_UPLOAD_DIR, 0700, true);
        }

        // Save chunk
        $chunkPath = self::CHUNK_UPLOAD_DIR . $chunkId . '_' . $chunkIndex;
        file_put_contents($chunkPath, base64_decode($chunkData));

        // If this is the last chunk, reassemble
        if ($chunkIndex === $totalChunks - 1) {
            return self::reassembleChunks($userId, $chunkId, $totalChunks);
        }

        return null; // Still waiting for more chunks
    }

    private static function reassembleChunks(int $userId, string $chunkId, int $totalChunks): array
    {
        $finalPath = self::CHUNK_UPLOAD_DIR . $chunkId . '_final';
        $finalHandle = fopen($finalPath, 'wb');

        if (!$finalHandle) {
            throw new \RuntimeException("Failed to create reassembly file");
        }

        // Reassemble all chunks
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = self::CHUNK_UPLOAD_DIR . $chunkId . '_' . $i;

            if (!file_exists($chunkPath)) {
                throw new \RuntimeException("Missing chunk {$i}");
            }

            $chunkData = file_get_contents($chunkPath);
            fwrite($finalHandle, $chunkData);
            unlink($chunkPath); // Clean up chunk
        }

        fclose($finalHandle);

        // Detect file type (image or audio)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $finalPath);
        finfo_close($finfo);

        $isAudio = strpos($mimeType, 'audio/') === 0;

        // Process based on file type
        if ($isAudio) {
            $processed = AudioProcessor::process($finalPath);
            $mediaType = 'audio';
            $duration = $processed['duration'];
            $fileSize = $processed['file_size'];
            $masterData = $processed['audio_data'];
            $thumbData = $processed['thumbnail'];
            $metadata = null;
        } else {
            $processed = ImageProcessor::process($finalPath);
            $mediaType = 'image';
            $duration = null;
            $fileSize = filesize($finalPath);
            $masterData = $processed['master'];
            $thumbData = $processed['thumbnail'];
            $metadata = $processed['metadata'] ?? null;
        }

        // Generate unique slug
        $slug = SlugGenerator::generate();

        // Save to database
        $imageId = Image::create(
            $userId,
            $slug,
            $masterData,
            $thumbData,
            null,
            $metadata,
            $mediaType,
            $duration,
            $fileSize,
            $mimeType
        );

        // Clean up final file
        unlink($finalPath);

        return [
            'id' => $imageId,
            'slug' => $slug,
            'url' => "/v/{$slug}",
        ];
    }
}
