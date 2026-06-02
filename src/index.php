<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$routes = [
    ''            => 'pages/home.php',
    'home'        => 'pages/home.php',
    'news'        => 'pages/news.php',
    'about'       => 'pages/about.php',
    'contact'     => 'pages/contact.php',
    'login'       => 'pages/login.php',
    'logout'      => 'pages/logout.php',
    'dashboard'   => 'pages/dashboard.php',
    'admin/users' => 'pages/admin/users.php',
];

$page = isset($routes[$uri]) ? __DIR__ . '/' . $routes[$uri] : null;

if ($page === null || !file_exists($page)) {
    http_response_code(404);
    include __DIR__ . '/pages/404.php';
    exit;
}

include $page;
