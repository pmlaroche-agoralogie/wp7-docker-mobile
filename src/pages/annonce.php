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
            <span class="annonce-prix"><?= number_format((float)$annonce['prix'], 2, ',', '&thinsp;') ?>&thinsp;&euro; <span style="font-size:.7em; font-weight:600; vertical-align:middle;">HT</span></span>
        <?php else: ?>
            <span style="color:var(--muted);">Prix non communiqué</span>
        <?php endif; ?>
        <span style="margin-left:auto;">Publi&eacute;e le <?= date('d/m/Y', strtotime($annonce['created_at'])) ?></span>
    </div>

    <h1 class="page-title" style="margin-bottom:.85rem;"><?= htmlspecialchars($annonce['titre']) ?></h1>

    <?php if (!empty($annonce['lieu']) || !empty($annonce['phone_vendeur'])): ?>
    <div style="display:flex; flex-wrap:wrap; gap:.75rem 1.5rem; margin-bottom:1rem; font-size:.92rem; color:var(--muted);">
        <?php if (!empty($annonce['lieu'])): ?>
            <span>&#128205; <?= htmlspecialchars($annonce['lieu']) ?></span>
        <?php endif; ?>
        <?php if (!empty($annonce['phone_vendeur'])): ?>
            <span>&#128222; <a href="tel:<?= htmlspecialchars($annonce['phone_vendeur']) ?>" style="color:inherit;"><?= htmlspecialchars($annonce['phone_vendeur']) ?></a></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($medias)): ?>
    <div class="annonce-medias-grid"
         data-gallery='<?= htmlspecialchars(json_encode(array_values(array_map(
             fn($m) => '/uploads/annonces/' . $m['fichier'],
             array_filter($medias, fn($m) => $m['type'] === 'photo')
         )))) ?>'>
        <?php $photoIndex = 0; foreach ($medias as $m): ?>
            <?php if ($m['type'] === 'photo'): ?>
            <div class="annonce-media-item" data-index="<?= $photoIndex++ ?>" onclick="openLightbox(this)">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
