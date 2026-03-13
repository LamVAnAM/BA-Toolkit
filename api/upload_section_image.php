<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/storage_helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonError('Method Not Allowed', 405);
}

requireAuth();
verifyCsrfToken();
$userId = (int)getCurrentUserId();

try {
    $deptId = (int)($_POST['department_id'] ?? 0);
    $sectionId = trim((string)($_POST['section_id'] ?? ''));
    if ($deptId <= 0 || $sectionId === '') {
        jsonError('department_id and section_id are required', 400);
    }

    $chk = $pdo->prepare("SELECT id FROM departments WHERE id = ? AND user_id = ?");
    $chk->execute([$deptId, $userId]);
    if (!$chk->fetch()) {
        jsonError('Department not found or access denied', 404);
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        jsonError('image file is required', 400);
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonError('Upload failed with error code ' . (int)$file['error'], 400);
    }

    $cfg = getUploadStorageConfig($pdo, $userId);
    $maxBytes = (int)$cfg['upload_max_mb'] * 1024 * 1024;
    if ((int)$file['size'] > $maxBytes) {
        jsonError('File too large. Max ' . (int)$cfg['upload_max_mb'] . 'MB', 400);
    }

    $tmp = (string)$file['tmp_name'];
    if (!is_uploaded_file($tmp) && !is_file($tmp)) {
        jsonError('Invalid uploaded file', 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        jsonError('Only JPEG, PNG, WEBP are allowed', 400);
    }

    $imgInfo = @getimagesize($tmp);
    if (!$imgInfo) {
        jsonError('Invalid image content', 400);
    }

    $scan = scanFileForVirus($tmp);
    if (($scan['clean'] ?? false) !== true) {
        jsonError('File rejected by antivirus scanner', 400, ['scan' => $scan]);
    }
    if (($cfg['upload_require_av'] ?? false) && !($scan['scanned'] ?? false)) {
        jsonError('Antivirus scanner is required but not available on server', 400);
    }

    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
    $baseName = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $relativeKey = implode('/', [
        'files',
        (string)$userId,
        (string)$deptId,
        preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sectionId),
        $baseName . '.' . $ext
    ]);

    $optimizedTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $optimizedMeta = normalizeImageAndSave(
        $tmp,
        $mime,
        $optimizedTmp,
        (int)$cfg['upload_max_width'],
        (int)$cfg['upload_max_height'],
        (int)$cfg['upload_jpeg_quality']
    );

    $stored = storeImageByDriver($cfg, $relativeKey, $optimizedTmp);

    $ins = $pdo->prepare(
        "INSERT INTO section_files (user_id, department_id, section_id, storage_disk, storage_path, public_url, original_name, mime_type, file_size, width, height, checksum_sha256, av_scanned, av_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
        $userId,
        $deptId,
        $sectionId,
        $stored['disk'],
        $stored['storage_path'],
        $stored['public_url'],
        (string)($file['name'] ?? ''),
        $optimizedMeta['mime'],
        (int)$optimizedMeta['size'],
        (int)$optimizedMeta['width'],
        (int)$optimizedMeta['height'],
        (string)$optimizedMeta['sha256'],
        ($scan['scanned'] ?? false) ? 1 : 0,
        (string)($scan['status'] ?? 'unknown')
    ]);

    $id = (int)$pdo->lastInsertId();
    $rowStmt = $pdo->prepare("SELECT * FROM section_files WHERE id = ? AND user_id = ?");
    $rowStmt->execute([$id, $userId]);
    $row = $rowStmt->fetch();

    jsonResponse([
        'success' => true,
        'item' => $row,
        'scan' => $scan
    ]);
} catch (Throwable $e) {
    jsonError($e->getMessage(), 500);
}
