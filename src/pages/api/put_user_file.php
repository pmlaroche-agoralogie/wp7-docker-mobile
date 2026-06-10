<?php
/**
 * API partenaire : dépôt de fichier vers un ou plusieurs destinataires.
 *
 * POST /api/put_user_file
 *   api_key          string   Clé secrète
 *   files            file     Fichier binaire
 *   destinataires[]  string[] Emails des destinataires (utilisateurs existants)
 *   sous_dossier     string   Dossier virtuel cible (ex: "Documents")
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/files.php';

header('Content-Type: application/json; charset=UTF-8');

$logFile = FILES_UPLOAD_DIR . '/api_logs_' . date('Y-m-d') . '.txt';

function apiLog(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function apiError(int $code, string $msg, string $logMsg = ''): never {
    http_response_code($code);
    apiLog('ERROR ' . $code . ' — ' . ($logMsg ?: $msg) . ' | IP=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Méthode non autorisée.');
}

// Vérification clé API
$apiKey = trim($_POST['api_key'] ?? '');
if ($apiKey === '' || !hash_equals(FILES_API_KEY, $apiKey)) {
    apiError(401, 'Clé API invalide.');
}

// Fichier
if (empty($_FILES['files']) || $_FILES['files']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['files']['error'] ?? -1;
    apiError(400, 'Fichier manquant ou erreur d\'upload (code ' . $code . ').');
}

$originalName = fileDecodeRfc2047($_FILES['files']['name']);
$originalName = basename($originalName);

if (!fileIsExtensionAllowed($originalName)) {
    apiError(400, 'Extension de fichier non autorisée.', 'Extension refusée : ' . $originalName);
}

// Destinataires
$destinataires = $_POST['destinataires'] ?? [];
if (!is_array($destinataires) || empty($destinataires)) {
    apiError(400, 'Aucun destinataire fourni.');
}

// Sous-dossier
$sousDossier = trim($_POST['sous_dossier'] ?? '');
$virtualPath = fileNormalizePath($sousDossier ?: '/');
$depth       = $virtualPath === '/' ? 0 : count(array_filter(explode('/', trim($virtualPath, '/'))));
if ($depth > 5) {
    apiError(400, 'Profondeur de sous-dossier maximale (5 niveaux) dépassée.');
}

// Résolution des destinataires
$db       = getDB();
$resolved = [];
foreach ($destinataires as $email) {
    $email = trim((string)$email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiLog('WARN destinataire email invalide : ' . $email);
        continue;
    }
    $stmt = $db->prepare("SELECT id, email FROM users WHERE email = ? AND active = 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u) {
        apiLog('WARN destinataire introuvable : ' . $email);
        continue;
    }
    $resolved[] = $u;
}

if (empty($resolved)) {
    apiError(400, 'Aucun destinataire valide trouvé.');
}

// Dépôt du fichier pour chaque destinataire
$tmpPath = $_FILES['files']['tmp_name'];
$results = [];
$errors  = [];

// Copier le fichier tmp pour chaque destinataire (sauf le dernier qui utilise le move)
$total = count($resolved);
foreach ($resolved as $idx => $recipient) {
    $isLast    = ($idx === $total - 1);
    $targetTmp = $isLast ? $tmpPath : tempnam(sys_get_temp_dir(), 'fbo_');

    if (!$isLast) {
        if (!copy($tmpPath, $targetTmp)) {
            $errors[] = $recipient['email'] . ' : copie temporaire impossible';
            continue;
        }
    }

    $storedName = fileGenerateName($originalName, $recipient['id']);
    fileEnsureSubdir($storedName);
    $dest = fileStoredPath($storedName);

    if ($isLast) {
        $moved = move_uploaded_file($tmpPath, $dest);
    } else {
        $moved = rename($targetTmp, $dest);
    }

    if (!$moved) {
        $errors[] = $recipient['email'] . ' : impossible de déplacer le fichier';
        continue;
    }

    $mime = mime_content_type($dest) ?: 'application/octet-stream';
    $size = filesize($dest);

    $db->prepare("
        INSERT INTO files_bo_name (stored_name, original_name, virtual_path, owner_id, group_id, mime_type, file_size)
        VALUES (?, ?, ?, ?, NULL, ?, ?)
    ")->execute([$storedName, $originalName, $virtualPath, $recipient['id'], $mime, $size]);

    $results[]  = $recipient['email'];
    apiLog('OK fichier "' . $originalName . '" → ' . $recipient['email'] . ' dans ' . $virtualPath);
}

$response = ['ok' => true, 'deposited' => $results];
if (!empty($errors)) {
    $response['warnings'] = $errors;
}

echo json_encode($response);
exit;
