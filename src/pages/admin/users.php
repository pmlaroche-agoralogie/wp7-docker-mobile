<?php
requireAdmin();
$pageTitle = 'Utilisateurs — ' . SITE_NAME;
$db = getDB();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $role     = in_array($_POST['role'] ?? '', ['user', 'admin']) ? $_POST['role'] : 'user';

        if ($username && $email && $password) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)")
                   ->execute([$username, $email, $hash, $role]);
                $message = "Utilisateur &laquo;" . htmlspecialchars($username) . "&raquo; cr&eacute;&eacute;.";
            } catch (PDOException $e) {
                $error = "Erreur : identifiant ou email d&eacute;j&agrave; utilis&eacute;.";
            }
        } else {
            $error = "Tous les champs sont requis.";
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE users SET active = 1 - active WHERE id = ?")->execute([$id]);
        }
    }
}

$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>
<h1 class="page-title">Gestion des utilisateurs</h1>

<?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

<div class="card" style="margin-bottom:1.5rem;">
    <h2>Ajouter un utilisateur</h2>
    <form method="post" style="margin-top:.75rem;">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label>Identifiant</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>R&ocirc;le</label>
            <select name="role">
                <option value="user">Utilisateur</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Cr&eacute;er</button>
    </form>
</div>

<div class="card">
    <h2>Membres (<?= count($users) ?>)</h2>
    <div style="overflow-x:auto; margin-top:.75rem;">
        <table>
            <thead>
                <tr>
                    <th>Identifiant</th>
                    <th>Email</th>
                    <th>R&ocirc;le</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td><?= $u['active'] ? '&#10003; Actif' : '&#10007; D&eacute;sactiv&eacute;' ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="btn btn-sm">
                                <?= $u['active'] ? 'D&eacute;sactiver' : 'Activer' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
