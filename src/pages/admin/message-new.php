<?php
requireAdmin();
$db   = getDB();
$user = getCurrentUser();

$uploadDir = '/var/www/html/uploads/messages/';
$errors    = [];

// Active users + distinct groups
$allUsers = $db->query("SELECT id, username, group_name FROM users WHERE active = 1 ORDER BY username ASC")->fetchAll();
$groups   = $db->query("SELECT DISTINCT group_name FROM users WHERE group_name IS NOT NULL AND group_name != '' ORDER BY group_name ASC")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject       = trim($_POST['subject'] ?? '');
    $body          = $_POST['body'] ?? '';
    $recipientType = $_POST['recipient_type'] ?? 'individual';

    if ($subject === '') $errors[] = 'L\'objet est obligatoire.';
    if (trim(strip_tags($body)) === '') $errors[] = 'Le corps du message est obligatoire.';

    // Resolve recipient IDs
    $recipientIds = [];
    if (empty($errors)) {
        if ($recipientType === 'individual') {
            $uid = (int)($_POST['recipient_user'] ?? 0);
            if ($uid > 0) $recipientIds = [$uid];
            else $errors[] = 'Veuillez sélectionner un destinataire.';
        } elseif ($recipientType === 'group') {
            $gname = $_POST['recipient_group'] ?? '';
            if ($gname) {
                $stmt = $db->prepare("SELECT id FROM users WHERE group_name = ? AND active = 1");
                $stmt->execute([$gname]);
                $recipientIds = array_column($stmt->fetchAll(), 'id');
                if (empty($recipientIds)) $errors[] = 'Aucun utilisateur dans ce groupe.';
            } else {
                $errors[] = 'Veuillez sélectionner un groupe.';
            }
        } elseif ($recipientType === 'all') {
            $recipientIds = array_column($allUsers, 'id');
            if (empty($recipientIds)) $errors[] = 'Aucun utilisateur actif.';
        }
    }

    // Handle file attachments
    $newAttachments = [];
    $allowedExts = ['jpg','jpeg','png','gif','webp','mp4','webm','ogg','mov','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv'];
    $mimeMap = [
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
        'mp4'=>'video/mp4','webm'=>'video/webm','ogg'=>'video/ogg','mov'=>'video/quicktime',
        'pdf'=>'application/pdf','doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt'=>'text/plain','csv'=>'text/csv',
    ];

    if (empty($errors) && !empty($_FILES['attachments']['name'][0])) {
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        foreach ($_FILES['attachments']['error'] as $i => $err) {
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            if ($err !== UPLOAD_ERR_OK) { $errors[] = 'Erreur lors du téléversement d\'un fichier.'; continue; }
            $origName = $_FILES['attachments']['name'][$i];
            $tmp      = $_FILES['attachments']['tmp_name'][$i];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) { $errors[] = "Type non autorisé : $origName"; continue; }
            $mime    = $mimeMap[$ext] ?? 'application/octet-stream';
            $newName = uniqid('msg_', true) . '.' . $ext;
            if (move_uploaded_file($tmp, $uploadDir . $newName)) {
                $newAttachments[] = ['filename' => $newName, 'original_name' => $origName, 'mime_type' => $mime];
            } else {
                $errors[] = "Impossible d'enregistrer le fichier $origName.";
            }
        }
    }

    if (empty($errors)) {
        $db->prepare("INSERT INTO messages (sender_id, subject, body) VALUES (?, ?, ?)")
           ->execute([$user['id'], $subject, $body]);
        $msgId = (int)$db->lastInsertId();

        $insRecip = $db->prepare("INSERT OR IGNORE INTO message_recipients (message_id, user_id) VALUES (?, ?)");
        foreach ($recipientIds as $rid) {
            $insRecip->execute([$msgId, $rid]);
        }

        $insAtt = $db->prepare("INSERT INTO message_attachments (message_id, filename, original_name, mime_type) VALUES (?, ?, ?, ?)");
        foreach ($newAttachments as $att) {
            $insAtt->execute([$msgId, $att['filename'], $att['original_name'], $att['mime_type']]);
        }

        header('Location: /admin/messages?sent=1');
        exit;
    }
}

$pageTitle  = 'Nouveau message — ' . SITE_NAME;
$extraHead  = '<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">';
include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem;">
    <h1 class="page-title" style="margin:0;">Nouveau message</h1>
    <a href="/admin/messages" class="btn btn-sm">&larr; Messages</a>
</div>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" id="msg-form">
    <input type="hidden" name="body" id="body-input">

    <div class="card" style="display:flex; flex-direction:column; gap:1.25rem;">

        <!-- Destinataires -->
        <div class="form-group">
            <label style="margin-bottom:.5rem; display:block;">Destinataires <span style="color:red;">*</span></label>
            <div style="display:flex; gap:1.25rem; flex-wrap:wrap; margin-bottom:.75rem;">
                <label style="display:flex; align-items:center; gap:.4rem; cursor:pointer; font-weight:400;">
                    <input type="radio" name="recipient_type" value="individual"
                           <?= ($_POST['recipient_type'] ?? 'individual') === 'individual' ? 'checked' : '' ?>
                           onchange="updateRecipientUI()">
                    Personne sp&eacute;cifique
                </label>
                <?php if (!empty($groups)): ?>
                <label style="display:flex; align-items:center; gap:.4rem; cursor:pointer; font-weight:400;">
                    <input type="radio" name="recipient_type" value="group"
                           <?= ($_POST['recipient_type'] ?? '') === 'group' ? 'checked' : '' ?>
                           onchange="updateRecipientUI()">
                    Groupe
                </label>
                <?php endif; ?>
                <label style="display:flex; align-items:center; gap:.4rem; cursor:pointer; font-weight:400;">
                    <input type="radio" name="recipient_type" value="all"
                           <?= ($_POST['recipient_type'] ?? '') === 'all' ? 'checked' : '' ?>
                           onchange="updateRecipientUI()">
                    Tous les membres actifs (<?= count($allUsers) ?>)
                </label>
            </div>

            <div id="ui-individual">
                <select name="recipient_user">
                    <option value="">— Choisir un utilisateur —</option>
                    <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"
                                <?= ((int)($_POST['recipient_user'] ?? 0)) === $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?>
                            <?= $u['group_name'] ? ' (' . htmlspecialchars($u['group_name']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($groups)): ?>
            <div id="ui-group" style="display:none;">
                <select name="recipient_group">
                    <option value="">— Choisir un groupe —</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>"
                                <?= ($_POST['recipient_group'] ?? '') === $g ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div id="ui-all" style="display:none;">
                <p style="font-size:.85rem; color:var(--muted); font-style:italic; margin:0;">
                    Le message sera envoy&eacute; &agrave; tous les membres actifs.
                </p>
            </div>
        </div>

        <!-- Objet -->
        <div class="form-group">
            <label for="subject">Objet <span style="color:red;">*</span></label>
            <input type="text" id="subject" name="subject" required
                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                   placeholder="Ex : Nouvelle cotation disponible">
        </div>

        <!-- Corps (Quill) -->
        <div class="form-group">
            <label>Message <span style="color:red;">*</span></label>
            <div style="border:1px solid var(--border); border-radius:8px; overflow:hidden; margin-top:.3rem;">
                <div id="quill-editor" style="min-height:260px; font-size:1rem;"></div>
            </div>
        </div>

        <!-- Pièces jointes -->
        <div class="form-group">
            <label>Pi&egrave;ces jointes
                <span style="font-weight:400; font-size:.82rem; color:var(--muted);">
                    (images, vid&eacute;os, PDF, documents — plusieurs fichiers possibles)
                </span>
            </label>
            <input type="file" name="attachments[]" multiple
                   accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
                   style="display:block; margin-top:.35rem;">
        </div>

        <div>
            <button type="submit" class="btn btn-primary">&#10003; Envoyer le message</button>
        </div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
var quill = new Quill('#quill-editor', {
    theme: 'snow',
    modules: { toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ color: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link'],
        ['clean']
    ]}
});
<?php if (!empty($_POST['body'])): ?>
quill.clipboard.dangerouslyPasteHTML(<?= json_encode($_POST['body']) ?>);
<?php endif; ?>

document.getElementById('msg-form').addEventListener('submit', function () {
    document.getElementById('body-input').value = quill.root.innerHTML;
});

function updateRecipientUI() {
    var type = document.querySelector('input[name="recipient_type"]:checked').value;
    document.getElementById('ui-individual').style.display = type === 'individual' ? 'block' : 'none';
    var grp = document.getElementById('ui-group');
    if (grp) grp.style.display = type === 'group' ? 'block' : 'none';
    document.getElementById('ui-all').style.display = type === 'all' ? 'block' : 'none';
}
updateRecipientUI();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
