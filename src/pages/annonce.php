<?php
requireLogin();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /annonces');
    exit;
}

$stmt = $db->prepare("SELECT * FROM annonces WHERE id = ? AND visible = 1");
$stmt->execute([$id]);
$annonce = $stmt->fetch();

if (!$annonce) {
    http_response_code(404);
    include __DIR__ . '/../pages/404.php';
    exit;
}

$medias = $db->prepare("SELECT * FROM annonce_medias WHERE annonce_id = ? ORDER BY position ASC");
$medias->execute([$id]);
$medias = $medias->fetchAll();

$tagColors = [
    'matériels' => ['bg' => '#dbeafe', 'color' => '#1d4ed8'],
    'aliments'  => ['bg' => '#dcfce7', 'color' => '#15803d'],
    'animaux'   => ['bg' => '#fef9c3', 'color' => '#a16207'],
    'services'  => ['bg' => '#ede9fe', 'color' => '#6d28d9'],
    'divers'    => ['bg' => '#f1f5f9', 'color' => '#475569'],
];
$tc = $tagColors[$annonce['tag']] ?? $tagColors['divers'];

$msgSuccess = false;
$msgErrors  = [];

$currentUser = getCurrentUser();
$userRow     = $db->prepare("SELECT email FROM users WHERE id = ?");
$userRow->execute([$currentUser['id']]);
$userEmail = (string)($userRow->fetchColumn() ?: '');

// ── POST : envoi message ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') $msgErrors[] = 'Le message ne peut pas être vide.';

    if (empty($msgErrors)) {
        $db->prepare("INSERT INTO annonce_messages (annonce_id, nom, email, message) VALUES (?, ?, ?, ?)")
           ->execute([$id, $currentUser['username'], $userEmail, $message]);
        $msgSuccess = true;
    }
}

$pageTitle = htmlspecialchars($annonce['titre']) . ' — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:.75rem;">
    <a href="/annonces" style="color:var(--muted); font-size:.88rem; text-decoration:none;">&larr; Retour aux annonces</a>
</div>

<div class="card">
    <div class="annonce-detail-meta">
        <span class="annonce-tag" style="background:<?= $tc['bg'] ?>; color:<?= $tc['color'] ?>;">
            <?= htmlspecialchars($annonce['tag']) ?>
        </span>
        <?php if ($annonce['prix'] !== null): ?>
            <span class="annonce-prix"><?= number_format((float)$annonce['prix'], 2, ',', '&thinsp;') ?>&thinsp;&euro;</span>
        <?php else: ?>
            <span style="color:var(--muted);">Prix sur demande</span>
        <?php endif; ?>
        <span style="margin-left:auto;">Publi&eacute;e le <?= date('d/m/Y', strtotime($annonce['created_at'])) ?></span>
    </div>

    <h1 class="page-title" style="margin-bottom:.85rem;"><?= htmlspecialchars($annonce['titre']) ?></h1>

    <?php if (!empty($medias)): ?>
    <div class="annonce-medias-grid"
         data-gallery='<?= htmlspecialchars(json_encode(array_map(
             fn($m) => '/uploads/annonces/' . $m['fichier'],
             array_filter($medias, fn($m) => $m['type'] === 'photo')
         ))) ?>'>
        <?php foreach ($medias as $i => $m): ?>
            <?php if ($m['type'] === 'photo'): ?>
            <div class="annonce-media-item" data-index="<?= $i ?>" onclick="openLightbox(this)">
                <img src="/uploads/annonces/<?= htmlspecialchars($m['fichier']) ?>"
                     alt="Photo <?= $i + 1 ?>" loading="lazy">
            </div>
            <?php else: ?>
            <div style="border-radius:8px; overflow:hidden; background:#000;">
                <video controls style="width:100%; max-height:220px; display:block;">
                    <source src="/uploads/annonces/<?= htmlspecialchars($m['fichier']) ?>">
                </video>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="content-body" style="white-space:pre-wrap;"><?= htmlspecialchars($annonce['texte']) ?></div>
</div>

<div class="card">
    <h2>Contacter l&rsquo;annonceur</h2>
    <?php if ($msgSuccess): ?>
        <div class="alert alert-success">Votre message a bien &eacute;t&eacute; envoy&eacute;. L&rsquo;administrateur vous recontactera.</div>
    <?php else: ?>
        <p style="font-size:.85rem; color:var(--muted); margin-bottom:.75rem;">
            Message envoy&eacute; en tant que <strong><?= htmlspecialchars($currentUser['username']) ?></strong>
            <?php if ($userEmail !== ''): ?>(<?= htmlspecialchars($userEmail) ?>)<?php endif; ?>
        </p>
        <?php foreach ($msgErrors as $e): ?>
            <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <form method="post">
            <div class="form-group">
                <label for="msg-message">Votre message <span style="color:red;">*</span></label>
                <textarea id="msg-message" name="message" rows="5"
                          style="width:100%; padding:.65rem .9rem; border:1px solid var(--border); border-radius:8px; font-size:.95rem; font-family:inherit; resize:vertical;"
                          required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Envoyer le message</button>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
