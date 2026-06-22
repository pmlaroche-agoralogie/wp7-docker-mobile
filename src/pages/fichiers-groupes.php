<?php
requireLogin();
require_once __DIR__ . '/../includes/files.php';

$user      = getCurrentUser();
$pageTitle = 'Fichiers groupes — ' . SITE_NAME;

recordPageVisit($user['id'], 'fichiers-groupes');

$groups  = fileUserGroups($user['id']);
$groupId = isset($_GET['group']) ? (int)$_GET['group'] : null;

// Auto-select first group if none specified
if ($groupId === null && !empty($groups)) {
    $groupId = (int)$groups[0]['id'];
}

// Validate group membership
$currentGroup = null;
if ($groupId !== null) {
    foreach ($groups as $g) {
        if ((int)$g['id'] === $groupId) {
            $currentGroup = $g;
            break;
        }
    }
    if ($currentGroup === null) {
        header('Location: /fichiers-groupes');
        exit;
    }
}

$currentPath = fileNormalizePath($_GET['path'] ?? '/');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentGroup !== null) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && isset($_FILES['file'])) {
        $uploadPath = fileNormalizePath($_POST['upload_path'] ?? $currentPath);
        $result     = fileUpload($_FILES['file'], $user['id'], $groupId, $uploadPath);
        if ($result['ok']) {
            header('Location: /fichiers-groupes?group=' . $groupId . '&path=' . urlencode($uploadPath) . '&ok=1');
            exit;
        }
        $error = $result['error'];
    }

    if ($action === 'delete' && isset($_POST['file_id'])) {
        $fileId = (int)$_POST['file_id'];
        $rec    = fileGetById($fileId);
        if ($rec && (int)$rec['group_id'] === $groupId && (int)$rec['owner_id'] === $user['id']) {
            fileDelete($fileId, $user['id']);
        }
        header('Location: /fichiers-groupes?group=' . $groupId . '&path=' . urlencode($currentPath));
        exit;
    }
}

if (isset($_GET['ok'])) $success = 'Fichier téléversé avec succès.';

$files   = [];
$subdirs = [];
$crumbs  = [];
if ($currentGroup !== null) {
    $listing = fileListDir($user['id'], $groupId, $currentPath);
    $files   = $listing['files'];
    $subdirs = $listing['subdirs'];
    $crumbs  = fileBreadcrumb($currentPath);
}

$pathDepth   = $currentPath === '/' ? 0 : count(array_filter(explode('/', trim($currentPath, '/'))));
$canGoDeeper = $pathDepth < 5;

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">&#128101; Fichiers groupes</h1>
    <a href="/dashboard" class="btn btn-sm">&larr; Accueil</a>
    <a href="/fichiers" class="btn btn-sm" style="margin-left:auto;">Mes fichiers &rarr;</a>
</div>

<?php if (empty($groups)): ?>
    <div class="card" style="text-align:center; padding:2.5rem 1rem; color:var(--muted);">
        <div style="font-size:2.5rem; margin-bottom:.75rem;">&#128101;</div>
        <p>Vous n'appartenez &agrave; aucun groupe pour l'instant.</p>
    </div>

<?php else: ?>

    <!-- Sélecteur de groupe -->
    <div class="card" style="margin-bottom:1.25rem; padding:.8rem 1rem;">
        <div style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
            <span style="font-size:.82rem; color:var(--muted); flex-shrink:0;">Groupe&nbsp;:</span>
            <?php foreach ($groups as $g): ?>
                <a href="/fichiers-groupes?group=<?= $g['id'] ?>"
                   style="padding:.35rem .85rem; border-radius:999px; font-size:.82rem; text-decoration:none;
                          border:1px solid <?= ($currentGroup && (int)$g['id'] === (int)$currentGroup['id']) ? 'var(--primary)' : 'var(--border)' ?>;
                          color:<?= ($currentGroup && (int)$g['id'] === (int)$currentGroup['id']) ? 'var(--primary)' : 'var(--text)' ?>;
                          background:<?= ($currentGroup && (int)$g['id'] === (int)$currentGroup['id']) ? 'rgba(37,99,235,.07)' : 'transparent' ?>;">
                    <?= htmlspecialchars($g['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($currentGroup !== null): ?>

        <!-- Fil d'Ariane -->
        <div style="display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; margin-bottom:1rem;
                    font-size:.85rem; color:var(--muted);">
            <a href="/fichiers-groupes?group=<?= $groupId ?>"
               style="color:var(--primary); text-decoration:none;">&#8962; <?= htmlspecialchars($currentGroup['name']) ?></a>
            <?php foreach ($crumbs as $crumb): ?>
                <span style="color:var(--border);">/</span>
                <a href="/fichiers-groupes?group=<?= $groupId ?>&path=<?= urlencode($crumb['path']) ?>"
                   style="color:var(--primary); text-decoration:none;"><?= htmlspecialchars($crumb['label']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Contenu du répertoire -->
        <div class="card" style="padding:0; overflow:hidden; margin-bottom:1.25rem;">
            <?php if (empty($subdirs) && empty($files)): ?>
                <div style="padding:2.5rem 1rem; text-align:center; color:var(--muted);">
                    <div style="font-size:2.5rem; margin-bottom:.75rem;">&#128193;</div>
                    <p>Ce dossier est vide.</p>
                </div>
            <?php else: ?>

                <?php foreach ($subdirs as $i => $subdir):
                    $subPath = ($currentPath === '/') ? '/' . $subdir : $currentPath . '/' . $subdir;
                    $isLast  = ($i === count($subdirs) - 1) && empty($files);
                ?>
                <a href="/fichiers-groupes?group=<?= $groupId ?>&path=<?= urlencode($subPath) ?>"
                   style="display:flex; align-items:center; gap:.75rem; padding:.85rem 1.1rem;
                          text-decoration:none; color:inherit;
                          border-bottom:<?= $isLast ? 'none' : '1px solid var(--border)' ?>; background:rgba(37,99,235,.03);">
                    <span style="font-size:1.3rem; flex-shrink:0;">&#128193;</span>
                    <span style="font-weight:600;"><?= htmlspecialchars($subdir) ?></span>
                    <span style="margin-left:auto; color:var(--muted); font-size:.8rem;">&#8250;</span>
                </a>
                <?php endforeach; ?>

                <?php foreach ($files as $i => $f):
                    $isLast  = ($i === count($files) - 1);
                    $isMedia = fileMimeIsMedia($f['mime_type']);
                    $isOwner = (int)$f['owner_id'] === $user['id'];
                ?>
                <div style="display:flex; align-items:center; gap:.6rem; padding:.8rem 1.1rem; flex-wrap:wrap;
                            border-bottom:<?= $isLast ? 'none' : '1px solid var(--border)' ?>;">
                    <span style="font-size:1.2rem; flex-shrink:0;">
                        <?php
                            if (str_starts_with($f['mime_type'], 'image/'))     echo '&#128444;';
                            elseif (str_starts_with($f['mime_type'], 'video/')) echo '&#127916;';
                            elseif ($f['mime_type'] === 'application/pdf')      echo '&#128462;';
                            else                                                 echo '&#128196;';
                        ?>
                    </span>
                    <div style="flex:1; min-width:0;">
                        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.88rem;">
                            <?= htmlspecialchars($f['original_name']) ?>
                        </div>
                        <div style="font-size:.72rem; color:var(--muted);">
                            <?= fileHumanSize((int)$f['file_size']) ?>
                            &middot; <?= date('d/m/Y', strtotime($f['created_at'])) ?>
                        </div>
                    </div>
                    <div style="display:flex; gap:.4rem; flex-shrink:0;">
                        <?php if ($isMedia): ?>
                        <button onclick="previewFile(<?= $f['id'] ?>, '<?= htmlspecialchars($f['mime_type']) ?>')"
                                title="Aperçu"
                                style="background:none; border:1px solid var(--border); border-radius:6px;
                                       padding:.3rem .55rem; cursor:pointer; font-size:.95rem; color:var(--muted);">
                            &#128065;
                        </button>
                        <?php endif; ?>
                        <a href="/fichiers-dl?id=<?= $f['id'] ?>"
                           title="Télécharger"
                           style="display:inline-flex; align-items:center; border:1px solid var(--border); border-radius:6px;
                                  padding:.3rem .55rem; text-decoration:none; font-size:.95rem; color:var(--primary);">
                            &#8681;
                        </a>
                        <?php if ($isOwner): ?>
                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('Supprimer « <?= htmlspecialchars(addslashes($f['original_name'])) ?> » ?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="file_id" value="<?= $f['id'] ?>">
                            <button type="submit"
                                    style="background:none; border:1px solid var(--border); border-radius:6px;
                                           padding:.3rem .55rem; cursor:pointer; font-size:.95rem; color:#ef4444;">
                                &#128465;
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulaire d'upload -->
        <div class="card">
            <h2 style="font-size:.95rem; margin-bottom:.9rem;">&#128228; Ajouter un fichier au groupe</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div style="margin-bottom:.75rem;">
                    <label style="font-size:.82rem; color:var(--muted); display:block; margin-bottom:.3rem;">Fichier</label>
                    <input type="file" name="file" required
                           style="width:100%; padding:.5rem .7rem; border:1px solid var(--border);
                                  border-radius:8px; font-size:.9rem; background:var(--bg);">
                </div>
                <div style="margin-bottom:.85rem;">
                    <label style="font-size:.82rem; color:var(--muted); display:block; margin-bottom:.3rem;">
                        Dossier de destination
                    </label>
                    <input type="text" name="upload_path" value="<?= htmlspecialchars($currentPath) ?>"
                           placeholder="/sous-dossier"
                           style="width:100%; padding:.5rem .7rem; border:1px solid var(--border);
                                  border-radius:8px; font-size:.9rem;">
                </div>
                <button type="submit" class="btn btn-primary">T&eacute;l&eacute;verser</button>
            </form>
        </div>

        <!-- Navigation sous-dossier -->
        <?php if ($canGoDeeper): ?>
        <div class="card" style="margin-top:1rem;">
            <h2 style="font-size:.95rem; margin-bottom:.9rem;">&#128193; Naviguer vers un sous-dossier</h2>
            <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                <input type="text" id="subdir-input" placeholder="nom-du-dossier"
                       style="flex:1; min-width:140px; padding:.5rem .7rem; border:1px solid var(--border);
                              border-radius:8px; font-size:.9rem;">
                <button type="button" onclick="navigateSubdir()" class="btn btn-primary">Ouvrir</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modale aperçu -->
        <div id="preview-modal" onclick="closePreview()"
             style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.82);
                    z-index:9999; align-items:center; justify-content:center; padding:1rem;">
            <div onclick="event.stopPropagation()"
                 style="max-width:95vw; max-height:90vh; position:relative;">
                <button onclick="closePreview()"
                        style="position:absolute; top:-.5rem; right:-.5rem; background:#fff; border:none;
                               border-radius:50%; width:2rem; height:2rem; font-size:1.1rem;
                               cursor:pointer; z-index:10; line-height:1; box-shadow:0 2px 8px rgba(0,0,0,.3);">
                    &times;
                </button>
                <div id="preview-content"></div>
            </div>
        </div>

        <script>
        function previewFile(id, mime) {
            var url = '/fichiers-dl?id=' + id + '&inline=1';
            var content = '';
            if (mime.startsWith('image/')) {
                content = '<img src="' + url + '" style="max-width:90vw; max-height:85vh; border-radius:8px; display:block;">';
            } else if (mime.startsWith('video/')) {
                content = '<video controls autoplay playsinline style="max-width:90vw; max-height:85vh; border-radius:8px; display:block;">'
                        + '<source src="' + url + '" type="' + mime + '"></video>';
            }
            document.getElementById('preview-content').innerHTML = content;
            document.getElementById('preview-modal').style.display = 'flex';
        }
        function closePreview() {
            document.getElementById('preview-modal').style.display = 'none';
            document.getElementById('preview-content').innerHTML = '';
        }
        function navigateSubdir() {
            var base   = <?= json_encode($currentPath) ?>;
            var subdir = document.getElementById('subdir-input').value.trim();
            if (!subdir) return;
            var path = (base === '/') ? '/' + subdir : base + '/' + subdir;
            location.href = '/fichiers-groupes?group=<?= $groupId ?>&path=' + encodeURIComponent(path);
        }
        </script>

    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
