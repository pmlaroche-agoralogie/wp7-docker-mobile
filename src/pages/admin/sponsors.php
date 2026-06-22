<?php
requireAdmin();
$db = getDB();

$error   = '';
$success = '';

$uploadDir = '/var/www/html/media/sponsors';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $url  = trim($_POST['url'] ?? '');
        if (!$name || !$url) {
            $error = 'Nom et URL sont obligatoires.';
        } elseif (empty($_FILES['logo']['tmp_name'])) {
            $error = 'Un logo est obligatoire.';
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $mime    = mime_content_type($_FILES['logo']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                $error = 'Format de logo non supporté (JPEG, PNG, GIF, WebP, SVG).';
            } else {
                $ext      = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $stored   = uniqid('sponsor_', true) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . '/' . $stored)) {
                    $db->prepare("INSERT INTO sponsors (name, url, logo) VALUES (?, ?, ?)")
                       ->execute([$name, $url, $stored]);
                    $success = 'Sponsor ajouté.';
                } else {
                    $error = 'Erreur lors de l\'enregistrement du logo.';
                }
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['sponsor_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE sponsors SET active = 1 - active WHERE id = ?")
               ->execute([$id]);
        }
        header('Location: /admin/sponsors');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['sponsor_id'] ?? 0);
        if ($id > 0) {
            $row = $db->prepare("SELECT logo FROM sponsors WHERE id = ?");
            $row->execute([$id]);
            $s = $row->fetch();
            if ($s) {
                @unlink($uploadDir . '/' . $s['logo']);
            }
            $db->prepare("DELETE FROM sponsors WHERE id = ?")->execute([$id]);
        }
        header('Location: /admin/sponsors');
        exit;
    }
}

$sponsors  = $db->query("SELECT * FROM sponsors ORDER BY created_at DESC")->fetchAll();
$pageTitle = 'Gestion sponsors — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">&#127775; Sponsors</h1>
    <a href="/dashboard" class="btn btn-sm">&larr; Accueil</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Formulaire d'ajout -->
<div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:.95rem; margin-bottom:.9rem;">Ajouter un sponsor</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div style="display:grid; gap:.75rem;">
            <div>
                <label style="font-size:.82rem; color:var(--muted); display:block; margin-bottom:.3rem;">Nom</label>
                <input type="text" name="name" required
                       style="width:100%; padding:.5rem .7rem; border:1px solid var(--border); border-radius:8px; font-size:.9rem;">
            </div>
            <div>
                <label style="font-size:.82rem; color:var(--muted); display:block; margin-bottom:.3rem;">URL (avec https://)</label>
                <input type="url" name="url" required placeholder="https://exemple.com"
                       style="width:100%; padding:.5rem .7rem; border:1px solid var(--border); border-radius:8px; font-size:.9rem;">
            </div>
            <div>
                <label style="font-size:.82rem; color:var(--muted); display:block; margin-bottom:.3rem;">Logo (JPEG, PNG, GIF, WebP, SVG)</label>
                <input type="file" name="logo" accept="image/*" required
                       style="width:100%; padding:.5rem .7rem; border:1px solid var(--border); border-radius:8px; font-size:.9rem; background:var(--bg);">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:.9rem;">Ajouter</button>
    </form>
</div>

<!-- Liste des sponsors -->
<?php if (empty($sponsors)): ?>
    <div class="card" style="text-align:center; padding:2.5rem 1rem; color:var(--muted);">
        <p>Aucun sponsor enregistré.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0; overflow:hidden;">
        <?php foreach ($sponsors as $i => $s): ?>
        <div style="display:flex; align-items:center; gap:.75rem; padding:.85rem 1.1rem; flex-wrap:wrap;
                    border-bottom:<?= $i < count($sponsors) - 1 ? '1px solid var(--border)' : 'none' ?>;
                    opacity:<?= $s['active'] ? '1' : '.5' ?>;">
            <img src="/media/sponsors/<?= htmlspecialchars($s['logo']) ?>" alt=""
                 style="height:40px; width:80px; object-fit:contain; border-radius:4px; background:#f8f8f8; flex-shrink:0;">
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600; font-size:.88rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars($s['name']) ?>
                </div>
                <div style="font-size:.72rem; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars($s['url']) ?>
                </div>
            </div>
            <div style="display:flex; gap:.4rem; flex-shrink:0;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="sponsor_id" value="<?= $s['id'] ?>">
                    <button type="submit"
                            style="background:none; border:1px solid var(--border); border-radius:6px;
                                   padding:.3rem .6rem; cursor:pointer; font-size:.78rem; color:var(--muted);">
                        <?= $s['active'] ? 'Désactiver' : 'Activer' ?>
                    </button>
                </form>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Supprimer « <?= htmlspecialchars(addslashes($s['name'])) ?> » ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="sponsor_id" value="<?= $s['id'] ?>">
                    <button type="submit"
                            style="background:none; border:1px solid var(--border); border-radius:6px;
                                   padding:.3rem .55rem; cursor:pointer; font-size:.95rem; color:#ef4444;">
                        &#128465;
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
