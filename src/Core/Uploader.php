<?php
declare(strict_types=1);

namespace App\Core;

use finfo;
use RuntimeException;
use App\Exceptions\ValidationException;

final class Uploader
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];
    private const MAX_BYTES = UPLOAD_MAX_BYTES;

    public function store(array $file, string $subdir = 'rooms'): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            // Translate PHP's numeric codes into something a user can act on.
            $reason = match ((int) $file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is too large.',
                UPLOAD_ERR_NO_FILE                        => 'Please choose an image to upload.',
                UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Please try again.',
                default                                   => 'The image could not be uploaded.',
            };
            throw new ValidationException(['image' => $reason]);
        }

        // Confirm PHP actually received this through a multipart upload. Without
        // it, a caller could be tricked into treating an arbitrary server path as
        // an uploaded file.
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException(['image' => 'Invalid upload.']);
        }

        if ($file['size'] > self::MAX_BYTES) {
            throw new ValidationException(['image' => 'File size exceeds maximum limit of 2 MB.']);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        if (!isset(self::ALLOWED[$mime])) {
            throw new ValidationException(['image' => 'Only JPG, PNG, or WebP image formats are allowed.']);
        }

        if (@getimagesize($file['tmp_name']) === false) {
            throw new ValidationException(['image' => 'Uploaded file is not a valid or corrupt image.']);
        }

        // Whitelist the destination folder. `trim($subdir, '/')` alone does not
        // stop "../../" — a caller passing request data could have written the
        // file anywhere on disk.
        $subdir = trim($subdir, '/');
        if (!preg_match('/^[a-z0-9_-]+$/i', $subdir)) {
            throw new RuntimeException('Invalid upload destination.');
        }

        $targetDir = UPLOAD_PATH . '/' . $subdir;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $destPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Failed to save uploaded file to destination directory.');
        }

        chmod($destPath, 0644);
        return 'uploads/' . trim($subdir, '/') . '/' . $filename;
    }
}
