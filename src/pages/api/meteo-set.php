<?php
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = trim($data['code_insee'] ?? '');

// INSEE codes: 5 chars, digits or letters (for DOM-TOM like 2A, 2B)
if (!preg_match('/^[0-9A-Za-z]{5}$/', $code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Code INSEE invalide']);
    exit;
}

$code = strtoupper($code);

$db = getDB();
$db->prepare("UPDATE users SET meteo_code_insee = ? WHERE id = ?")
   ->execute([$code, $user['id']]);

echo json_encode(['ok' => true, 'code_insee' => $code]);
