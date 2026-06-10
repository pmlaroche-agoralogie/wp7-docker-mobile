<?php
requireLogin();
require_once __DIR__ . '/../includes/files.php';

$user   = getCurrentUser();
$fileId = (int)($_GET['id'] ?? 0);
$inline = isset($_GET['inline']);

if (!$fileId) {
    http_response_code(400);
    exit('Paramètre manquant.');
}

$rec = fileGetById($fileId);
if (!$rec) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

if (!fileUserCanAccess($rec, $user['id'])) {
    http_response_code(403);
    exit('Accès refusé.');
}

$path = fileStoredPath($rec['stored_name']);
if (!file_exists($path)) {
    http_response_code(404);
    exit('Fichier physique introuvable.');
}

$mime        = $rec['mime_type'] ?: 'application/octet-stream';
$disposition = $inline ? 'inline' : 'attachment';
$filename    = rawurlencode($rec['original_name']);

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $rec['original_name'] . '"; filename*=UTF-8\'\'' . $filename);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
