<?php
requireLogin();
$db   = getDB();
$user = getCurrentUser();

// ── POST actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_qte') {
        $ligneId = (int)($_POST['ligne_id'] ?? 0);
        $qte     = (int)($_POST['quantite'] ?? 1);
        if ($ligneId > 0) {
            if ($qte < 1) {
                $db->prepare("DELETE FROM panier_lignes WHERE id = ? AND panier_id IN (SELECT id FROM paniers WHERE user_id = ? AND statut = 'panier')")
                   ->execute([$ligneId, $user['id']]);
            } else {
                $db->prepare("UPDATE panier_lignes SET quantite = ? WHERE id = ? AND panier_id IN (SELECT id FROM paniers WHERE user_id = ? AND statut = 'panier')")
                   ->execute([$qte, $ligneId, $user['id']]);
            }
        }
        header("Location: /panier");
        exit;
    }

    if ($action === 'supprimer') {
        $ligneId = (int)($_POST['ligne_id'] ?? 0);
        if ($ligneId > 0) {
            $db->prepare("DELETE FROM panier_lignes WHERE id = ? AND panier_id IN (SELECT id FROM paniers WHERE user_id = ? AND statut = 'panier')")
               ->execute([$ligneId, $user['id']]);
        }
        header("Location: /panier");
        exit;
    }

    if ($action === 'commander') {
        $stmt = $db->prepare("SELECT id FROM paniers WHERE user_id = ? AND statut = 'panier' LIMIT 1");
        $stmt->execute([$user['id']]);
        $panier = $stmt->fetch();

        if ($panier) {
            // Vérifier qu'il y a au moins une ligne
            $nb = (int)$db->prepare("SELECT COUNT(*) FROM panier_lignes WHERE panier_id = ?")
                          ->execute([$panier['id']]) ? $db->query("SELECT COUNT(*) FROM panier_lignes WHERE panier_id = " . (int)$panier['id'])->fetchColumn() : 0;

            if ($nb > 0) {
                $db->prepare("UPDATE paniers SET statut = 'commande', updated_at = datetime('now') WHERE id = ?")
                   ->execute([$panier['id']]);

                // Mail à l'admin
                $adminEmail = $db->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
                if ($adminEmail) {
                    $stmtEmail = $db->prepare("SELECT email FROM users WHERE id = ?");
                    $stmtEmail->execute([$user['id']]);
                    $userEmail = $stmtEmail->fetchColumn() ?: '';
                    $lignes = $db->query("SELECT pl.quantite, p.titre, p.prix_ht FROM panier_lignes pl JOIN produits p ON p.id = pl.produit_id WHERE pl.panier_id = " . (int)$panier['id'])->fetchAll();
                    $corps  = "Nouvelle commande de {$user['username']} ({$userEmail}) :\n\n";
                    $total  = 0;
                    foreach ($lignes as $l) {
                        $sous = $l['quantite'] * $l['prix_ht'];
                        $total += $sous;
                        $corps .= "- {$l['titre']} x{$l['quantite']} = " . number_format($sous, 2, ',', ' ') . " € HT\n";
                    }
                    $corps .= "\nTotal : " . number_format($total, 2, ',', ' ') . " € HT\n";
                    @mail($adminEmail, '[' . SITE_NAME . '] Nouvelle commande', $corps, "From: noreply@elvea64-40.fr");
                }

                header("Location: /panier?commande=1");
                exit;
            }
        }
        header("Location: /panier");
        exit;
    }
}

// ── Données ───────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM paniers WHERE user_id = ? AND statut IN ('panier','commande') ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$user['id']]);
$panierCourant = $stmt->fetch();

$lignes = [];
$total  = 0;
if ($panierCourant) {
    $lignes = $db->query("
        SELECT pl.id, pl.quantite, p.id AS produit_id, p.titre, p.prix_ht, p.photo
        FROM panier_lignes pl
        JOIN produits p ON p.id = pl.produit_id
        WHERE pl.panier_id = " . (int)$panierCourant['id'] . "
        ORDER BY p.titre
    ")->fetchAll();
    foreach ($lignes as $l) {
        $total += $l['quantite'] * $l['prix_ht'];
    }
}

$pageTitle = 'Mon panier — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="produits-header">
    <h1 class="page-title">Mon panier</h1>
    <a href="/produits" class="btn btn-sm">&larr; Voir les produits</a>
</div>

<?php if (isset($_GET['commande'])): ?>
    <div class="alert alert-success">
        &#10003; Votre commande a &eacute;t&eacute; transmise. Nous reviendrons vers vous rapidement.
    </div>
<?php endif; ?>

<?php if (!$panierCourant || empty($lignes)): ?>
    <div class="card" style="text-align:center; padding:2rem;">
        <p class="text-muted" style="font-size:1.1rem;">Votre panier est vide.</p>
        <a href="/produits" class="btn btn-primary" style="margin-top:1rem;">Voir les produits</a>
    </div>

<?php elseif ($panierCourant['statut'] === 'commande'): ?>
    <div class="card" style="text-align:center; padding:2rem;">
        <div style="font-size:2.5rem; margin-bottom:.75rem;">&#9203;</div>
        <h2>Commande en cours</h2>
        <p class="text-muted" style="margin-top:.5rem;">
            Votre commande a &eacute;t&eacute; transmise le <?= date('d/m/Y', strtotime($panierCourant['updated_at'])) ?>.
            Vous serez contact&eacute; d&egrave;s qu'elle sera trait&eacute;e.
        </p>
    </div>

<?php else: ?>
<!-- ══ Panier en cours ═══════════════════════════════════ -->
<div class="card panier-table-card">
    <table class="panier-table">
        <thead>
            <tr>
                <th>Produit</th>
                <th style="text-align:right;">Prix HT</th>
                <th style="text-align:center;">Qté</th>
                <th style="text-align:right;">Sous-total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <td>
                    <div style="display:flex; align-items:center; gap:.6rem;">
                        <?php if ($l['photo']): ?>
                            <img src="/uploads/produits/<?= htmlspecialchars($l['photo']) ?>"
                                 style="width:40px; height:40px; object-fit:cover; border-radius:4px;" alt="">
                        <?php endif; ?>
                        <?= htmlspecialchars($l['titre']) ?>
                    </div>
                </td>
                <td style="text-align:right;"><?= number_format($l['prix_ht'], 2, ',', ' ') ?> €</td>
                <td style="text-align:center;">
                    <form method="post" style="display:inline-flex; gap:.35rem; align-items:center;">
                        <input type="hidden" name="action" value="update_qte">
                        <input type="hidden" name="ligne_id" value="<?= $l['id'] ?>">
                        <input type="number" name="quantite" value="<?= $l['quantite'] ?>"
                               min="1" max="99" class="qte-input" style="width:55px;">
                        <button type="submit" class="btn btn-sm">&#10003;</button>
                    </form>
                </td>
                <td style="text-align:right; font-weight:600;">
                    <?= number_format($l['quantite'] * $l['prix_ht'], 2, ',', ' ') ?> €
                </td>
                <td>
                    <form method="post" onsubmit="return confirm('Retirer ce produit ?');">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="ligne_id" value="<?= $l['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">&#10005;</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; font-weight:600; padding-top:.75rem;">Total HT :</td>
                <td style="text-align:right; font-weight:700; font-size:1.15rem; padding-top:.75rem;">
                    <?= number_format($total, 2, ',', ' ') ?> €
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<div style="text-align:right; margin-top:1rem;">
    <form method="post" onsubmit="return confirm('Confirmer et envoyer la commande ?');">
        <input type="hidden" name="action" value="commander">
        <button type="submit" class="btn btn-primary btn-lg">&#9989; Commander</button>
    </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
