<?php
requireAdmin();
$db = getDB();

// ── POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $panierId = (int)($_POST['panier_id'] ?? 0);

    if ($action === 'livrer' && $panierId > 0) {
        $db->prepare("UPDATE paniers SET statut = 'livre', updated_at = datetime('now') WHERE id = ? AND statut = 'commande'")
           ->execute([$panierId]);
    }

    if ($action === 'annuler' && $panierId > 0) {
        $db->prepare("UPDATE paniers SET statut = 'annule', updated_at = datetime('now') WHERE id = ? AND statut = 'commande'")
           ->execute([$panierId]);
    }

    header("Location: /admin/commandes");
    exit;
}

// ── Filtre ────────────────────────────────────────────────
$filtre  = $_GET['statut'] ?? 'commande';
$allowed = ['commande', 'livre', 'tous'];
if (!in_array($filtre, $allowed)) $filtre = 'commande';

$where = $filtre === 'tous' ? '' : "WHERE p.statut = '$filtre'";

$commandes = $db->query("
    SELECT p.id, p.statut, p.created_at, p.updated_at,
           u.username, u.email
    FROM paniers p
    JOIN users u ON u.id = p.user_id
    $where
    ORDER BY p.updated_at DESC
")->fetchAll();

$pageTitle = 'Commandes — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Commandes</h1>
    <a href="/admin/produits" class="btn btn-sm">&larr; Produits</a>
</div>

<div style="display:flex; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap;">
    <?php foreach (['commande' => 'En attente', 'livre' => 'Livrées', 'tous' => 'Toutes'] as $val => $label): ?>
        <a href="/admin/commandes?statut=<?= $val ?>"
           class="btn btn-sm <?= $filtre === $val ? 'btn-primary' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($commandes)): ?>
    <div class="card" style="text-align:center; padding:2rem; color:var(--muted);">Aucune commande.</div>
<?php else: ?>
<?php foreach ($commandes as $c): ?>
    <?php
    $lignes = $db->query("
        SELECT pl.quantite, p.titre, p.prix_ht
        FROM panier_lignes pl
        JOIN produits p ON p.id = pl.produit_id
        WHERE pl.panier_id = " . (int)$c['id'] . "
        ORDER BY p.titre
    ")->fetchAll();
    $total = array_sum(array_map(fn($l) => $l['quantite'] * $l['prix_ht'], $lignes));
    ?>
    <div class="card commande-card" style="margin-bottom:1rem;">
        <div class="commande-header">
            <div>
                <strong><?= htmlspecialchars($c['username']) ?></strong>
                <span class="text-muted" style="font-size:.85rem;"> — <?= htmlspecialchars($c['email']) ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
                <span class="statut-badge statut-<?= $c['statut'] ?>">
                    <?= $c['statut'] === 'commande' ? '&#9203; En attente' : '&#10003; Livr&eacute;e' ?>
                </span>
                <span class="text-muted" style="font-size:.8rem;">
                    <?= date('d/m/Y H:i', strtotime($c['updated_at'])) ?>
                </span>
                <?php if ($c['statut'] === 'commande'): ?>
                <form method="post" onsubmit="return confirm('Marquer cette commande comme livrée ?');">
                    <input type="hidden" name="action" value="livrer">
                    <input type="hidden" name="panier_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">&#128666; Marquer livr&eacute;e</button>
                </form>
                <form method="post" onsubmit="return confirm('Annuler cette commande ?');">
                    <input type="hidden" name="action" value="annuler">
                    <input type="hidden" name="panier_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">&#10005; Annuler</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <table class="commande-detail">
            <thead><tr><th>Produit</th><th style="text-align:center;">Qté</th><th style="text-align:right;">Prix HT</th><th style="text-align:right;">Sous-total</th></tr></thead>
            <tbody>
            <?php foreach ($lignes as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['titre']) ?></td>
                    <td style="text-align:center;"><?= $l['quantite'] ?></td>
                    <td style="text-align:right;"><?= number_format($l['prix_ht'], 2, ',', ' ') ?> €</td>
                    <td style="text-align:right;"><?= number_format($l['quantite'] * $l['prix_ht'], 2, ',', ' ') ?> €</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right; font-weight:600;">Total HT :</td>
                    <td style="text-align:right; font-weight:700;"><?= number_format($total, 2, ',', ' ') ?> €</td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
