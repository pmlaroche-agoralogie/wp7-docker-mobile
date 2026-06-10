<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    startSession();
    $_SESSION['user_id']          = $user['id'];
    $_SESSION['username']         = $user['username'];
    $_SESSION['role']             = $user['role'];
    $_SESSION['exploitation_name'] = $user['exploitation_name'] ?? null;

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + COOKIE_LIFETIME);
    $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
       ->execute([$user['id'], $token, $expires]);
    setcookie('remember_token', $token, time() + COOKIE_LIFETIME, '/', '', false, true);

    return true;
}

function logout(): void {
    startSession();
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $db->prepare("DELETE FROM remember_tokens WHERE token = ?")
           ->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    $_SESSION = [];
    session_destroy();
}

function getCurrentUser(): ?array {
    startSession();

    if (isset($_SESSION['user_id'])) {
        return [
            'id'               => $_SESSION['user_id'],
            'username'         => $_SESSION['username'],
            'role'             => $_SESSION['role'],
            'exploitation_name' => $_SESSION['exploitation_name'] ?? null,
        ];
    }

    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.role, u.exploitation_name FROM users u
            JOIN remember_tokens rt ON rt.user_id = u.id
            WHERE rt.token = ? AND rt.expires_at > datetime('now') AND u.active = 1
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id']           = $user['id'];
            $_SESSION['username']          = $user['username'];
            $_SESSION['role']              = $user['role'];
            $_SESSION['exploitation_name'] = $user['exploitation_name'] ?? null;
            return $user;
        }

        setcookie('remember_token', '', time() - 3600, '/');
    }

    return null;
}

function requireLogin(): void {
    if (!getCurrentUser()) {
        header('Location: /login');
        exit;
    }
}

function requireAdmin(): void {
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: /');
        exit;
    }
}

function isLoggedIn(): bool {
    return getCurrentUser() !== null;
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user !== null && $user['role'] === 'admin';
}
