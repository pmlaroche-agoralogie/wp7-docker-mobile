<?php
$pageTitle = 'Page introuvable — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="text-align:center; padding:3rem 1.5rem;">
    <div style="font-size:3rem; margin-bottom:1rem;">404</div>
    <h2 style="color:var(--text);">Page introuvable</h2>
    <p style="margin:.5rem 0 1.5rem; color:var(--muted);">La page que vous cherchez n'existe pas.</p>
    <a href="/" class="btn btn-primary">Retour &agrave; l'accueil</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
