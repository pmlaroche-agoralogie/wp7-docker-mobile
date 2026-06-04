<?php
requireLogin();
$pageTitle = 'Mon espace — ' . SITE_NAME;
$user = getCurrentUser();
include __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Bonjour, <?= htmlspecialchars($user['username']) ?></h1>

<div class="module-grid">

    <div class="module-card">
        <div class="mod-icon">&#128203;</div>
        <h3>Activit&eacute;s</h3>
        <p>Pr&eacute;sentation des activit&eacute;s r&eacute;centes dans l'intranet</p>
    </div>

    <div class="module-card">
        <div class="mod-icon">&#128226;</div>
        <h3>Annonces</h3>
        <p>Les 3 derni&egrave;res petites annonces</p>
    </div>

    <div class="module-card">
        <div class="mod-icon">&#128193;</div>
        <h3>Fichiers</h3>
        <p>Les 3 derniers fichiers disponibles</p>
    </div>

    <div class="module-card">
        <div class="mod-icon">&#9993;</div>
        <h3>Messagerie</h3>
        <p>Vos derniers messages re&ccedil;us</p>
    </div>

    <div class="module-card">
        <div class="mod-icon">&#127780;</div>
        <h3>M&eacute;t&eacute;o</h3>
        <p>Widget m&eacute;t&eacute;o personnalis&eacute;</p>
    </div>

    <div class="module-card" onclick="location.href='/produits'">
        <div class="mod-icon">&#128722;</div>
        <h3>Commandes</h3>
        <p>Catalogue de produits et panier de commandes</p>
    </div>

    <?php if ($user['role'] === 'admin'): ?>

    <div class="module-card" onclick="location.href='/admin/users'">
        <div class="mod-icon">&#128101;</div>
        <h3>Gestion utilisateurs</h3>
        <p>Liste, ajout, groupes, activation</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/pages'">
        <div class="mod-icon">&#128196;</div>
        <h3>Pages de contenu</h3>
        <p>&Eacute;diteur WYSIWYG des pages publiques</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/produits'">
        <div class="mod-icon">&#128230;</div>
        <h3>Produits</h3>
        <p>Gestion du catalogue, fiches, photos</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/commandes'">
        <div class="mod-icon">&#128203;</div>
        <h3>Commandes re&ccedil;ues</h3>
        <p>Suivi et livraison des commandes &eacute;leveurs</p>
    </div>

    <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
