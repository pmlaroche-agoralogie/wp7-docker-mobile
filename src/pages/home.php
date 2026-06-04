<?php
$pageTitle = 'Accueil — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>
<div class="hero">
    <h1>Bienvenue</h1>
    <p>L'espace en ligne de notre communaut&eacute; &mdash; informations, annonces et services.</p>
    <?php if (!isLoggedIn()): ?>
        <a href="/login" class="btn btn-primary" style="margin-top:1.25rem; display:inline-block; background:rgba(255,255,255,.2); border:2px solid rgba(255,255,255,.6);">
            Se connecter
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Notre association</h2>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
</div>

<div class="card">
    <h2>Derni&egrave;res actualit&eacute;s</h2>
    <div class="news-item">
        <span class="date">01/06/2026</span>
        <h3>R&eacute;union mensuelle &mdash; compte-rendu</h3>
        <p>R&eacute;sum&eacute; de l'actualit&eacute; placeholder. Lorem ipsum dolor sit amet.</p>
    </div>
    <div class="news-item">
        <span class="date">28/05/2026</span>
        <h3>Nouveaux fichiers disponibles</h3>
        <p>R&eacute;sum&eacute; de l'actualit&eacute; placeholder. Consectetur adipiscing elit.</p>
    </div>
    <div class="news-item">
        <span class="date">20/05/2026</span>
        <h3>Mise &agrave; jour du r&egrave;glement</h3>
        <p>R&eacute;sum&eacute; de l'actualit&eacute; placeholder. Sed do eiusmod tempor.</p>
    </div>
    <p style="margin-top:.75rem;"><a href="/news" style="color:var(--primary); font-size:.88rem;">Toutes les actualit&eacute;s &rarr;</a></p>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
