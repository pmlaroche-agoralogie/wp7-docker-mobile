<?php
requireAdmin();
$db = getDB();

// ── POST actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_visible') {
        $id = (int)($_POST['annonce_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE annonces SET visible = 1 - visible, updated_at = datetime('now') WHERE id = ?")
               ->execute([$id]);
        }
        header('Location: /admin/annonces');
        exit;
    }

    if ($action === 'supprimer') {
        $id = (int)($_POST['annonce_id'] ?? 0);
        if ($id > 0) {
            $files = $db->prepare("SELECT fichier FROM annonce_medias WHERE annonce_id = ?");
            $files->execute([$id]);
            foreach ($files->fetchAll() as $f) {
                @unlink(__DIR__ . '/../../uploads/annonces/' . $f['fichier']);
            }
            $db->prepare("DELETE FROM annonces WHERE id = ?")->execute([$id]);
        }
        header('Location: /admin/annonces');
        exit;
    }

    if ($action === 'mark_lu') {
        $mid = (int)($_POST['msg_id'] ?? 0);
        if ($mid > 0) {
            $db->prepare("UPDATE annonce_messages SET lu = 1 WHERE id = ?")->execute([$mid]);
        }
        header('Location: /admin/annonces#messages');
        exit;
    }
}

$annonces = $db->query(
    "SELECT a.*, (SELECT COUNT(*) FROM annonce_messages WHERE annonce_id = a.id) as nb_msgs,
            (SELECT COUNT(*) FROM annonce_messages WHERE annonce_id = a.id AND lu = 0) as nb_nonlu
     FROM annonces a ORDER BY a.created_at DESC"
)->fetchAll();

$messages = $db->query(
    "SELECT m.*, a.titre as annonce_titre FROM annonce_messages m
     JOIN annonces a ON a.id = m.annonce_id
     WHERE m.lu = 0 ORDER BY m.created_at DESC"
)->fetchAll();

$pageTitle = 'Gestion annonces — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Petites annonces</h1>
    <a href="/admin/annonce-edit" class="btn btn-primary">+ Nouvelle annonce</a>
    <a href="/annonces" class="btn btn-sm">&#128065; Vue publique</a>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Annonce enregistr&eacute;e.</div>
<?php endif; ?>

<div class="card" style="padding:0; overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Catégorie</th>
                <th style="text-align:right;">Prix</th>
                <th style="text-align:center;">Visible</th>
                <th style="text-align:center;">Messages</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($annonces)): ?>
            <tr><td colspan="7" class="text-muted" style="text-align:center; padding:1.5rem;">Aucune annonce.</td></tr>
        <?php endif; ?>
        <?php foreach ($annonces as $a): ?>
            <tr style="<?= $a['visible'] ? '' : 'opacity:.5;' ?>">
                <td><?= htmlspecialchars($a['titre']) ?></td>
                <td><span style="font-size:.8rem; color:var(--muted);"><?= htmlspecialchars($a['tag']) ?></span></td>
                <td style="text-align:right; white-space:nowrap;">
                    <?= $a['prix'] !== null
                        ? number_format((float)$a['prix'], 2, ',', '&thinsp;') . '&thinsp;&euro;'
                        : '<span class="text-muted">—</span>' ?>
                </td>
                <td style="text-align:center;">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_visible">
                        <input type="hidden" name="annonce_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-sm"
                                style="<?= $a['visible'] ? 'background:var(--primary); color:#fff;' : '' ?>">
                            <?= $a['visible'] ? '&#128065; Visible' : '&#128683; Masqu&eacute;' ?>
                        </button>
                    </form>
                </td>
                <td style="text-align:center;">
                    <?php if ($a['nb_nonlu'] > 0): ?>
                        <span style="background:#ef4444; color:#fff; border-radius:999px; padding:.1rem .5rem; font-size:.75rem; font-weight:700;">
                            <?= $a['nb_nonlu'] ?> nouveau<?= $a['nb_nonlu'] > 1 ? 'x' : '' ?>
                        </span>
                    <?php elseif ($a['nb_msgs'] > 0): ?>
                        <span class="text-muted" style="font-size:.8rem;"><?= $a['nb_msgs'] ?></span>
                    <?php else: ?>
                        <span class="text-muted" style="font-size:.8rem;">—</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap; font-size:.8rem; color:var(--muted);">
                    <?= date('d/m/Y', strtotime($a['created_at'])) ?>
                </td>
                <td style="white-space:nowrap;">
                    <a href="/admin/annonce-edit?id=<?= $a['id'] ?>" class="btn btn-sm">&#9998; &Eacute;diter</a>
                    <form method="post" style="display:inline;"
                          onsubmit="return confirm('Supprimer cette annonce définitivement ?');">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="annonce_id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">&#10005;</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($messages)): ?>
<div class="card" id="messages" style="margin-top:1.5rem;">
    <h2 style="margin-bottom:1rem;">Messages non lus (<?= count($messages) ?>)</h2>
    <?php foreach ($messages as $m): ?>
    <div style="border-bottom:1px solid var(--border); padding:.85rem 0;">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:.4rem;">
            <div>
                <strong><?= htmlspecialchars($m['nom']) ?></strong>
                <span class="text-muted" style="font-size:.82rem;"> &mdash; <?= htmlspecialchars($m['email']) ?></span>
            </div>
            <span class="text-muted" style="font-size:.8rem;"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></span>
        </div>
        <p style="font-size:.82rem; color:var(--muted); margin-bottom:.4rem;">
            Annonce : <em><?= htmlspecialchars($m['annonce_titre']) ?></em>
        </p>
        <p style="font-size:.9rem; white-space:pre-wrap;"><?= htmlspecialchars($m['message']) ?></p>
        <form method="post" style="margin-top:.5rem; display:inline;">
            <input type="hidden" name="action" value="mark_lu">
            <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
            <button type="submit" class="btn btn-sm">&#10003; Marquer comme lu</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>