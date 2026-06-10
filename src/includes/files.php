<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

const FILES_FORBIDDEN_EXT = [
    'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'exe', 'js', 'mjs', 'html', 'htm', 'sh', 'bash', 'bat', 'cmd',
    'pl', 'py', 'rb', 'asp', 'aspx', 'cgi', 'ts', 'jsx', 'tsx',
    'htaccess', 'htpasswd',
];

function fileStoredPath(string $storedName): string {
    $a = strtolower($storedName[0]);
    $b = strtolower($storedName[1]);
    return FILES_UPLOAD_DIR . '/' . $a . '/' . $b . '/' . $storedName;
}

function fileSanitizeName(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    return strtolower(trim($name, '._-')) ?: 'file';
}

function fileIsExtensionAllowed(string $filename): bool {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return !in_array($ext, FILES_FORBIDDEN_EXT, true);
}

function fileGenerateName(string $originalName, int $userId): string {
    $hash   = md5($originalName . microtime(true) . $userId . mt_rand(0, 9999));
    $prefix = substr($hash, 0, 10);
    return $prefix . '_' . $userId . '_' . fileSanitizeName($originalName);
}

function fileNormalizePath(string $path): string {
    $parts = array_filter(
        explode('/', strtolower($path)),
        fn($p) => $p !== '' && $p !== '.' && $p !== '..'
    );
    return '/' . implode('/', array_slice(array_values($parts), 0, 5));
}

function fileEnsureSubdir(string $storedName): void {
    $a   = strtolower($storedName[0]);
    $b   = strtolower($storedName[1]);
    $dir = FILES_UPLOAD_DIR . '/' . $a . '/' . $b;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function fileUpload(array $file, int $userId, ?int $groupId, string $virtualPath): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Erreur lors du téléversement (code ' . $file['error'] . ').'];
    }
    if (!fileIsExtensionAllowed($file['name'])) {
        return ['ok' => false, 'error' => 'Extension de fichier non autorisée.'];
    }
    $virtualPath = fileNormalizePath($virtualPath);
    $storedName  = fileGenerateName($file['name'], $userId);
    fileEnsureSubdir($storedName);
    $dest = fileStoredPath($storedName);
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Impossible d\'enregistrer le fichier sur le disque.'];
    }
    $mime = mime_content_type($dest) ?: 'application/octet-stream';
    $size = filesize($dest);
    $db   = getDB();
    $db->prepare("
        INSERT INTO files_bo_name (stored_name, original_name, virtual_path, owner_id, group_id, mime_type, file_size)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$storedName, $file['name'], $virtualPath, $userId, $groupId, $mime, $size]);
    return ['ok' => true, 'id' => (int)$db->lastInsertId()];
}

// Upload already-moved file (used by API)
function fileRegister(string $tmpPath, string $originalName, int $userId, ?int $groupId, string $virtualPath): array {
    $virtualPath = fileNormalizePath($virtualPath);
    $storedName  = fileGenerateName($originalName, $userId);
    fileEnsureSubdir($storedName);
    $dest = fileStoredPath($storedName);
    if (!rename($tmpPath, $dest)) {
        return ['ok' => false, 'error' => 'Impossible de déplacer le fichier.'];
    }
    $mime = mime_content_type($dest) ?: 'application/octet-stream';
    $size = filesize($dest);
    $db   = getDB();
    $db->prepare("
        INSERT INTO files_bo_name (stored_name, original_name, virtual_path, owner_id, group_id, mime_type, file_size)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$storedName, $originalName, $virtualPath, $userId, $groupId, $mime, $size]);
    return ['ok' => true, 'id' => (int)$db->lastInsertId()];
}

function fileDelete(int $fileId, int $userId, bool $isAdmin = false): bool {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM files_bo_name WHERE id = ?");
    $stmt->execute([$fileId]);
    $rec = $stmt->fetch();
    if (!$rec) return false;
    if (!$isAdmin && (int)$rec['owner_id'] !== $userId) return false;
    $path = fileStoredPath($rec['stored_name']);
    if (file_exists($path)) {
        unlink($path);
    }
    $db->prepare("DELETE FROM files_bo_name WHERE id = ?")->execute([$fileId]);
    return true;
}

function fileGetById(int $fileId): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM files_bo_name WHERE id = ?");
    $stmt->execute([$fileId]);
    return $stmt->fetch() ?: null;
}

function fileUserCanAccess(array $rec, int $userId): bool {
    if ((int)$rec['owner_id'] === $userId) return true;
    if ($rec['group_id'] === null) return false;
    $db   = getDB();
    $stmt = $db->prepare("SELECT 1 FROM files_bo_group_members WHERE group_id = ? AND user_id = ?");
    $stmt->execute([$rec['group_id'], $userId]);
    return (bool)$stmt->fetch();
}

function fileListDir(int $userId, ?int $groupId, string $virtualPath): array {
    $db          = getDB();
    $virtualPath = fileNormalizePath($virtualPath);

    if ($groupId === null) {
        $stmt = $db->prepare("
            SELECT * FROM files_bo_name
            WHERE owner_id = ? AND group_id IS NULL AND virtual_path = ?
            ORDER BY original_name
        ");
        $stmt->execute([$userId, $virtualPath]);
    } else {
        $stmt = $db->prepare("
            SELECT * FROM files_bo_name
            WHERE group_id = ? AND virtual_path = ?
            ORDER BY original_name
        ");
        $stmt->execute([$groupId, $virtualPath]);
    }
    $files = $stmt->fetchAll();

    $prefix = ($virtualPath === '/') ? '/' : $virtualPath . '/';
    if ($groupId === null) {
        $stmt2 = $db->prepare("
            SELECT DISTINCT virtual_path FROM files_bo_name
            WHERE owner_id = ? AND group_id IS NULL AND virtual_path LIKE ? AND virtual_path != ?
        ");
        $stmt2->execute([$userId, $prefix . '%', $virtualPath]);
    } else {
        $stmt2 = $db->prepare("
            SELECT DISTINCT virtual_path FROM files_bo_name
            WHERE group_id = ? AND virtual_path LIKE ? AND virtual_path != ?
        ");
        $stmt2->execute([$groupId, $prefix . '%', $virtualPath]);
    }
    $allPaths = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $subdirs = [];
    foreach ($allPaths as $p) {
        $rest     = substr($p, strlen($prefix));
        $segments = explode('/', $rest);
        $subdir   = $segments[0];
        if ($subdir !== '' && !in_array($subdir, $subdirs, true)) {
            $subdirs[] = $subdir;
        }
    }
    sort($subdirs);

    return ['files' => $files, 'subdirs' => $subdirs];
}

function fileUserGroups(int $userId): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT g.id, g.name, g.description
        FROM files_bo_groups g
        JOIN files_bo_group_members m ON m.group_id = g.id
        WHERE m.user_id = ?
        ORDER BY g.name
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function fileDecodeRfc2047(string $str): string {
    return preg_replace_callback(
        '/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/',
        function ($m) {
            $decoded = strtoupper($m[2]) === 'B'
                ? base64_decode($m[3])
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return mb_convert_encoding($decoded, 'UTF-8', $m[1]);
        },
        $str
    );
}

function fileMimeIsMedia(string $mime): bool {
    return str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/');
}

function fileHumanSize(int $bytes): string {
    if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 1) . ' Mo';
    if ($bytes >= 1_024)     return round($bytes / 1_024, 1) . ' Ko';
    return $bytes . ' o';
}

function fileBreadcrumb(string $virtualPath): array {
    $crumbs = [];
    if ($virtualPath === '/') return $crumbs;
    $parts = array_filter(explode('/', trim($virtualPath, '/')));
    $acc   = '';
    foreach ($parts as $part) {
        $acc      .= '/' . $part;
        $crumbs[] = ['label' => $part, 'path' => $acc];
    }
    return $crumbs;
}
