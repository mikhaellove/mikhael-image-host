<?php

namespace App\Controllers;

use App\Core\AuthMiddleware;
use App\Services\Uploader;

class ApiController
{
    public function handleUpload(): void
    {
        // Require Bearer token authentication
        $userId = AuthMiddleware::requireApiAuth();

        header('Content-Type: application/json');

        try {
            // Check if this is a chunked upload
            $isChunked = isset($_POST['chunk_id']);

            if ($isChunked) {
                $chunkId = $_POST['chunk_id'] ?? '';
                $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
                $totalChunks = (int)($_POST['total_chunks'] ?? 0);
                $chunkData = $_POST['chunk_data'] ?? '';

                $result = Uploader::handleChunkedUpload($userId, $chunkId, $chunkIndex, $totalChunks, $chunkData);

                if ($result === null) {
                    // Still waiting for more chunks
                    echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Chunk received']);
                } else {
                    // Upload complete
                    echo json_encode(['success' => true, 'status' => 'complete', 'data' => $result]);
                }
            } else {
                // Standard single-file upload
                $caption = $_POST['caption'] ?? null;

                if (!isset($_FILES['image'])) {
                    throw new \RuntimeException("No image file provided");
                }

                $result = Uploader::handleUpload($userId, $_FILES['image'], $caption);
                echo json_encode(['success' => true, 'data' => $result]);
            }

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
