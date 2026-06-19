<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Redirect authenticated users from home to dashboard
if (($uri === '' || $uri === 'home') && isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

// Dynamic content pages (slug-based, editable via admin)
$contentSlugs = ['eleveurs', 'filieres', 'oribase', 'adhesion'];
if ($uri === '' || $uri === 'home' || in_array($uri, $contentSlugs)) {
    $slug = ($uri === '' || $uri === 'home') ? 'home' : $uri;
    include __DIR__ . '/pages/content.php';
    exit;
}

$routes = [
    'news'               => 'pages/news.php',
    'about'              => 'pages/about.php',
    'contact'            => 'pages/contact.php',
    'login'              => 'pages/login.php',
    'logout'             => 'pages/logout.php',
    'dashboard'          => 'pages/dashboard.php',
    'produits'           => 'pages/produits.php',
    'panier'             => 'pages/panier.php',
    'admin/users'        => 'pages/admin/users.php',
    'admin/pages'        => 'pages/admin/pages.php',
    'admin/block'        => 'pages/admin/block.php',
    'admin/produits'     => 'pages/admin/produits.php',
    'admin/produit-edit' => 'pages/admin/produit-edit.php',
    'admin/commandes'    => 'pages/admin/commandes.php',
    'annonces'           => 'pages/annonces.php',
    'annonce'            => 'pages/annonce.php',
    'admin/annonces'     => 'pages/admin/annonces.php',
    'admin/annonce-edit' => 'pages/admin/annonce-edit.php',
    'api/meteo-set'      => 'pages/api/meteo-set.php',
    'cotations'          => 'pages/cotations.php',
    'admin/cotations'    => 'pages/admin/cotations.php',
    'messages'               => 'pages/messages.php',
    'message'                => 'pages/message.php',
    'admin/messages'         => 'pages/admin/messages.php',
    'admin/message-new'      => 'pages/admin/message-new.php',
    'admin/fichiers-groupes' => 'pages/admin/fichiers-groupes.php',
    'fichiers'               => 'pages/fichiers.php',
    'fichiers-groupes'       => 'pages/fichiers-groupes.php',
    'fichiers-dl'            => 'pages/fichiers-dl.php',
    'api/put_user_file'      => 'pages/api/put_user_file.php',
];

$page = isset($routes[$uri]) ? __DIR__ . '/' . $routes[$uri] : null;

if ($page === null || !file_exists($page)) {
    http_response_code(404);
    include __DIR__ . '/pages/404.php';
    exit;
}

include $page;
