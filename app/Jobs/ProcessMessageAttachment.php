<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessMessageAttachment implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $messageId,
        public string $redisKey,
        public array $fileData
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $message = Message::findOrFail($this->messageId);
            
            // Get file content from file storage
            if (!Storage::disk('local')->exists($this->redisKey)) {
                Log::error("File content not found in storage for path: {$this->redisKey}");
                return;
            }
            $fileContent = Storage::disk('local')->get($this->redisKey);
            
            // Determine file type
            $fileType = $this->determineFileType($this->fileData['mime_type']);
            
            // Generate unique file path
            $fileName = $this->fileData['original_name'];
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = 'attachments/' . date('Y/m') . '/' . $uniqueFileName;
            
            // Store file
            Storage::disk('public')->put($filePath, $fileContent);
            
            // Generate thumbnail for images and videos
            $thumbnailPath = null;
            if (in_array($fileType, ['image', 'video'])) {
                $thumbnailPath = $this->generateThumbnail($filePath, $fileType, $fileContent);
            }
            
            // Create attachment record
            MessageAttachment::create([
                'message_id' => $message->id,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_type' => $fileType,
                'file_size' => $this->fileData['size'],
                'mime_type' => $this->fileData['mime_type'],
                'thumbnail_path' => $thumbnailPath,
                'duration' => $this->fileData['duration'] ?? null,
            ]);
            
            // Delete temporary file
            Storage::disk('local')->delete($this->redisKey);
            
        } catch (\Exception $e) {
            Log::error("Error processing attachment: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Determine file type from mime type.
     */
    private function determineFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        
        return 'file';
    }

    /**
     * Generate thumbnail for image or video.
     */
    private function generateThumbnail(string $filePath, string $fileType, string $fileContent): ?string
    {
        try {
            $thumbnailPath = 'thumbnails/' . date('Y/m') . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '_thumb.jpg';
            
            if ($fileType === 'image') {
                // Create thumbnail from image
                $image = imagecreatefromstring($fileContent);
                if (!$image) {
                    return null;
                }
                
                $width = imagesx($image);
                $height = imagesy($image);
                $thumbWidth = 200;
                $thumbHeight = 200;
                
                // Calculate aspect ratio
                $aspectRatio = $width / $height;
                if ($aspectRatio > 1) {
                    $thumbHeight = (int) ($thumbWidth / $aspectRatio);
                } else {
                    $thumbWidth = (int) ($thumbHeight * $aspectRatio);
                }
                
                $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
                imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
                
                // Save thumbnail
                $thumbnailContent = '';
                ob_start();
                imagejpeg($thumbnail, null, 85);
                $thumbnailContent = ob_get_clean();
                imagedestroy($image);
                imagedestroy($thumbnail);
                
                Storage::disk('public')->put($thumbnailPath, $thumbnailContent);
                
                return $thumbnailPath;
            }
            
            // For videos, we would need ffmpeg, but for now return null
            // This can be enhanced later with ffmpeg-php or similar
            
        } catch (\Exception $e) {
            Log::error("Error generating thumbnail: " . $e->getMessage());
            return null;
        }
        
        return null;
    }
}
