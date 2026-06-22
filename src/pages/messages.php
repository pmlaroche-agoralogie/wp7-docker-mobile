<?php
requireLogin();
$user = getCurrentUser();
$db   = getDB();
$pageTitle = 'Messagerie — ' . SITE_NAME;

recordPageVisit($user['id'], 'messages');

$stmt = $db->prepare("
    SELECT m.id, m.subject, m.created_at, mr.read_at,
           u.username AS sender_name,
           (SELECT COUNT(*) FROM message_replies WHERE message_id = m.id AND sender_id = ?) AS nb_replies
    FROM messages m
    JOIN message_recipients mr ON mr.message_id = m.id AND mr.user_id = ?
    JOIN users u ON u.id = m.sender_id
    WHERE mr.deleted = 0
    ORDER BY m.created_at DESC
");
$stmt->execute([$user['id'], $user['id']]);
$messages = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Messagerie</h1>
    <a href="/dashboard" class="btn btn-sm">&larr; Accueil</a>
</div>

<?php if (empty($messages)): ?>
    <div class="card" style="text-align:center; padding:2.5rem 1rem; color:var(--muted);">
        <div style="font-size:2.5rem; margin-bottom:.75rem;">&#9993;</div>
        <p>Vous n'avez pas encore re&ccedil;u de message.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0; overflow:hidden;">
        <?php foreach ($messages as $i => $msg): ?>
            <?php $unread = !$msg['read_at']; ?>
            <a href="/message?id=<?= $msg['id'] ?>"
               style="display:flex; align-items:center; gap:.75rem; padding:.9rem 1.1rem; text-decoration:none;
                      color:inherit; flex-wrap:wrap;
                      border-bottom:<?= $i < count($messages) - 1 ? '1px solid var(--border)' : 'none' ?>;
                      background:<?= $unread ? 'rgba(37,99,235,.04)' : 'transparent' ?>;">
                <span style="width:8px; height:8px; border-radius:50%; flex-shrink:0;
                             background:<?= $unread ? 'var(--primary)' : 'transparent' ?>;"></span>
                <div style="flex:1; min-width:0;">
                    <div style="<?= $unread ? 'font-weight:700;' : '' ?> overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <?= htmlspecialchars($msg['subject']) ?>
                    </div>
                    <div style="font-size:.78rem; color:var(--muted); margin-top:.1rem;">
                        De <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
                        &middot; <?= date('d/m/Y', strtotime($msg['created_at'])) ?>
                    </div>
                </div>
                <?php if ($msg['nb_replies'] > 0): ?>
                    <span style="font-size:.75rem; color:var(--muted); white-space:nowrap; flex-shrink:0;">
                        <?= $msg['nb_replies'] ?> r&eacute;ponse<?= $msg['nb_replies'] > 1 ? 's' : '' ?>
                    </span>
                <?php endif; ?>
                <?php if ($unread): ?>
                    <span style="background:var(--primary); color:#fff; border-radius:999px;
                                 padding:.1rem .5rem; font-size:.7rem; font-weight:700; white-space:nowrap; flex-shrink:0;">
                        Nouveau
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
