<?php
requireAdmin();

$cotationsDir = '/var/www/html/media/cotations';
$error = '';
$success = '';

// ── POST actions ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $file = $_FILES['pdf'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Erreur lors du téléversement du fichier.';
        } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            $error = 'Seuls les fichiers PDF sont acceptés.';
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            $error = 'Le fichier ne doit pas dépasser 20 Mo.';
        } else {
            $safeName = preg_replace('/[^A-Za-z0-9\-_.]/', '-', basename($file['name']));
            $dest = $cotationsDir . '/' . $safeName;
            if (!is_dir($cotationsDir)) {
                mkdir($cotationsDir, 0775, true);
            }
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $success = 'Fichier « ' . htmlspecialchars($safeName) . ' » ajouté avec succès.';
            } else {
                $error = 'Impossible de déplacer le fichier. Vérifiez les permissions du dossier.';
            }
        }
    }

    if ($action === 'delete') {
        $filename = basename($_POST['filename'] ?? '');
        if ($filename && preg_match('/\.pdf$/i', $filename)) {
            $path = $cotationsDir . '/' . $filename;
            if (file_exists($path)) {
                unlink($path);
                $success = 'Fichier « ' . htmlspecialchars($filename) . ' » supprimé.';
            }
        }
    }
}

// Build sorted list (most recent first)
$pdfFiles = [];
if (is_dir($cotationsDir)) {
    foreach (glob($cotationsDir . '/*.pdf') as $file) {
        $pdfFiles[] = ['name' => basename($file), 'mtime' => filemtime($file)];
    }
}
usort($pdfFiles, fn($a, $b) => $b['mtime'] - $a['mtime']);

$pageTitle = 'Gestion cotations — ' . SITE_NAME;
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Cotations PDF</h1>
    <a href="/dashboard" class="btn btn-sm">&larr; Tableau de bord</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1.5rem;">
    <h2 style="margin-bottom:1rem;">Ajouter un PDF</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
            <label for="pdf">Fichier PDF (max 20&nbsp;Mo)</label>
            <input type="file" id="pdf" name="pdf" accept=".pdf,application/pdf" required
                   style="display:block; margin-top:.35rem;">
        </div>
        <button type="submit" class="btn btn-primary">&#11014; T&eacute;l&eacute;verser</button>
    </form>
</div>

<div class="card" style="padding:0; overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Fichier</th>
                <th>Date</th>
                <th style="text-align:center;">Dernier</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($pdfFiles)): ?>
            <tr><td colspan="4" class="text-muted" style="text-align:center; padding:1.5rem;">Aucun PDF dans ce dossier.</td></tr>
        <?php endif; ?>
        <?php foreach ($pdfFiles as $i => $f): ?>
            <tr>
                <td>
                    <a href="/media/cotations/<?= rawurlencode($f['name']) ?>" target="_blank" rel="noopener"
                       style="color:var(--primary); text-decoration:none; font-size:.9rem;">
                        &#128462; <?= htmlspecialchars($f['name']) ?>
                    </a>
                </td>
                <td style="white-space:nowrap; font-size:.82rem; color:var(--muted);">
                    <?= date('d/m/Y H:i', $f['mtime']) ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($i === 0): ?>
                        <span style="background:var(--primary); color:#fff; border-radius:999px;
                                     padding:.1rem .55rem; font-size:.72rem; font-weight:700;">
                            &#10003; affiché
                        </span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;">
                    <form method="post" style="display:inline;"
                          onsubmit="return confirm('Supprimer ce fichier définitivement ?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="filename" value="<?= htmlspecialchars($f['name']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">&#10005; Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
