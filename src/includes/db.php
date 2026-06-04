<?php
require_once __DIR__ . '/../config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDB($pdo);
    }
    return $pdo;
}

function initDB(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            title TEXT NOT NULL,
            content TEXT DEFAULT '',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS page_blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_slug TEXT NOT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            type TEXT NOT NULL DEFAULT 'html',
            content TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS produits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titre TEXT NOT NULL,
            description TEXT DEFAULT '',
            prix_ht REAL NOT NULL DEFAULT 0,
            photo TEXT DEFAULT NULL,
            visible INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS paniers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            statut TEXT NOT NULL DEFAULT 'panier',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS panier_lignes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            panier_id INTEGER NOT NULL,
            produit_id INTEGER NOT NULL,
            quantite INTEGER NOT NULL DEFAULT 1,
            FOREIGN KEY (panier_id) REFERENCES paniers(id) ON DELETE CASCADE,
            FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
        );
    ");

    // Default admin
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
    if ((int)$stmt->fetch()['cnt'] === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
            ->execute(['admin', 'admin@local.dev', $hash]);
    }

    // Seed pages
    $seedPages = [
        ['home',     'Accueil'],
        ['eleveurs', 'Éleveurs'],
        ['filieres', 'Filières'],
        ['oribase',  'Oribase'],
        ['adhesion', 'Adhésion'],
    ];
    $ins = $pdo->prepare("INSERT OR IGNORE INTO pages (slug, title, content) VALUES (?, ?, '')");
    foreach ($seedPages as [$s, $t]) {
        $ins->execute([$s, $t]);
    }

    // Migrate legacy page.content → first HTML block (one-time)
    $rows = $pdo->query("SELECT slug, content FROM pages WHERE content != ''")->fetchAll();
    foreach ($rows as $row) {
        $check = $pdo->prepare("SELECT COUNT(*) as cnt FROM page_blocks WHERE page_slug = ?");
        $check->execute([$row['slug']]);
        if ((int)$check->fetch()['cnt'] === 0) {
            $pdo->prepare("INSERT INTO page_blocks (page_slug, position, type, content) VALUES (?, 0, 'html', ?)")
                ->execute([$row['slug'], $row['content']]);
        }
    }
}
