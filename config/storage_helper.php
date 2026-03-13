<?php

function getUploadStorageConfig(PDO $pdo, int $userId): array
{
    $settings = loadSettingsMap($pdo, $userId);

    $driver = strtolower(trim((string)($settings['storage_driver'] ?? 'local')));
    if (!in_array($driver, ['local', 's3'], true)) {
        $driver = 'local';
    }

    $localRoot = trim((string)($settings['storage_local_root'] ?? '../private_uploads'));
    $localRoot = $localRoot !== '' ? $localRoot : '../private_uploads';
    if ($localRoot === 'storage/uploads') {
        $localRoot = '../private_uploads';
    }

    return [
        'driver' => $driver,
        'local_root' => $localRoot,
        's3_bucket' => (string)($settings['s3_bucket'] ?? ''),
        's3_region' => (string)($settings['s3_region'] ?? ''),
        's3_endpoint' => (string)($settings['s3_endpoint'] ?? ''),
        's3_prefix' => trim((string)($settings['s3_prefix'] ?? ''), '/'),
        'upload_max_mb' => max(1, (int)($settings['upload_max_mb'] ?? 5)),
        'upload_max_width' => max(320, (int)($settings['upload_max_width'] ?? 1920)),
        'upload_max_height' => max(320, (int)($settings['upload_max_height'] ?? 1920)),
        'upload_jpeg_quality' => max(55, min(95, (int)($settings['upload_jpeg_quality'] ?? 82))),
        'upload_require_av' => filter_var($settings['upload_require_av'] ?? '0', FILTER_VALIDATE_BOOLEAN),
    ];
}

function findAvailableAvScanner(): ?string
{
    $candidates = ['clamscan', 'clamdscan'];
    foreach ($candidates as $bin) {
        $result = @shell_exec("where {$bin} 2>NUL");
        if (is_string($result) && trim($result) !== '') {
            return $bin;
        }
    }
    return null;
}

function scanFileForVirus(string $absolutePath): array
{
    $scanner = findAvailableAvScanner();
    if ($scanner === null) {
        return ['scanned' => false, 'clean' => true, 'status' => 'scanner_not_found'];
    }

    $cmd = $scanner . ' --no-summary ' . escapeshellarg($absolutePath) . ' 2>&1';
    $out = [];
    $code = 2;
    @exec($cmd, $out, $code);

    if ($code === 0) {
        return ['scanned' => true, 'clean' => true, 'status' => 'clean'];
    }
    if ($code === 1) {
        return ['scanned' => true, 'clean' => false, 'status' => 'infected', 'message' => implode("\n", $out)];
    }

    return ['scanned' => true, 'clean' => false, 'status' => 'scan_error', 'message' => implode("\n", $out)];
}

function normalizeImageAndSave(string $srcPath, string $mimeType, string $destPath, int $maxW, int $maxH, int $jpegQuality): array
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('GD extension is required for image processing.');
    }

    $bytes = @file_get_contents($srcPath);
    if ($bytes === false) {
        throw new RuntimeException('Unable to read uploaded image.');
    }

    $img = @imagecreatefromstring($bytes);
    if (!$img) {
        throw new RuntimeException('Unsupported or corrupted image.');
    }

    $srcW = imagesx($img);
    $srcH = imagesy($img);
    $scale = min(1, $maxW / max(1, $srcW), $maxH / max(1, $srcH));
    $dstW = max(1, (int)floor($srcW * $scale));
    $dstH = max(1, (int)floor($srcH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    if (!$dst) {
        imagedestroy($img);
        throw new RuntimeException('Unable to allocate image buffer.');
    }

    // Preserve alpha for png/webp
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefill($dst, 0, 0, $transparent);

    imagecopyresampled($dst, $img, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

    $outMime = 'image/jpeg';
    $ok = imagejpeg($dst, $destPath, $jpegQuality);

    if ($mimeType === 'image/png') {
        $outMime = 'image/png';
        $ok = imagepng($dst, $destPath, 6);
    } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
        $outMime = 'image/webp';
        $ok = imagewebp($dst, $destPath, $jpegQuality);
    }

    imagedestroy($img);
    imagedestroy($dst);

    if (!$ok) {
        throw new RuntimeException('Failed to save optimized image.');
    }

    return [
        'mime' => $outMime,
        'width' => $dstW,
        'height' => $dstH,
        'size' => (int)filesize($destPath),
        'sha256' => hash_file('sha256', $destPath) ?: '',
    ];
}

function storeImageByDriver(array $cfg, string $relativeKey, string $tmpOptimizedPath): array
{
    if ($cfg['driver'] === 's3') {
        // Placeholder for future S3 integration.
        throw new RuntimeException('S3 storage is configured but uploader is not enabled yet. Switch storage_driver=local or complete S3 adapter.');
    }

    $localRoot = trim((string)$cfg['local_root'], '/\\');
    $baseAbs = realpath(__DIR__ . '/..');
    if ($baseAbs === false) {
        throw new RuntimeException('Cannot resolve application root path.');
    }

    $absDir = $baseAbs . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $localRoot);
    if (!is_dir($absDir) && !@mkdir($absDir, 0777, true)) {
        throw new RuntimeException('Unable to create upload root directory.');
    }

    $targetAbs = $absDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeKey);
    $targetDir = dirname($targetAbs);
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    if (!@rename($tmpOptimizedPath, $targetAbs)) {
        if (!@copy($tmpOptimizedPath, $targetAbs)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }
        @unlink($tmpOptimizedPath);
    }

    return [
        'disk' => 'local',
        'storage_path' => $relativeKey,
        'public_url' => null,
        'absolute_path' => $targetAbs,
    ];
}

function resolveStoredFileAbsolutePath(string $storagePath, array $cfg): ?string
{
    $localRoot = trim((string)($cfg['local_root'] ?? '../private_uploads'), '/\\');
    $baseAbs = realpath(__DIR__ . '/..');
    if ($baseAbs === false || $storagePath === '') {
        return null;
    }
    return $baseAbs . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $localRoot) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
}
