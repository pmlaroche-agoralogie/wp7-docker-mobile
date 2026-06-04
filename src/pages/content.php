<?php
// $slug is set by the router before including this file
$db = getDB();

$stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    include __DIR__ . '/../pages/404.php';
    exit;
}

$blocksStmt = $db->prepare("SELECT * FROM page_blocks WHERE page_slug = ? ORDER BY position ASC");
$blocksStmt->execute([$slug]);
$blocks = $blocksStmt->fetchAll();

$pageTitle = $page['title'] . ' — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';

function youtubeId(string $url): ?string {
    if (preg_match('/[?&]v=([^&]+)/', $url, $m)) return $m[1];
    if (preg_match('/youtu\.be\/([^?]+)/', $url, $m)) return $m[1];
    if (preg_match('/youtube\.com\/embed\/([^?\/]+)/', $url, $m)) return $m[1];
    return null;
}

function vimeoId(string $url): ?string {
    if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m)) return $m[1];
    return null;
}
?>

<div class="page-header">
    <h1 class="page-title"><?= htmlspecialchars($page['title']) ?></h1>
    <?php if (isAdmin()): ?>
        <a href="/admin/pages?edit=<?= urlencode($slug) ?>" class="btn btn-sm" style="margin-left:auto;">
            &#9998; G&eacute;rer les blocs
        </a>
    <?php endif; ?>
</div>

<?php if (empty($blocks)): ?>
    <div class="card"><p style="color:var(--muted); font-style:italic;">Contenu &agrave; venir.</p></div>
<?php endif; ?>

<?php foreach ($blocks as $block): ?>

<?php if ($block['type'] === 'html'): ?>
    <div class="card content-body">
        <?= $block['content'] ?>
    </div>

<?php elseif ($block['type'] === 'gallery'): ?>
    <?php $images = json_decode($block['content'], true) ?? []; ?>
    <?php if ($images): ?>
    <div class="card" style="padding:1rem;">
        <div class="gallery-grid"
             data-gallery='<?= htmlspecialchars(json_encode(array_map(fn($img) => '/media/' . $img['file'], $images))) ?>'>
            <?php foreach ($images as $i => $img): ?>
            <div class="gallery-item" data-index="<?= $i ?>" onclick="openLightbox(this)">
                <img src="/media/<?= htmlspecialchars($img['file']) ?>"
                     alt="<?= htmlspecialchars($img['caption'] ?? '') ?>"
                     loading="lazy">
                <?php if (!empty($img['caption'])): ?>
                    <p class="gallery-caption"><?= htmlspecialchars($img['caption']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php elseif ($block['type'] === 'video'): ?>
    <?php $data = json_decode($block['content'], true) ?? []; ?>
    <?php $src = $data['src'] ?? ''; ?>
    <?php if ($src): ?>
    <div class="card">
        <?php if (!empty($data['title'])): ?>
            <h2 style="margin-bottom:.75rem;"><?= htmlspecialchars($data['title']) ?></h2>
        <?php endif; ?>
        <?php if ($ytId = youtubeId($src)): ?>
            <div class="video-wrap">
                <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>"
                        frameborder="0" allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                </iframe>
            </div>
        <?php elseif ($vmId = vimeoId($src)): ?>
            <div class="video-wrap">
                <iframe src="https://player.vimeo.com/video/<?= htmlspecialchars($vmId) ?>"
                        frameborder="0" allowfullscreen>
                </iframe>
            </div>
        <?php else: ?>
            <video controls style="width:100%; border-radius:8px; background:#000;">
                <source src="<?= htmlspecialchars($src) ?>">
                Votre navigateur ne supporte pas la balise vid&eacute;o.
            </video>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
