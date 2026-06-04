<?php
requireAdmin();
$db = getDB();

// ── POST actions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']   ?? '';
    $blockId = (int)($_POST['block_id'] ?? 0);
    $editSlug = $_POST['page_slug'] ?? '';

    if ($action === 'update_title' && $editSlug) {
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $db->prepare("UPDATE pages SET title = ?, updated_at = datetime('now') WHERE slug = ?")
               ->execute([$title, $editSlug]);
        }
        header("Location: /admin/pages?edit=" . urlencode($editSlug) . "&saved=1");
        exit;
    }

    if ($action === 'delete_block' && $blockId) {
        $row = $db->prepare("SELECT page_slug FROM page_blocks WHERE id = ?");
        $row->execute([$blockId]);
        $r = $row->fetch();
        $db->prepare("DELETE FROM page_blocks WHERE id = ?")->execute([$blockId]);
        header("Location: /admin/pages?edit=" . urlencode($r['page_slug'] ?? $editSlug));
        exit;
    }

    if (($action === 'move_up' || $action === 'move_down') && $blockId) {
        $row = $db->prepare("SELECT * FROM page_blocks WHERE id = ?");
        $row->execute([$blockId]);
        $current = $row->fetch();
        $pageSlug = $current['page_slug'];

        $all = $db->prepare("SELECT id, position FROM page_blocks WHERE page_slug = ? ORDER BY position ASC");
        $all->execute([$pageSlug]);
        $all = $all->fetchAll();

        $idx = null;
        foreach ($all as $i => $b) {
            if ((int)$b['id'] === $blockId) { $idx = $i; break; }
        }

        $swapIdx = ($action === 'move_up') ? $idx - 1 : $idx + 1;
        if ($idx !== null && isset($all[$swapIdx])) {
            $posA = $all[$idx]['position'];
            $posB = $all[$swapIdx]['position'];
            // Swap positions (use a temp value to avoid unique constraint issues)
            $db->prepare("UPDATE page_blocks SET position = -9999 WHERE id = ?")->execute([$blockId]);
            $db->prepare("UPDATE page_blocks SET position = ? WHERE id = ?")->execute([$posA, $all[$swapIdx]['id']]);
            $db->prepare("UPDATE page_blocks SET position = ? WHERE id = ?")->execute([$posB, $blockId]);
        }
        header("Location: /admin/pages?edit=" . urlencode($pageSlug));
        exit;
    }
}

// ── GET ──────────────────────────────────────────────────
$saved    = isset($_GET['saved']);
$editSlug = $_GET['edit'] ?? null;
$editPage = null;
$blocks   = [];

if ($editSlug) {
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
    $stmt->execute([$editSlug]);
    $editPage = $stmt->fetch();

    if ($editPage) {
        $bstmt = $db->prepare("SELECT * FROM page_blocks WHERE page_slug = ? ORDER BY position ASC");
        $bstmt->execute([$editSlug]);
        $blocks = $bstmt->fetchAll();
    } else {
        $editSlug = null;
    }
}

$allPages = $db->query("SELECT id, slug, title, updated_at FROM pages ORDER BY id")->fetchAll();

$pageTitle = 'Pages de contenu — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';

$typeLabels = ['html' => 'HTML', 'gallery' => 'Galerie', 'video' => 'Vidéo'];
$typeIcons  = ['html' => '&#128196;', 'gallery' => '&#128247;', 'video' => '&#127909;'];

function blockPreview(array $block): string {
    if ($block['type'] === 'html') {
        $text = strip_tags($block['content']);
        return htmlspecialchars(mb_substr($text, 0, 60)) . (mb_strlen($text) > 60 ? '…' : '');
    }
    if ($block['type'] === 'gallery') {
        $imgs = json_decode($block['content'], true) ?? [];
        return count($imgs) . ' image' . (count($imgs) > 1 ? 's' : '');
    }
    if ($block['type'] === 'video') {
        $data = json_decode($block['content'], true) ?? [];
        return htmlspecialchars($data['title'] ?? ($data['src'] ?? ''));
    }
    return '';
}
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Pages de contenu</h1>
    <?php if ($editPage): ?>
        <a href="/admin/pages" class="btn btn-sm">&larr; Liste des pages</a>
        <a href="/<?= htmlspecialchars($editSlug) ?>" target="_blank" class="btn btn-sm">&#128065; Voir</a>
    <?php endif; ?>
</div>

<?php if ($saved): ?><div class="alert alert-success">Modifications enregistr&eacute;es.</div><?php endif; ?>

<?php if (!$editPage): ?>
<!-- ══ Liste des pages ══════════════════════════════════ -->
<div class="card">
    <table>
        <thead><tr><th>Titre</th><th>URL</th><th>Modifi&eacute;e le</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allPages as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td><a href="/<?= htmlspecialchars($p['slug']) ?>" target="_blank" style="color:var(--primary);">/<?= htmlspecialchars($p['slug']) ?></a></td>
                <td style="color:var(--muted);font-size:.82rem;"><?= $p['updated_at'] ?></td>
                <td><a href="/admin/pages?edit=<?= urlencode($p['slug']) ?>" class="btn btn-sm">&#9998; G&eacute;rer</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<!-- ══ Titre de la page ════════════════════════════════ -->
<form method="post" style="margin-bottom:1rem;">
    <input type="hidden" name="action" value="update_title">
    <input type="hidden" name="page_slug" value="<?= htmlspecialchars($editSlug) ?>">
    <div class="card" style="display:flex; gap:.75rem; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="flex:1; margin:0; min-width:200px;">
            <label>Titre de la page</label>
            <input type="text" name="title" value="<?= htmlspecialchars($editPage['title']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="flex-shrink:0;">Enregistrer le titre</button>
    </div>
</form>

<!-- ══ Liste des blocs ════════════════════════════════ -->
<?php if (empty($blocks)): ?>
    <p style="color:var(--muted); font-style:italic; margin-bottom:1rem;">Aucun bloc — ajoutez-en un ci-dessous.</p>
<?php else: ?>
<div class="card" style="padding:.5rem; margin-bottom:1rem;">
    <?php foreach ($blocks as $i => $b): ?>
    <div class="block-row">
        <span class="block-type-badge"><?= $typeIcons[$b['type']] ?> <?= htmlspecialchars($typeLabels[$b['type']] ?? $b['type']) ?></span>
        <span class="block-preview"><?= blockPreview($b) ?></span>
        <div class="block-actions">
            <!-- Réordonner -->
            <?php if ($i > 0): ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="move_up">
                <input type="hidden" name="block_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="page_slug" value="<?= htmlspecialchars($editSlug) ?>">
                <button type="submit" class="btn btn-sm" title="Monter">&#8593;</button>
            </form>
            <?php endif; ?>
            <?php if ($i < count($blocks) - 1): ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="move_down">
                <input type="hidden" name="block_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="page_slug" value="<?= htmlspecialchars($editSlug) ?>">
                <button type="submit" class="btn btn-sm" title="Descendre">&#8595;</button>
            </form>
            <?php endif; ?>
            <!-- Éditer -->
            <a href="/admin/block?id=<?= $b['id'] ?>" class="btn btn-sm">&#9998;</a>
            <!-- Supprimer -->
            <form method="post" style="display:inline;"
                  onsubmit="return confirm('Supprimer ce bloc ?');">
                <input type="hidden" name="action" value="delete_block">
                <input type="hidden" name="block_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="page_slug" value="<?= htmlspecialchars($editSlug) ?>">
                <button type="submit" class="btn btn-sm btn-danger">&#10005;</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ Ajouter un bloc ═══════════════════════════════ -->
<div class="card">
    <h2 style="margin-bottom:.75rem;">Ajouter un bloc</h2>
    <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
        <a href="/admin/block?page=<?= urlencode($editSlug) ?>&type=html"
           class="btn btn-primary">&#128196; Texte HTML</a>
        <a href="/admin/block?page=<?= urlencode($editSlug) ?>&type=gallery"
           class="btn btn-primary">&#128247; Galerie photos</a>
        <a href="/admin/block?page=<?= urlencode($editSlug) ?>&type=video"
           class="btn btn-primary">&#127909; Vid&eacute;o</a>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
