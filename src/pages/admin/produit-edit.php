<?php
requireAdmin();
$db = getDB();

$id      = (int)($_GET['id'] ?? 0);
$produit = null;
$errors  = [];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$id]);
    $produit = $stmt->fetch();
    if (!$produit) {
        header("Location: /admin/produits");
        exit;
    }
}

// ── POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre  = trim($_POST['titre'] ?? '');
    $desc   = $_POST['description'] ?? '';
    $prix   = str_replace(',', '.', trim($_POST['prix_ht'] ?? '0'));
    $visible = isset($_POST['visible']) ? 1 : 0;

    if ($titre === '') $errors[] = 'Le titre est obligatoire.';
    if (!is_numeric($prix) || (float)$prix < 0) $errors[] = 'Prix invalide.';

    $photoFilename = $produit['photo'] ?? null;

    // Upload photo
    if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['photo']['tmp_name'];
        $orig = $_FILES['photo']['name'];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $errors[] = 'Format de photo non autorisé (jpg, png, webp, gif).';
        } else {
            $uploadDir = __DIR__ . '/../../uploads/produits/';
            $newName   = uniqid('prod_', true) . '.' . $ext;
            if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                // Supprimer l'ancienne photo
                if ($photoFilename && file_exists($uploadDir . $photoFilename)) {
                    @unlink($uploadDir . $photoFilename);
                }
                $photoFilename = $newName;
            } else {
                $errors[] = 'Erreur lors de l\'upload de la photo.';
            }
        }
    }

    if (empty($errors)) {
        if ($produit) {
            $db->prepare("UPDATE produits SET titre = ?, description = ?, prix_ht = ?, photo = ?, visible = ?, updated_at = datetime('now') WHERE id = ?")
               ->execute([$titre, $desc, (float)$prix, $photoFilename, $visible, $id]);
            header("Location: /admin/produits?saved=1");
        } else {
            $db->prepare("INSERT INTO produits (titre, description, prix_ht, photo, visible) VALUES (?, ?, ?, ?, ?)")
               ->execute([$titre, $desc, (float)$prix, $photoFilename, $visible]);
            header("Location: /admin/produits?saved=1");
        }
        exit;
    }

    // Reconstituer pour réaffichage
    $produit = [
        'id'          => $id,
        'titre'       => $titre,
        'description' => $desc,
        'prix_ht'     => $prix,
        'visible'     => $visible,
        'photo'       => $photoFilename,
    ];
}

$pageTitle = ($id ? 'Éditer' : 'Nouveau') . ' produit — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
    <h1 class="page-title" style="margin:0;">
        <?= $id ? 'Éditer le produit' : 'Nouveau produit' ?>
    </h1>
    <a href="/admin/produits" class="btn btn-sm">&larr; Liste</a>
</div>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
    <div class="card" style="display:flex; flex-direction:column; gap:1rem;">

        <div class="form-group">
            <label for="titre">Titre <span style="color:red;">*</span></label>
            <input type="text" id="titre" name="titre" required
                   value="<?= htmlspecialchars($produit['titre'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="prix_ht">Prix HT (€)</label>
            <input type="text" id="prix_ht" name="prix_ht" inputmode="decimal"
                   value="<?= htmlspecialchars(number_format((float)($produit['prix_ht'] ?? 0), 2, '.', '')) ?>"
                   style="max-width:160px;">
        </div>

        <div class="form-group">
            <label for="description">Description (HTML)</label>
            <textarea id="description" name="description" rows="10"
                      style="font-family: monospace; font-size: .85rem;"><?= htmlspecialchars($produit['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="photo">Photo du produit</label>
            <?php if (!empty($produit['photo'])): ?>
                <div style="margin-bottom:.5rem;">
                    <img src="/uploads/produits/<?= htmlspecialchars($produit['photo']) ?>"
                         style="max-height:120px; border-radius:8px; border:1px solid var(--border);" alt="Photo actuelle">
                    <p style="font-size:.8rem; color:var(--muted); margin-top:.25rem;">Photo actuelle — choisir un nouveau fichier pour remplacer</p>
                </div>
            <?php endif; ?>
            <input type="file" id="photo" name="photo" accept="image/*">
        </div>

        <div class="form-group">
            <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
                <input type="checkbox" name="visible" value="1"
                       <?= (!isset($produit['visible']) || $produit['visible']) ? 'checked' : '' ?>>
                Visible sur le catalogue
            </label>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">
                <?= $id ? '&#10003; Enregistrer' : '+ Créer le produit' ?>
            </button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
