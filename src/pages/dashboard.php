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

    <div class="module-card">
        <div class="mod-icon">&#128722;</div>
        <h3>Commandes</h3>
        <p>Produits et paniers de commandes</p>
    </div>

    <?php if ($user['role'] === 'admin'): ?>

    <div class="module-card" onclick="location.href='/admin/users'">
        <div class="mod-icon">&#128101;</div>
        <h3>Gestion utilisateurs</h3>
        <p>Liste, ajout, groupes, activation</p>
    </div>

    <div class="module-card">
        <div class="mod-icon">&#9881;</div>
        <h3>Administration</h3>
        <p>Pages, annonces, commandes, mod&eacute;ration</p>
    </div>

    <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
