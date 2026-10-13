<?php

declare(strict_types=1);

namespace App\Core;

final class FileUploader
{
    private const ALLOWED_IMAGES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function image(?array $file, string $directory, int $maxBytes = 2097152): ?string
    {
        if ($file === null) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The file could not be uploaded.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('The uploaded file is invalid.');
        }

        if ($size <= 0 || $size > $maxBytes) {
            throw new \RuntimeException('The file must be an image smaller than 2 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!isset(self::ALLOWED_IMAGES[$mime])) {
            throw new \RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
        }

        $targetDir = public_path('uploads/' . trim($directory, '/'));
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_IMAGES[$mime];
        $destination = $targetDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $destination)) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        return 'uploads/' . trim($directory, '/') . '/' . $filename;
    }

    public static function deletePublicImage(string $storedPath): void
    {
        $storedPath = str_replace('\\', '/', $storedPath);
        if (preg_match('#^uploads/logos/[a-f0-9]{32}\.(jpg|png|webp)$#', $storedPath) !== 1) {
            return;
        }

        $base = realpath(public_path('uploads/logos'));
        if ($base === false) {
            return;
        }

        $candidate = $base . DIRECTORY_SEPARATOR . basename($storedPath);
        $path = realpath($candidate);
        if ($path === false || !is_file($path)) {
            return;
        }

        $prefix = $base . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            unlink($path);
        }
    }

    /**
     * Store a non-executable document outside the public web root.
     *
     * @return array{original_name: string, stored_path: string, mime_type: string, size_bytes: int}|null
     */
    public static function privateDocument(?array $file, string $directory, int $maxBytes = 8388608): ?array
    {
        if ($file === null) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The file could not be uploaded.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('The uploaded file is invalid.');
        }

        if ($size <= 0 || $size > $maxBytes) {
            throw new \RuntimeException('The file must be smaller than 8 MB.');
        }

        $original = self::safeOriginalName((string) ($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $blocked = ['php', 'phtml', 'phar', 'exe', 'js', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd', 'com', 'msi', 'cgi', 'pl'];
        if ($extension === '' || in_array($extension, $blocked, true) || str_contains($original, '.php')) {
            throw new \RuntimeException('This file type is not allowed.');
        }

        $head = file_get_contents($tmp, false, null, 0, 32);
        if (is_string($head) && preg_match('/<\?(php|=)/i', $head) === 1) {
            throw new \RuntimeException('This file type is not allowed.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $storedExtension = self::extensionForUpload($mime, $extension);
        if ($storedExtension === null) {
            throw new \RuntimeException('Only images, PDF, text, CSV, Office, and ZIP files are allowed.');
        }

        $relativeDir = trim(str_replace('\\', '/', $directory), '/');
        if ($relativeDir === '' || str_contains($relativeDir, '..')) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        $targetDir = BASE_PATH . '/storage/uploads/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $storedExtension;
        $destination = $targetDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        return [
            'original_name' => $original,
            'stored_path' => $relativeDir . '/' . $filename,
            'mime_type' => $mime,
            'size_bytes' => $size,
        ];
    }

    public static function privateAbsolutePath(string $storedPath): ?string
    {
        $storedPath = str_replace('\\', '/', $storedPath);
        if ($storedPath === '' || str_contains($storedPath, '..') || preg_match('/^[a-z0-9][a-z0-9_\/.-]*$/i', $storedPath) !== 1) {
            return null;
        }

        $base = realpath(BASE_PATH . '/storage/uploads');
        if ($base === false) {
            return null;
        }

        $candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
        $path = realpath($candidate);
        if ($path === false) {
            return null;
        }

        $prefix = $base . DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        return $path;
    }

    public static function deletePrivate(string $storedPath): void
    {
        $path = self::privateAbsolutePath($storedPath);
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    private static function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\w.\- ]+/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }

        if (strlen($name) > 180) {
            $name = substr($name, -180);
        }

        return $name;
    }

    private static function extensionForUpload(string $mime, string $extension): ?string
    {
        $exact = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        ];

        if (isset($exact[$mime])) {
            return $exact[$mime];
        }

        if ($mime === 'text/plain' && in_array($extension, ['txt', 'csv'], true)) {
            return $extension;
        }

        if ($mime === 'application/zip' && in_array($extension, ['zip', 'docx', 'xlsx', 'pptx'], true)) {
            return $extension;
        }

        return null;
    }
}
