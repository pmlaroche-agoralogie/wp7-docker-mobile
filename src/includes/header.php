<?php
$currentUser = getCurrentUser();
$currentUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

function navLink(string $label, string $href, string $uri): string {
    $active = ($uri === trim($href, '/')) ? ' class="active"' : '';
    return "<li><a href=\"$href\"$active>$label</a></li>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? SITE_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="/" class="site-logo"><?= SITE_NAME ?></a>
        <button class="menu-toggle" aria-label="Menu" onclick="toggleMenu()">&#9776;</button>
    </div>
    <nav class="main-nav" id="main-nav">
        <ul>
            <?= navLink('Accueil', '/', $currentUri) ?>
            <?= navLink('Actualit&eacute;s', '/news', $currentUri) ?>
            <?= navLink('&Agrave; propos', '/about', $currentUri) ?>
            <?= navLink('Contact', '/contact', $currentUri) ?>
            <?php if ($currentUser): ?>
                <?= navLink('Mon espace', '/dashboard', $currentUri) ?>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <?= navLink('Admin', '/admin/users', $currentUri) ?>
                <?php endif; ?>
                <li><a href="/logout">D&eacute;connexion <span class="nav-user">(<?= htmlspecialchars($currentUser['username']) ?>)</span></a></li>
            <?php else: ?>
                <li><a href="/login" class="btn-login">Se connecter</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<main class="main-content">
