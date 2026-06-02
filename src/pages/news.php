<?php
$pageTitle = 'Actualités — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>
<h1 class="page-title">Actualit&eacute;s</h1>

<?php
$items = [
    ['Assembl&eacute;e g&eacute;n&eacute;rale annuelle',          '01/06/2026'],
    ['Nouveaux membres accueillis',                                  '28/05/2026'],
    ['Mise &agrave; jour du r&egrave;glement int&eacute;rieur',    '20/05/2026'],
    ['Sortie du week-end &mdash; inscriptions ouvertes',            '15/05/2026'],
    ['Publication du rapport d\'activit&eacute; 2025',             '05/05/2026'],
];
foreach ($items as [$title, $date]):
?>
<div class="card">
    <div class="news-item" style="border:none; padding:0;">
        <span class="date"><?= $date ?></span>
        <h3><?= $title ?></h3>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Placeholder text pour cette actualit&eacute;. Ut enim ad minim veniam.</p>
    </div>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
