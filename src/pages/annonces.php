<?php
requireLogin();
$user = getCurrentUser();
$db   = getDB();

recordPageVisit($user['id'], 'annonces');

$tagColors = [
    'matériels' => ['bg' => '#dbeafe', 'color' => '#1d4ed8'],
    'aliments'  => ['bg' => '#dcfce7', 'color' => '#15803d'],
    'animaux'   => ['bg' => '#fef9c3', 'color' => '#a16207'],
    'services'  => ['bg' => '#ede9fe', 'color' => '#6d28d9'],
    'divers'    => ['bg' => '#f1f5f9', 'color' => '#475569'],
];

$filterTag = $_GET['tag']  ?? '';
$sortBy    = $_GET['sort'] ?? 'date_desc';

$allowed_sorts = ['date_desc', 'date_asc', 'prix_asc', 'prix_desc'];
if (!in_array($sortBy, $allowed_sorts)) $sortBy = 'date_desc';

$orderSql = match($sortBy) {
    'date_asc'  => 'created_at ASC',
    'prix_asc'  => 'CASE WHEN prix IS NULL THEN 1 ELSE 0 END, prix ASC',
    'prix_desc' => 'CASE WHEN prix IS NULL THEN 1 ELSE 0 END, prix DESC',
    default     => 'created_at DESC',
};

if ($filterTag !== '' && in_array($filterTag, ANNONCE_TAGS)) {
    $stmt = $db->prepare("SELECT * FROM annonces WHERE visible = 1 AND tag = ? ORDER BY $orderSql");
    $stmt->execute([$filterTag]);
} else {
    $filterTag = '';
    $stmt = $db->query("SELECT * FROM annonces WHERE visible = 1 ORDER BY $orderSql");
}
$annonces = $stmt->fetchAll();

// First photo for each annonce
$thumbs = [];
if (!empty($annonces)) {
    $ids = implode(',', array_column($annonces, 'id'));
    $mediaRows = $db->query(
        "SELECT annonce_id, fichier FROM annonce_medias
         WHERE annonce_id IN ($ids) AND type = 'photo'
         ORDER BY annonce_id, position ASC"
    )->fetchAll();
    foreach ($mediaRows as $m) {
        if (!isset($thumbs[$m['annonce_id']])) {
            $thumbs[$m['annonce_id']] = $m['fichier'];
        }
    }
}

$pageTitle = 'Petites annonces — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
    <h1 class="page-title" style="margin:0;">Petites annonces</h1>
    <a href="/dashboard" class="btn btn-sm">&larr; Accueil</a>
</div>

<form method="get" class="annonces-filters">
    <select name="tag" onchange="this.form.submit()">
        <option value="">Toutes les catégories</option>
        <?php foreach (ANNONCE_TAGS as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $filterTag === $t ? 'selected' : '' ?>>
                <?= htmlspecialchars(ucfirst($t)) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="sort" onchange="this.form.submit()">
        <option value="date_desc"  <?= $sortBy === 'date_desc'  ? 'selected' : '' ?>>Plus récentes</option>
        <option value="date_asc"   <?= $sortBy === 'date_asc'   ? 'selected' : '' ?>>Plus anciennes</option>
        <option value="prix_asc"   <?= $sortBy === 'prix_asc'   ? 'selected' : '' ?>>Prix croissant</option>
        <option value="prix_desc"  <?= $sortBy === 'prix_desc'  ? 'selected' : '' ?>>Prix décroissant</option>
    </select>
    <?php if ($filterTag !== ''): ?>
        <a href="/annonces" class="btn btn-sm">&#10005; Effacer le filtre</a>
    <?php endif; ?>
    <span style="font-size:.82rem; color:var(--muted); margin-left:.25rem;">
        <?= count($annonces) ?> annonce<?= count($annonces) !== 1 ? 's' : '' ?>
    </span>
</form>

<?php if (empty($annonces)): ?>
    <div class="card">
        <p style="color:var(--muted); font-style:italic; text-align:center; padding:.5rem 0;">
            Aucune annonce dans cette catégorie.
        </p>
    </div>
<?php else: ?>
<div class="annonces-grid">
    <?php foreach ($annonces as $a):
        $tc = $tagColors[$a['tag']] ?? $tagColors['divers'];
    ?>
    <a href="/annonce?id=<?= $a['id'] ?>" class="annonce-card">
        <div class="annonce-thumb">
            <?php if (isset($thumbs[$a['id']])): ?>
                <img src="/uploads/annonces/<?= htmlspecialchars($thumbs[$a['id']]) ?>"
                     alt="<?= htmlspecialchars($a['titre']) ?>" loading="lazy">
            <?php else: ?>
                &#128247;
            <?php endif; ?>
        </div>
        <div class="annonce-body">
            <span class="annonce-tag" style="background:<?= $tc['bg'] ?>; color:<?= $tc['color'] ?>; align-self:flex-start;">
                <?= htmlspecialchars($a['tag']) ?>
            </span>
            <h3><?= htmlspecialchars($a['titre']) ?></h3>
            <p class="annonce-excerpt"><?= htmlspecialchars(strip_tags($a['texte'])) ?></p>
            <div class="annonce-meta">
                <span><?= date('d/m/Y', strtotime($a['created_at'])) ?></span>
                <?php if ($a['prix'] !== null): ?>
                    <span class="annonce-prix"><?= number_format((float)$a['prix'], 2, ',', '&thinsp;') ?>&thinsp;&euro;</span>
                <?php else: ?>
                    <span style="color:var(--muted);">Prix non communiqué</span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
