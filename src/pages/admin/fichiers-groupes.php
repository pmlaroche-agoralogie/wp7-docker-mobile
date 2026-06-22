<?php
requireAdmin();
require_once __DIR__ . '/../../includes/files.php';

$pageTitle = 'Groupes de fichiers — ' . SITE_NAME;
$db        = getDB();

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_group') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $error = 'Le nom du groupe est requis.';
        } else {
            try {
                $db->prepare("INSERT INTO files_bo_groups (name, description) VALUES (?, ?)")
                   ->execute([$name, $desc]);
                $message = 'Groupe &laquo;' . htmlspecialchars($name) . '&raquo; cr&eacute;&eacute;.';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création du groupe.';
            }
        }
    }

    if ($action === 'delete_group') {
        $gid = (int)($_POST['group_id'] ?? 0);
        if ($gid) {
            $db->prepare("DELETE FROM files_bo_groups WHERE id = ?")->execute([$gid]);
            $message = 'Groupe supprim&eacute;.';
            // Redirect to avoid staying on a now-deleted group
            header('Location: /admin/fichiers-groupes?ok=1');
            exit;
        }
    }

    if ($action === 'add_member') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $uid = (int)($_POST['user_id']  ?? 0);
        if ($gid && $uid) {
            try {
                $db->prepare("INSERT OR IGNORE INTO files_bo_group_members (group_id, user_id) VALUES (?, ?)")
                   ->execute([$gid, $uid]);
                $message = 'Membre ajout&eacute;.';
            } catch (PDOException $e) {
                $error = 'Erreur lors de l\'ajout du membre.';
            }
        }
    }

    if ($action === 'remove_member') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $uid = (int)($_POST['user_id']  ?? 0);
        if ($gid && $uid) {
            $db->prepare("DELETE FROM files_bo_group_members WHERE group_id = ? AND user_id = ?")
               ->execute([$gid, $uid]);
            $message = 'Membre retir&eacute;.';
        }
    }
}

if (isset($_GET['ok'])) $message = 'Groupe supprim&eacute;.';

$groups   = $db->query("
    SELECT g.*, COUNT(m.user_id) AS nb_members
    FROM files_bo_groups g
    LEFT JOIN files_bo_group_members m ON m.group_id = g.id
    GROUP BY g.id
    ORDER BY g.name
")->fetchAll();

$activeGroupId = (int)($_GET['group'] ?? 0);
$activeGroup   = null;
$members       = [];
$nonMembers    = [];

if ($activeGroupId) {
    foreach ($groups as $g) {
        if ((int)$g['id'] === $activeGroupId) { $activeGroup = $g; break; }
    }
    if ($activeGroup) {
        $members = $db->prepare("
            SELECT u.id, u.username, u.email, u.exploitation_name
            FROM users u
            JOIN files_bo_group_members m ON m.user_id = u.id
            WHERE m.group_id = ?
            ORDER BY u.username
        ");
        $members->execute([$activeGroupId]);
        $members = $members->fetchAll();

        $memberIds = array_column($members, 'id');
        $allUsers  = $db->query("SELECT id, username, email, exploitation_name FROM users WHERE active = 1 ORDER BY username")->fetchAll();
        $nonMembers = array_filter($allUsers, fn($u) => !in_array((int)$u['id'], array_map('intval', $memberIds)));
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Groupes de fichiers</h1>
    <a href="/dashboard" class="btn btn-sm">&larr; Accueil</a>
</div>

<?php if ($message): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?= $message ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"   style="margin-bottom:1rem;"><?= $error ?></div><?php endif; ?>

<!-- Créer un groupe -->
<div class="card" style="margin-bottom:1.5rem;">
    <h2>Cr&eacute;er un groupe</h2>
    <form method="post" style="margin-top:.75rem;">
        <input type="hidden" name="action" value="create_group">
        <div class="form-group">
            <label>Nom du groupe</label>
            <input type="text" name="name" required placeholder="Ex. : &Eacute;leveurs Basco-B&eacute;arnais">
        </div>
        <div class="form-group">
            <label>Description <small style="font-weight:normal;">(facultative)</small></label>
            <input type="text" name="description" placeholder="Description courte">
        </div>
        <button type="submit" class="btn btn-primary">Cr&eacute;er</button>
    </form>
</div>

<!-- Liste des groupes -->
<div class="card" style="margin-bottom:1.5rem; padding:0; overflow:hidden;">
    <?php if (empty($groups)): ?>
        <div style="padding:2rem 1rem; text-align:center; color:var(--muted);">
            Aucun groupe cr&eacute;&eacute; pour l'instant.
        </div>
    <?php else: ?>
        <?php foreach ($groups as $i => $g):
            $isLast  = ($i === count($groups) - 1);
            $isActive = ((int)$g['id'] === $activeGroupId);
        ?>
        <div style="display:flex; align-items:center; gap:.75rem; padding:.85rem 1.1rem; flex-wrap:wrap;
                    border-bottom:<?= $isLast ? 'none' : '1px solid var(--border)' ?>;
                    background:<?= $isActive ? 'rgba(37,99,235,.05)' : 'transparent' ?>;">
            <div style="flex:1; min-width:0;">
                <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= htmlspecialchars($g['name']) ?>
                    <?php if ($isActive): ?>
                        <span style="font-size:.72rem; color:var(--primary); font-weight:400; margin-left:.4rem;">
                            &#9658; en cours d'&eacute;dition
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($g['description']): ?>
                <div style="font-size:.78rem; color:var(--muted); margin-top:.1rem;">
                    <?= htmlspecialchars($g['description']) ?>
                </div>
                <?php endif; ?>
                <div style="font-size:.74rem; color:var(--muted); margin-top:.15rem;">
                    <?= (int)$g['nb_members'] ?> membre<?= (int)$g['nb_members'] !== 1 ? 's' : '' ?>
                </div>
            </div>
            <div style="display:flex; gap:.4rem; flex-shrink:0; flex-wrap:wrap;">
                <a href="/admin/fichiers-groupes?group=<?= $g['id'] ?>"
                   class="btn btn-sm" style="<?= $isActive ? 'background:var(--primary);color:#fff;' : '' ?>">
                    Membres
                </a>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Supprimer le groupe &laquo;<?= htmlspecialchars(addslashes($g['name'])) ?>&raquo; ? Les fichiers associés ne seront pas supprimés.')">
                    <input type="hidden" name="action"   value="delete_group">
                    <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="color:#ef4444;">Supprimer</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Gestion des membres du groupe actif -->
<?php if ($activeGroup): ?>
<div class="card" style="margin-bottom:1.5rem; border-left:4px solid var(--primary);">
    <h2 style="margin-bottom:1rem;">
        Membres du groupe &laquo;<?= htmlspecialchars($activeGroup['name']) ?>&raquo;
    </h2>

    <!-- Membres actuels -->
    <?php if (empty($members)): ?>
        <p style="color:var(--muted); font-size:.85rem; margin-bottom:1rem;">Aucun membre dans ce groupe.</p>
    <?php else: ?>
        <div style="margin-bottom:1.25rem; overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Identifiant</th>
                        <th>Exploitation</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['username']) ?></td>
                        <td><?= htmlspecialchars($m['exploitation_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td>
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('Retirer <?= htmlspecialchars(addslashes($m['username'])) ?> du groupe ?')">
                                <input type="hidden" name="action"   value="remove_member">
                                <input type="hidden" name="group_id" value="<?= $activeGroupId ?>">
                                <input type="hidden" name="user_id"  value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="color:#ef4444;">Retirer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Ajouter un membre -->
    <?php if (!empty($nonMembers)): ?>
    <h3 style="font-size:.9rem; margin-bottom:.65rem;">Ajouter un membre</h3>
    <form method="post" style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <input type="hidden" name="action"   value="add_member">
        <input type="hidden" name="group_id" value="<?= $activeGroupId ?>">
        <select name="user_id" required
                style="flex:1; min-width:180px; padding:.5rem .7rem; border:1px solid var(--border);
                       border-radius:8px; font-size:.9rem; background:var(--bg);">
            <option value="">-- Choisir un utilisateur --</option>
            <?php foreach ($nonMembers as $u): ?>
                <option value="<?= $u['id'] ?>">
                    <?= htmlspecialchars($u['username']) ?>
                    <?php if ($u['exploitation_name']): ?>
                        — <?= htmlspecialchars($u['exploitation_name']) ?>
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Ajouter</button>
    </form>
    <?php else: ?>
        <p style="font-size:.82rem; color:var(--muted);">Tous les utilisateurs actifs sont d&eacute;j&agrave; membres de ce groupe.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
