<?php
requireAdmin();
$db = getDB();

$id      = (int)($_GET['id'] ?? 0);
$annonce = null;
$errors  = [];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM annonces WHERE id = ?");
    $stmt->execute([$id]);
    $annonce = $stmt->fetch();
    if (!$annonce) {
        header('Location: /admin/annonces');
        exit;
    }
}

$uploadDir = __DIR__ . '/../../uploads/annonces/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    // Delete individual media
    if ($action === 'delete_media') {
        $mid = (int)($_POST['media_id'] ?? 0);
        if ($mid > 0 && $id > 0) {
            $mrow = $db->prepare("SELECT fichier FROM annonce_medias WHERE id = ? AND annonce_id = ?");
            $mrow->execute([$mid, $id]);
            $mrow = $mrow->fetch();
            if ($mrow) {
                @unlink($uploadDir . $mrow['fichier']);
                $db->prepare("DELETE FROM annonce_medias WHERE id = ?")->execute([$mid]);
            }
        }
        header("Location: /admin/annonce-edit?id=$id");
        exit;
    }

    // Save annonce
    $titre   = trim($_POST['titre']   ?? '');
    $texte   = trim($_POST['texte']   ?? '');
    $tag     = $_POST['tag']          ?? 'divers';
    $prixRaw = str_replace(',', '.', trim($_POST['prix'] ?? ''));
    $prix    = $prixRaw !== '' && is_numeric($prixRaw) ? (float)$prixRaw : null;
    $visible = isset($_POST['visible']) ? 1 : 0;

    if (!in_array($tag, ANNONCE_TAGS)) $tag = 'divers';
    if ($titre === '') $errors[] = 'Le titre est obligatoire.';
    if ($prix !== null && $prix < 0) $errors[] = 'Le prix ne peut pas être négatif.';

    // Upload new media files
    $newMedias = [];
    if (empty($errors) && !empty($_FILES['medias']['name'][0])) {
        $allowedImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowedVid = ['mp4', 'webm', 'ogg', 'mov'];
        $allowedAll = array_merge($allowedImg, $allowedVid);

        foreach ($_FILES['medias']['error'] as $i => $err) {
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = 'Erreur lors du transfert d\'un fichier.';
                continue;
            }
            $orig = $_FILES['medias']['name'][$i];
            $tmp  = $_FILES['medias']['tmp_name'][$i];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedAll)) {
                $errors[] = "Format non autorisé : $orig";
                continue;
            }
            $type    = in_array($ext, $allowedVid) ? 'video' : 'photo';
            $newName = uniqid('ann_', true) . '.' . $ext;
            if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                $newMedias[] = ['fichier' => $newName, 'type' => $type];
            } else {
                $errors[] = "Impossible d'enregistrer le fichier $orig.";
            }
        }
    }

    if (empty($errors)) {
        if ($annonce) {
            $db->prepare("UPDATE annonces SET titre=?, texte=?, tag=?, prix=?, visible=?, updated_at=datetime('now') WHERE id=?")
               ->execute([$titre, $texte, $tag, $prix, $visible, $id]);
        } else {
            $db->prepare("INSERT INTO annonces (titre, texte, tag, prix, visible) VALUES (?, ?, ?, ?, ?)")
               ->execute([$titre, $texte, $tag, $prix, $visible]);
            $id = (int)$db->lastInsertId();
        }

        // Insert new media
        $maxPos = (int)$db->prepare("SELECT COALESCE(MAX(position),0) FROM annonce_medias WHERE annonce_id = ?")
                           ->execute([$id]) ? $db->query("SELECT COALESCE(MAX(position),0) FROM annonce_medias WHERE annonce_id = $id")->fetchColumn() : 0;
        foreach ($newMedias as $pos => $m) {
            $db->prepare("INSERT INTO annonce_medias (annonce_id, fichier, type, position) VALUES (?, ?, ?, ?)")
               ->execute([$id, $m['fichier'], $m['type'], (int)$maxPos + $pos + 1]);
        }

        header('Location: /admin/annonces?saved=1');
        exit;
    }

    // Reconstituer pour réaffichage
    $annonce = ['id' => $id, 'titre' => $titre, 'texte' => $texte, 'tag' => $tag, 'prix' => $prix, 'visible' => $visible];
}

// Médias existants
$existingMedias = [];
if ($id > 0) {
    $existingMedias = $db->prepare("SELECT * FROM annonce_medias WHERE annonce_id = ? ORDER BY position ASC")
                         ->execute([$id]) ? $db->query("SELECT * FROM annonce_medias WHERE annonce_id = $id ORDER BY position ASC")->fetchAll() : [];
}

$pageTitle = ($id ? 'Éditer l\'annonce' : 'Nouvelle annonce') . ' — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
    <h1 class="page-title" style="margin:0;">
        <?= $id ? 'Éditer l\'annonce' : 'Nouvelle annonce' ?>
    </h1>
    <a href="/admin/annonces" class="btn btn-sm">&larr; Liste</a>
</div>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
    <div class="card" style="display:flex; flex-direction:column; gap:1rem;">

        <div class="form-group">
            <label for="titre">Titre <span style="color:red;">*</span></label>
            <input type="text" id="titre" name="titre" required
                   value="<?= htmlspecialchars($annonce['titre'] ?? '') ?>">
        </div>

        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:160px;">
                <label for="tag">Catégorie</label>
                <select id="tag" name="tag">
                    <?php foreach (ANNONCE_TAGS as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"
                                <?= ($annonce['tag'] ?? 'divers') === $t ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:160px;">
                <label for="prix">Prix (€) <span style="color:var(--muted); font-weight:400; font-size:.82rem;">optionnel</span></label>
                <input type="text" id="prix" name="prix" inputmode="decimal" style="max-width:180px;"
                       placeholder="Laisser vide = prix sur demande"
                       value="<?= $annonce['prix'] !== null ? htmlspecialchars(number_format((float)($annonce['prix'] ?? 0), 2, '.', '')) : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="texte">Description</label>
            <textarea id="texte" name="texte" rows="8"
                      style="width:100%; padding:.65rem .9rem; border:1px solid var(--border); border-radius:8px; font-size:.95rem; font-family:inherit; resize:vertical;"><?= htmlspecialchars($annonce['texte'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>Photos / Vidéos
                <span style="color:var(--muted); font-weight:400; font-size:.82rem;">
                    (jpg, png, webp, gif, mp4, webm — plusieurs fichiers possibles)
                </span>
            </label>
            <input type="file" name="medias[]" multiple
                   accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg,video/quicktime">
        </div>

        <?php if (!empty($existingMedias)): ?>
        <div class="form-group">
            <label>Médias existants</label>
            <div class="gallery-admin-grid">
                <?php foreach ($existingMedias as $m): ?>
                <div class="gallery-admin-item">
                    <?php if ($m['type'] === 'photo'): ?>
                        <img src="/uploads/annonces/<?= htmlspecialchars($m['fichier']) ?>" alt="">
                    <?php else: ?>
                        <div style="height:110px; background:#000; display:flex; align-items:center; justify-content:center; border-radius:6px; color:#fff; font-size:.8rem;">
                            &#127909; Vidéo
                        </div>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Supprimer ce média ?');" style="margin-top:.2rem;">
                        <input type="hidden" name="action" value="delete_media">
                        <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" style="width:100%; font-size:.75rem;">
                            &#10005; Supprimer
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                <input type="checkbox" name="visible" value="1"
                       <?= (!isset($annonce['visible']) || $annonce['visible']) ? 'checked' : '' ?>>
                Visible publiquement
            </label>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">
                <?= $id ? '&#10003; Enregistrer' : '+ Créer l\'annonce' ?>
            </button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
