<?php
requireLogin();
$user      = getCurrentUser();
$pageTitle = 'Cotations — ' . SITE_NAME;

recordPageVisit($user['id'], 'cotations');

$cotationsDir = '/var/www/html/media/cotations';
$pdfs = [];
if (is_dir($cotationsDir)) {
    foreach (glob($cotationsDir . '/*.pdf') as $file) {
        $pdfs[] = ['name' => basename($file), 'mtime' => filemtime($file)];
    }
    usort($pdfs, fn($a, $b) => $b['mtime'] - $a['mtime']);
}

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">&#128196; Cotations</h1>
    <a href="/dashboard" class="btn btn-sm">&larr; Tableau de bord</a>
</div>

<?php if (empty($pdfs)): ?>
    <div class="card" style="text-align:center; padding:2.5rem 1rem; color:var(--muted);">
        <div style="font-size:2.5rem; margin-bottom:.75rem;">&#128196;</div>
        <p>Aucune cotation disponible pour le moment.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0; overflow:hidden;">
        <?php foreach ($pdfs as $i => $pdf): ?>
        <a href="/media/cotations/<?= rawurlencode($pdf['name']) ?>" target="_blank" rel="noopener"
           style="display:flex; align-items:center; gap:.75rem; padding:.9rem 1.1rem;
                  text-decoration:none; color:inherit;
                  border-bottom:<?= $i < count($pdfs) - 1 ? '1px solid var(--border)' : 'none' ?>;">
            <span style="font-size:1.3rem; flex-shrink:0;">&#128462;</span>
            <div style="flex:1; min-width:0;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.88rem; font-weight:500;">
                    <?= htmlspecialchars($pdf['name']) ?>
                </div>
                <div style="font-size:.72rem; color:var(--muted);">
                    <?= date('d/m/Y', $pdf['mtime']) ?>
                </div>
            </div>
            <span style="color:var(--primary); font-size:.9rem;">&#8599;</span>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
