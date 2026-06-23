<?php
requireLogin();
$db   = getDB();
$user = getCurrentUser();

// ── POST : ajouter au panier ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajouter') {
    $produitId = (int)($_POST['produit_id'] ?? 0);
    $qte       = max(1, (int)($_POST['quantite'] ?? 1));

    if ($produitId > 0) {
        // Panier actif de l'utilisateur (statut = panier)
        $stmt = $db->prepare("SELECT id FROM paniers WHERE user_id = ? AND statut = 'panier' LIMIT 1");
        $stmt->execute([$user['id']]);
        $panier = $stmt->fetch();

        if (!$panier) {
            $db->prepare("INSERT INTO paniers (user_id, statut) VALUES (?, 'panier')")->execute([$user['id']]);
            $panierId = (int)$db->lastInsertId();
        } else {
            $panierId = (int)$panier['id'];
        }

        // Ligne existante ?
        $ligne = $db->prepare("SELECT id, quantite FROM panier_lignes WHERE panier_id = ? AND produit_id = ?");
        $ligne->execute([$panierId, $produitId]);
        $existante = $ligne->fetch();

        if ($existante) {
            $db->prepare("UPDATE panier_lignes SET quantite = quantite + ? WHERE id = ?")
               ->execute([$qte, $existante['id']]);
        } else {
            $db->prepare("INSERT INTO panier_lignes (panier_id, produit_id, quantite) VALUES (?, ?, ?)")
               ->execute([$panierId, $produitId, $qte]);
        }
    }
    header("Location: /produits?ajout=1");
    exit;
}

// ── Données ───────────────────────────────────────────────
$produits = $db->query("SELECT * FROM produits WHERE visible = 1 ORDER BY titre ASC")->fetchAll();

// Nombre d'articles dans le panier actif
$panier = $db->prepare("SELECT id FROM paniers WHERE user_id = ? AND statut = 'panier' LIMIT 1");
$panier->execute([$user['id']]);
$panierActif = $panier->fetch();
$nbArticles  = 0;
if ($panierActif) {
    $nbArticles = (int)$db->prepare("SELECT COALESCE(SUM(quantite),0) FROM panier_lignes WHERE panier_id = ?")
                           ->execute([$panierActif['id']]) ? $db->query("SELECT COALESCE(SUM(quantite),0) FROM panier_lignes WHERE panier_id = " . (int)$panierActif['id'])->fetchColumn() : 0;
}

$pageTitle = 'Produits — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="produits-header">
    <h1 class="page-title">Nos produits</h1>
    <div style="display:flex; align-items:center; gap:.6rem;">
    <a href="/dashboard" class="btn btn-sm">&larr; Accueil</a>
    <a href="/panier" class="btn btn-primary panier-btn">
        &#128722; Mon panier
        <?php if ($nbArticles > 0): ?>
            <span class="panier-badge"><?= $nbArticles ?></span>
        <?php endif; ?>
    </a>
    </div>
</div>

<?php if (isset($_GET['ajout'])): ?>
    <div class="alert alert-success">Produit ajout&eacute; au panier.</div>
<?php endif; ?>

<?php if (empty($produits)): ?>
    <p class="text-muted">Aucun produit disponible pour le moment.</p>
<?php else: ?>
<div class="produits-grid">
    <?php foreach ($produits as $p): ?>
    <div class="produit-card">
        <div class="produit-photo">
            <?php if ($p['photo']): ?>
                <img src="/uploads/produits/<?= htmlspecialchars($p['photo']) ?>"
                     alt="<?= htmlspecialchars($p['titre']) ?>">
            <?php else: ?>
                <div class="produit-no-photo">&#128247;</div>
            <?php endif; ?>
        </div>
        <div class="produit-body">
            <h3 class="produit-titre"><?= htmlspecialchars($p['titre']) ?></h3>
            <div class="produit-desc"><?= $p['description'] ?></div>
            <div class="produit-prix">
                <?php if ($p['prix_ht'] > 0): ?>
                    <strong><?= number_format($p['prix_ht'], 2, ',', ' ') ?> € HT</strong>
                <?php else: ?>
                    <span class="text-muted">Prix non communiqué</span>
                <?php endif; ?>
            </div>
            <form method="post" class="produit-form">
                <input type="hidden" name="action" value="ajouter">
                <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                <div class="produit-form-row">
                    <input type="number" name="quantite" value="1" min="1" max="99" class="qte-input">
                    <button type="submit" class="btn btn-primary btn-sm">+ Panier</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
