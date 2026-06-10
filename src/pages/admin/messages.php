<?php
requireAdmin();
$db = getDB();
$pageTitle = 'Messagerie — ' . SITE_NAME;

$messages = $db->query("
    SELECT m.id, m.subject, m.created_at, u.username AS sender_name,
           (SELECT COUNT(*) FROM message_recipients WHERE message_id = m.id) AS nb_recipients,
           (SELECT COUNT(*) FROM message_replies WHERE message_id = m.id) AS nb_replies,
           (SELECT COUNT(*) FROM message_replies WHERE message_id = m.id AND read_by_admin = 0) AS nb_unread
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    ORDER BY m.created_at DESC
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap;">
    <h1 class="page-title" style="margin:0;">Messagerie</h1>
    <a href="/admin/message-new" class="btn btn-primary">+ Nouveau message</a>
    <a href="/dashboard" class="btn btn-sm">&larr; Tableau de bord</a>
</div>

<?php if (isset($_GET['sent'])): ?>
    <div class="alert alert-success">Message envoy&eacute; avec succ&egrave;s.</div>
<?php endif; ?>

<?php if (empty($messages)): ?>
    <div class="card" style="text-align:center; padding:2.5rem 1rem; color:var(--muted);">
        <div style="font-size:2.5rem; margin-bottom:.75rem;">&#9993;</div>
        <p>Aucun message envoy&eacute; pour l'instant.</p>
        <a href="/admin/message-new" class="btn btn-primary" style="margin-top:.75rem;">
            R&eacute;diger un premier message
        </a>
    </div>
<?php else: ?>
    <div class="card" style="padding:0; overflow:hidden;">
        <?php foreach ($messages as $i => $msg): ?>
            <div style="display:flex; align-items:center; gap:.75rem; padding:.85rem 1.1rem; flex-wrap:wrap;
                        border-bottom:<?= $i < count($messages) - 1 ? '1px solid var(--border)' : 'none' ?>;
                        background:<?= $msg['nb_unread'] > 0 ? 'rgba(239,68,68,.04)' : 'transparent' ?>;">
                <div style="flex:1; min-width:0;">
                    <a href="/message?id=<?= $msg['id'] ?>" style="text-decoration:none; color:inherit;">
                        <div style="<?= $msg['nb_unread'] > 0 ? 'font-weight:700;' : '' ?> overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($msg['subject']) ?>
                        </div>
                    </a>
                    <div style="font-size:.77rem; color:var(--muted); margin-top:.1rem;">
                        <?= $msg['nb_recipients'] ?> destinataire<?= $msg['nb_recipients'] > 1 ? 's' : '' ?>
                        &middot; <?= date('d/m/Y \à H\hi', strtotime($msg['created_at'])) ?>
                    </div>
                </div>
                <?php if ($msg['nb_unread'] > 0): ?>
                    <span style="background:#ef4444; color:#fff; border-radius:999px; padding:.15rem .55rem;
                                 font-size:.72rem; font-weight:700; white-space:nowrap; flex-shrink:0;">
                        <?= $msg['nb_unread'] ?> non lue<?= $msg['nb_unread'] > 1 ? 's' : '' ?>
                    </span>
                <?php elseif ($msg['nb_replies'] > 0): ?>
                    <span style="font-size:.77rem; color:var(--muted); white-space:nowrap; flex-shrink:0;">
                        <?= $msg['nb_replies'] ?> r&eacute;ponse<?= $msg['nb_replies'] > 1 ? 's' : '' ?>
                    </span>
                <?php endif; ?>
                <a href="/message?id=<?= $msg['id'] ?>" class="btn btn-sm" style="flex-shrink:0;">Voir</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
