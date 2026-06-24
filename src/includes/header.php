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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? SITE_NAME) ?></title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ELVEA">
    <meta name="application-name" content="ELVEA">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/media/logo-elvea64.jpg">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (isset($extraHead)) echo $extraHead; ?>
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
      }
    </script>
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="/" class="site-logo">
            <img src="/media/logo-elvea64.jpg" alt="Logo ELVEA">
            <span class="logo-text">
                <?php if ($currentUser): ?>
                    <span class="logo-name"><?= htmlspecialchars($currentUser['exploitation_name'] ?: $currentUser['username']) ?></span>
                <?php else: ?>
                    <span class="logo-name">ELVEA</span>
                    <span class="logo-sub">B&eacute;arn &middot; Pays Basque &middot; Landes</span>
                <?php endif; ?>
            </span>
        </a>
        <button class="menu-toggle" aria-label="Menu" onclick="toggleMenu()">&#9776;</button>
    </div>
    <nav class="main-nav" id="main-nav">
        <ul>
            <?= navLink('Accueil', '/', $currentUri) ?>
            <?= navLink('&Eacute;leveurs', '/eleveurs', $currentUri) ?>
            <?= navLink('Fili&egrave;res', '/filieres', $currentUri) ?>
            <?= navLink('Oribase', '/oribase', $currentUri) ?>
            <?= navLink('Adh&eacute;sion', '/adhesion', $currentUri) ?>
            <?= navLink('Actualit&eacute;s', '/news', $currentUri) ?>
            <?php if ($currentUser): ?>
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
