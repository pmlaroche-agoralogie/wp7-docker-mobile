<?php
requireAdmin();
$db = getDB();

// ── POST actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_visible') {
        $id = (int)($_POST['produit_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE produits SET visible = 1 - visible, updated_at = datetime('now') WHERE id = ?")
               ->execute([$id]);
        }
        header("Location: /admin/produits");
        exit;
    }

    if ($action === 'supprimer') {
        $id = (int)($_POST['produit_id'] ?? 0);
        if ($id > 0) {
            // Supprimer la photo si elle existe
            $photo = $db->prepare("SELECT photo FROM produits WHERE id = ?");
            $photo->execute([$id]);
            $row = $photo->fetch();
            if ($row && $row['photo']) {
                @unlink(__DIR__ . '/../../uploads/produits/' . $row['photo']);
            }
            $db->prepare("DELETE FROM produits WHERE id = ?")->execute([$id]);
        }
        header("Location: /admin/produits");
        exit;
    }
}

$produits  = $db->query("SELECT * FROM produits ORDER BY titre ASC")->fetchAll();
$pageTitle = 'Gestion produits — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Produits</h1>
    <a href="/admin/produit-edit" class="btn btn-primary">+ Nouveau produit</a>
    <a href="/admin/commandes" class="btn btn-sm">&#128203; Commandes</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Photo</th>
                <th>Titre</th>
                <th style="text-align:right;">Prix HT</th>
                <th style="text-align:center;">Visible</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($produits)): ?>
            <tr><td colspan="5" class="text-muted" style="text-align:center; padding:1.5rem;">Aucun produit.</td></tr>
        <?php endif; ?>
        <?php foreach ($produits as $p): ?>
            <tr style="<?= $p['visible'] ? '' : 'opacity:.5;' ?>">
                <td style="width:56px;">
                    <?php if ($p['photo']): ?>
                        <img src="/uploads/produits/<?= htmlspecialchars($p['photo']) ?>"
                             style="width:48px; height:48px; object-fit:cover; border-radius:6px;" alt="">
                    <?php else: ?>
                        <div style="width:48px; height:48px; background:var(--border); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--muted); font-size:1.2rem;">&#128247;</div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['titre']) ?></td>
                <td style="text-align:right; white-space:nowrap;">
                    <?= $p['prix_ht'] > 0 ? number_format($p['prix_ht'], 2, ',', ' ') . ' €' : '<span class="text-muted">—</span>' ?>
                </td>
                <td style="text-align:center;">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_visible">
                        <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm" title="Basculer la visibilité"
                                style="<?= $p['visible'] ? 'background:var(--primary); color:#fff;' : '' ?>">
                            <?= $p['visible'] ? '&#128065; Visible' : '&#128683; Masqu&eacute;' ?>
                        </button>
                    </form>
                </td>
                <td style="white-space:nowrap;">
                    <a href="/admin/produit-edit?id=<?= $p['id'] ?>" class="btn btn-sm">&#9998; &Eacute;diter</a>
                    <form method="post" style="display:inline;"
                          onsubmit="return confirm('Supprimer ce produit définitivement ?');">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">&#10005;</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
