<?php
requireAdmin();
$pageTitle = 'Utilisateurs — ' . SITE_NAME;
$db = getDB();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username        = trim($_POST['username']        ?? '');
        $email           = trim($_POST['email']           ?? '');
        $password        = $_POST['password']             ?? '';
        $role            = in_array($_POST['role'] ?? '', ['user', 'admin']) ? $_POST['role'] : 'user';
        $exploitation    = trim($_POST['exploitation_name'] ?? '');
        $phone           = trim($_POST['phone']           ?? '');

        if ($username && $email && $password) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO users (username, email, password_hash, role, exploitation_name, phone) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$username, $email, $hash, $role, $exploitation ?: null, $phone ?: null]);
                $message = "Utilisateur &laquo;" . htmlspecialchars($username) . "&raquo; cr&eacute;&eacute;.";
            } catch (PDOException $e) {
                $error = "Erreur : identifiant ou email d&eacute;j&agrave; utilis&eacute;.";
            }
        } else {
            $error = "Identifiant, email et mot de passe sont requis.";
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE users SET active = 1 - active WHERE id = ?")->execute([$id]);
        }
    }

    if ($action === 'edit') {
        $id           = (int)($_POST['id'] ?? 0);
        $email        = trim($_POST['email']            ?? '');
        $exploitation = trim($_POST['exploitation_name'] ?? '');
        $phone        = trim($_POST['phone']            ?? '');
        $role         = in_array($_POST['role'] ?? '', ['user', 'admin']) ? $_POST['role'] : 'user';
        $password     = $_POST['password'] ?? '';

        if ($id && $email) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET email=?, role=?, exploitation_name=?, phone=?, password_hash=? WHERE id=?")
                   ->execute([$email, $role, $exploitation ?: null, $phone ?: null, $hash, $id]);
            } else {
                $db->prepare("UPDATE users SET email=?, role=?, exploitation_name=?, phone=? WHERE id=?")
                   ->execute([$email, $role, $exploitation ?: null, $phone ?: null, $id]);
            }
            $message = "Utilisateur mis &agrave; jour.";
        }
    }
}

$users   = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$editId  = (int)($_GET['edit'] ?? 0);
$editUser = null;
if ($editId) {
    foreach ($users as $u) {
        if ((int)$u['id'] === $editId) { $editUser = $u; break; }
    }
}

include __DIR__ . '/../../includes/header.php';
?>
<h1 class="page-title">Gestion des utilisateurs</h1>

<?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

<?php if ($editUser): ?>
<div class="card" style="margin-bottom:1.5rem; border-left:4px solid var(--primary);">
    <h2>Modifier — <?= htmlspecialchars($editUser['username']) ?></h2>
    <form method="post" style="margin-top:.75rem;">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']) ?>" required>
        </div>
        <div class="form-group">
            <label>Nom de l&rsquo;exploitation</label>
            <input type="text" name="exploitation_name" value="<?= htmlspecialchars($editUser['exploitation_name'] ?? '') ?>" placeholder="Ex. : GAEC des Pyrénées">
        </div>
        <div class="form-group">
            <label>T&eacute;l&eacute;phone</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>" placeholder="06 00 00 00 00">
        </div>
        <div class="form-group">
            <label>R&ocirc;le</label>
            <select name="role">
                <option value="user"  <?= $editUser['role'] === 'user'  ? 'selected' : '' ?>>Utilisateur</option>
                <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="form-group">
            <label>Nouveau mot de passe <small style="font-weight:normal;">(laisser vide pour ne pas changer)</small></label>
            <input type="password" name="password" autocomplete="new-password">
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="/admin/users" class="btn">Annuler</a>
        </div>
    </form>
</div>
<?php endif; ?>

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
            <label>Nom de l&rsquo;exploitation</label>
            <input type="text" name="exploitation_name" placeholder="Ex. : GAEC des Pyrénées">
        </div>
        <div class="form-group">
            <label>T&eacute;l&eacute;phone</label>
            <input type="tel" name="phone" placeholder="06 00 00 00 00">
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
                    <th>Exploitation</th>
                    <th>T&eacute;l&eacute;phone</th>
                    <th>Email</th>
                    <th>R&ocirc;le</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['exploitation_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['role']) ?></td>
                    <td><?= $u['active'] ? '&#10003; Actif' : '&#10007; D&eacute;sactiv&eacute;' ?></td>
                    <td style="white-space:nowrap;">
                        <a href="/admin/users?edit=<?= (int)$u['id'] ?>" class="btn btn-sm">&Eacute;diter</a>
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
