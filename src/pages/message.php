<?php
requireLogin();
$user    = getCurrentUser();
$db      = getDB();
$isAdmin = ($user['role'] === 'admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /messages');
    exit;
}

// Load message
$stmt = $db->prepare("SELECT m.*, u.username AS sender_name FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?");
$stmt->execute([$id]);
$message = $stmt->fetch();
if (!$message) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Access control
$recipient = null;
if (!$isAdmin) {
    $stmt = $db->prepare("SELECT * FROM message_recipients WHERE message_id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $recipient = $stmt->fetch();
    if (!$recipient) {
        header('Location: /messages');
        exit;
    }
    // Mark as read
    if (!$recipient['read_at']) {
        $db->prepare("UPDATE message_recipients SET read_at = datetime('now') WHERE message_id = ? AND user_id = ?")
           ->execute([$id, $user['id']]);
    }
}

// Handle reply or delete (users only)
$replyError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAdmin) {
    if (isset($_POST['delete_message'])) {
        $db->prepare("UPDATE message_recipients SET deleted = 1 WHERE message_id = ? AND user_id = ?")
           ->execute([$id, $user['id']]);
        header('Location: /messages');
        exit;
    }
    $body = trim($_POST['reply_body'] ?? '');
    if ($body === '') {
        $replyError = 'Le message ne peut pas être vide.';
    } else {
        $db->prepare("INSERT INTO message_replies (message_id, sender_id, body) VALUES (?, ?, ?)")
           ->execute([$id, $user['id'], $body]);
        header("Location: /message?id=$id");
        exit;
    }
}

// Load attachments
$attStmt = $db->prepare("SELECT * FROM message_attachments WHERE message_id = ? ORDER BY id ASC");
$attStmt->execute([$id]);
$attachments = $attStmt->fetchAll();

// Load replies + recipients (admin sees all; user sees only their own)
$filterUid = (int)($_GET['uid'] ?? 0);
if ($isAdmin) {
    if ($filterUid > 0) {
        $repStmt = $db->prepare("
            SELECT r.*, u.username FROM message_replies r
            JOIN users u ON u.id = r.sender_id
            WHERE r.message_id = ? AND r.sender_id = ?
            ORDER BY r.created_at ASC
        ");
        $repStmt->execute([$id, $filterUid]);
        $db->prepare("UPDATE message_replies SET read_by_admin = 1 WHERE message_id = ? AND sender_id = ?")
           ->execute([$id, $filterUid]);
    } else {
        $repStmt = $db->prepare("
            SELECT r.*, u.username FROM message_replies r
            JOIN users u ON u.id = r.sender_id
            WHERE r.message_id = ?
            ORDER BY r.created_at ASC
        ");
        $repStmt->execute([$id]);
        $db->prepare("UPDATE message_replies SET read_by_admin = 1 WHERE message_id = ?")
           ->execute([$id]);
    }
    $replies = $repStmt->fetchAll();

    // Recipients list with unread reply count per user
    $recipStmt = $db->prepare("
        SELECT mr.user_id, mr.read_at, u.username,
               (SELECT COUNT(*) FROM message_replies WHERE message_id = mr.message_id AND sender_id = mr.user_id) AS nb_replies,
               (SELECT COUNT(*) FROM message_replies WHERE message_id = mr.message_id AND sender_id = mr.user_id AND read_by_admin = 0) AS nb_unread
        FROM message_recipients mr
        JOIN users u ON u.id = mr.user_id
        WHERE mr.message_id = ?
        ORDER BY u.username ASC
    ");
    $recipStmt->execute([$id]);
    $recipients = $recipStmt->fetchAll();
} else {
    $repStmt = $db->prepare("
        SELECT r.*, u.username FROM message_replies r
        JOIN users u ON u.id = r.sender_id
        WHERE r.message_id = ? AND r.sender_id = ?
        ORDER BY r.created_at ASC
    ");
    $repStmt->execute([$id, $user['id']]);
    $replies  = $repStmt->fetchAll();
    $recipients = [];
}

$pageTitle = htmlspecialchars($message['subject']) . ' — ' . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:1.25rem;">
    <a href="<?= $isAdmin ? '/admin/messages' : '/messages' ?>" class="btn btn-sm">
        &larr; <?= $isAdmin ? 'Messages envoy&eacute;s' : 'Messagerie' ?>
    </a>
</div>

<!-- Message card -->
<div class="card" style="margin-bottom:1rem;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; padding-bottom:.75rem; border-bottom:1px solid var(--border);">
        <div>
            <h1 style="font-size:1.15rem; margin:0 0 .35rem 0;"><?= htmlspecialchars($message['subject']) ?></h1>
            <div style="font-size:.82rem; color:var(--muted);">
                De <strong><?= htmlspecialchars($message['sender_name']) ?></strong>
                &middot; <?= date('d/m/Y \à H\hi', strtotime($message['created_at'])) ?>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <a href="/admin/message-new" class="btn btn-primary" style="white-space:nowrap;">+ Nouveau</a>
        <?php else: ?>
            <form method="post" onsubmit="return confirm('Supprimer ce message ?');" style="margin:0;">
                <input type="hidden" name="delete_message" value="1">
                <button type="submit" title="Supprimer le message"
                        style="background:none; border:none; cursor:pointer; padding:.35rem .5rem;
                               color:var(--muted); line-height:1; border-radius:6px;"
                        onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--muted)'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        <path d="M10 11v6"></path><path d="M14 11v6"></path>
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                    </svg>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="message-body" style="line-height:1.7; color:var(--fg);">
        <?= $message['body'] ?>
    </div>

    <?php if (!empty($attachments)): ?>
    <div style="margin-top:1.25rem; padding-top:1rem; border-top:1px solid var(--border);">
        <p style="font-size:.78rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.7rem;">
            Pi&egrave;ces jointes
        </p>
        <div style="display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-start;">
            <?php foreach ($attachments as $att):
                $url  = '/uploads/messages/' . rawurlencode($att['filename']);
                $mime = $att['mime_type'];
            ?>
                <?php if (str_starts_with($mime, 'image/')): ?>
                    <a href="<?= $url ?>" target="_blank" rel="noopener">
                        <img src="<?= $url ?>" alt="<?= htmlspecialchars($att['original_name']) ?>"
                             style="max-width:180px; max-height:140px; border-radius:8px; object-fit:cover;
                                    border:1px solid var(--border); display:block;">
                    </a>
                <?php elseif (str_starts_with($mime, 'video/')): ?>
                    <div style="width:100%; max-width:520px;">
                        <video controls playsinline
                               style="width:100%; border-radius:8px; background:#000; display:block;">
                            <source src="<?= $url ?>" type="<?= htmlspecialchars($mime) ?>">
                        </video>
                        <div style="font-size:.75rem; color:var(--muted); margin-top:.25rem;">
                            <?= htmlspecialchars($att['original_name']) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= $url ?>" target="_blank" rel="noopener"
                       style="display:inline-flex; align-items:center; gap:.45rem; padding:.5rem .8rem;
                              border:1px solid var(--border); border-radius:8px; text-decoration:none;
                              color:var(--primary); font-size:.84rem; background:var(--bg);">
                        <?= $mime === 'application/pdf' ? '&#128462;' : '&#128196;' ?>
                        <?= htmlspecialchars($att['original_name']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin && !empty($recipients)): ?>
<!-- Destinataires (vue admin) -->
<div class="card" style="margin-bottom:1rem;">
    <p style="font-size:.78rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.65rem;">
        Destinataires (<?= count($recipients) ?>)
    </p>
    <div style="display:flex; flex-wrap:wrap; gap:.4rem;">
        <?php foreach ($recipients as $r): ?>
            <a href="/message?id=<?= $id ?>&uid=<?= $r['user_id'] ?>"
               style="display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .75rem;
                      border-radius:6px; font-size:.83rem; text-decoration:none;
                      border:1px solid <?= $filterUid === $r['user_id'] ? 'var(--primary)' : 'var(--border)' ?>;
                      color:<?= $filterUid === $r['user_id'] ? 'var(--primary)' : 'var(--fg)' ?>;
                      background:<?= $filterUid === $r['user_id'] ? 'rgba(37,99,235,.07)' : 'transparent' ?>;">
                <?= htmlspecialchars($r['username']) ?>
                <?php if (!$r['read_at']): ?>
                    <span title="Pas encore lu" style="width:7px; height:7px; border-radius:50%; background:#f59e0b;"></span>
                <?php elseif ($r['nb_unread'] > 0): ?>
                    <span style="background:#ef4444; color:#fff; border-radius:999px; padding:0 .35rem; font-size:.65rem; font-weight:700;">
                        <?= $r['nb_unread'] ?>
                    </span>
                <?php elseif ($r['nb_replies'] > 0): ?>
                    <span style="font-size:.7rem; color:var(--muted);"><?= $r['nb_replies'] ?> r&eacute;p.</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($filterUid > 0): ?>
        <div style="margin-top:.6rem;">
            <a href="/message?id=<?= $id ?>" style="font-size:.78rem; color:var(--muted); text-decoration:none;">
                &#10005; Afficher tous
            </a>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($replies)): ?>
<!-- Fil de réponses -->
<div style="margin-bottom:1rem;">
    <p style="font-size:.78rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.65rem;">
        <?= $isAdmin ? 'R&eacute;ponses re&ccedil;ues' : 'Vos r&eacute;ponses' ?>
    </p>
    <?php foreach ($replies as $r): ?>
        <div class="card" style="margin-bottom:.5rem;">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.45rem;">
                <strong style="font-size:.85rem;"><?= htmlspecialchars($r['username']) ?></strong>
                <span style="font-size:.75rem; color:var(--muted); white-space:nowrap;">
                    <?= date('d/m/Y \à H\hi', strtotime($r['created_at'])) ?>
                </span>
            </div>
            <p style="white-space:pre-wrap; font-size:.9rem; margin:0; color:var(--fg);">
                <?= htmlspecialchars($r['body']) ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$isAdmin): ?>
<!-- Formulaire de réponse -->
<div class="card">
    <h2 style="font-size:.95rem; margin-bottom:.75rem;">R&eacute;pondre</h2>
    <?php if ($replyError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($replyError) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <textarea name="reply_body" rows="4" required placeholder="Votre r&eacute;ponse&hellip;"
                      style="width:100%; padding:.65rem .9rem; border:1px solid var(--border);
                             border-radius:8px; font-size:.95rem; font-family:inherit; resize:vertical;"
                      ><?= htmlspecialchars($_POST['reply_body'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Envoyer ma r&eacute;ponse</button>
    </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
