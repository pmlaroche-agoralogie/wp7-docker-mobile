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

        CREATE TABLE IF NOT EXISTS annonces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titre TEXT NOT NULL,
            texte TEXT DEFAULT '',
            tag TEXT DEFAULT 'divers',
            prix REAL DEFAULT NULL,
            visible INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS annonce_medias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            annonce_id INTEGER NOT NULL,
            fichier TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'photo',
            position INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS annonce_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            annonce_id INTEGER NOT NULL,
            nom TEXT NOT NULL,
            email TEXT NOT NULL,
            message TEXT NOT NULL,
            lu INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT (datetime('now')),
            FOREIGN KEY (sender_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS message_recipients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            read_at DATETIME DEFAULT NULL,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(message_id, user_id)
        );

        CREATE TABLE IF NOT EXISTS message_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME DEFAULT (datetime('now')),
            read_by_admin INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS message_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
        );
    ");

    // Migrate: add meteo_code_insee to users if missing
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN meteo_code_insee TEXT DEFAULT '64430'");
    } catch (\PDOException $e) {}

    // Migrate: add group_name to users if missing
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN group_name TEXT DEFAULT NULL");
    } catch (\PDOException $e) {}

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

    // Seed annonces
    $aCount = (int)$pdo->query("SELECT COUNT(*) FROM annonces")->fetchColumn();
    if ($aCount === 0) {
        $insA = $pdo->prepare("INSERT INTO annonces (titre, texte, tag, prix, visible) VALUES (?, ?, ?, ?, 1)");
        $insA->execute(['Tracteur John Deere 6130R', "Tracteur en bon état général, révisé en 2024. Cabine climatisée, chargeur frontal inclus. Nombreuses heures de travail mais bien entretenu. À voir sur place.", 'matériels', 45000]);
        $insA->execute(['Lots de foin de prairie naturelle', "Foin de bonne qualité, 1ère coupe, en bottes rondes de 300 kg. Stocké sous abri. Disponible immédiatement, livraison possible dans un rayon de 30 km.", 'aliments', 35]);
        $insA->execute(['2 brebis laitières Manech', "Brebis de race Manech tête rousse, 3 ans, bonnes productrices. Agnelage annuel régulier. Idéales pour fromage fermier. Vente groupée uniquement.", 'animaux', 280]);
        $insA->execute(['Épandeur fumier Joskin 5000L', "Épandeur fumier à axe horizontal, bon état de fonctionnement. Pneus neufs posés en 2025. Quelques traces d'usure normales.", 'matériels', 8500]);
        $insA->execute(['Agnelles Blanche du Massif Central', "Lot de 5 agnelles de 8 mois, prêtes à la reproduction. Race rustique, adaptation facile aux parcours difficiles. Certifiées Scrapie R/R.", 'animaux', 150]);
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
