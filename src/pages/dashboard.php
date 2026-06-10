<?php
requireLogin();
$pageTitle = 'Mon espace — ' . SITE_NAME;
$user = getCurrentUser();
$db   = getDB();

$meteoRow = $db->prepare("SELECT meteo_code_insee FROM users WHERE id = ?");
$meteoRow->execute([$user['id']]);
$meteoCodeInsee = $meteoRow->fetchColumn() ?: '64430';

// Find the most recently modified PDF in media/cotations/
$cotationsDir = '/var/www/html/media/cotations';
$latestPdf = null;
$latestPdfTime = 0;
if (is_dir($cotationsDir)) {
    foreach (glob($cotationsDir . '/*.pdf') as $file) {
        $mtime = filemtime($file);
        if ($mtime > $latestPdfTime) {
            $latestPdfTime = $mtime;
            $latestPdf = basename($file);
        }
    }
}

// Messagerie : 3 derniers messages + compteur non lus
$dashMsgStmt = $db->prepare("
    SELECT m.id, m.subject, m.created_at, mr.read_at
    FROM messages m
    JOIN message_recipients mr ON mr.message_id = m.id AND mr.user_id = ?
    ORDER BY m.created_at DESC LIMIT 1
");
$dashMsgStmt->execute([$user['id']]);
$dashMessages = $dashMsgStmt->fetchAll();

$unreadCountStmt = $db->prepare("SELECT COUNT(*) FROM message_recipients WHERE user_id = ? AND read_at IS NULL");
$unreadCountStmt->execute([$user['id']]);
$unreadCount = (int)$unreadCountStmt->fetchColumn();

// Admin : compteur réponses non lues
$adminUnreadReplies = 0;
if ($user['role'] === 'admin') {
    $adminUnreadReplies = (int)$db->query("SELECT COUNT(*) FROM message_replies WHERE read_by_admin = 0")->fetchColumn();
}

$recentAnnonces = $db->query(
    "SELECT id, titre, texte, tag, prix, created_at FROM annonces WHERE visible = 1 ORDER BY created_at DESC LIMIT 1"
)->fetchAll();

$tagColors = [
    'matériels' => ['bg' => '#dbeafe', 'color' => '#1d4ed8'],
    'aliments'  => ['bg' => '#dcfce7', 'color' => '#15803d'],
    'animaux'   => ['bg' => '#fef9c3', 'color' => '#a16207'],
    'services'  => ['bg' => '#ede9fe', 'color' => '#6d28d9'],
    'divers'    => ['bg' => '#f1f5f9', 'color' => '#475569'],
];

include __DIR__ . '/../includes/header.php';
?>
<div class="module-grid">

    <div class="module-card" style="cursor:default;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.5rem;">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#9993;</span>
                <h3 style="margin:0;">Messagerie</h3>
                <?php if ($unreadCount > 0): ?>
                    <span style="background:#ef4444; color:#fff; border-radius:999px; padding:.1rem .45rem; font-size:.68rem; font-weight:700;">
                        <?= $unreadCount ?>
                    </span>
                <?php endif; ?>
            </div>
            <a href="/messages" style="font-size:.78rem; color:var(--primary); text-decoration:none; white-space:nowrap;">Tous &rarr;</a>
        </div>
        <?php if (empty($dashMessages)): ?>
            <p style="color:var(--muted); font-size:.82rem; font-style:italic;">Aucun message re&ccedil;u.</p>
        <?php else: ?>
            <?php $msg = $dashMessages[0]; $unread = !$msg['read_at']; ?>
            <a href="/message?id=<?= $msg['id'] ?>"
               style="display:flex; align-items:center; gap:.4rem; text-decoration:none; color:inherit; font-size:.84rem; overflow:hidden;">
                <?php if ($unread): ?>
                    <span style="width:7px; height:7px; border-radius:50%; background:var(--primary); flex-shrink:0;"></span>
                <?php else: ?>
                    <span style="width:7px; flex-shrink:0;"></span>
                <?php endif; ?>
                <span style="<?= $unread ? 'font-weight:700;' : '' ?> overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1;">
                    <?= htmlspecialchars($msg['subject']) ?>
                </span>
                <span style="font-size:.73rem; color:var(--muted); flex-shrink:0;">
                    <?= date('d/m', strtotime($msg['created_at'])) ?>
                </span>
            </a>
        <?php endif; ?>
    </div>

    <div class="module-card" style="cursor:default;">
        <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.6rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128196;</span>
            <h3 style="margin:0;">Cotations</h3>
        </div>
        <?php if ($latestPdf): ?>
            <a href="/media/cotations/<?= rawurlencode($latestPdf) ?>" target="_blank" rel="noopener"
               style="display:flex; align-items:center; gap:.5rem; text-decoration:none; color:var(--primary);
                      padding:.5rem .6rem; border:1px solid var(--border); border-radius:8px;
                      font-size:.85rem; word-break:break-word;">
                <span style="font-size:1.2rem;">&#128462;</span>
                <?= htmlspecialchars($latestPdf) ?>
            </a>
        <?php else: ?>
            <p style="color:var(--muted); font-size:.82rem; font-style:italic;">Aucune cotation disponible.</p>
        <?php endif; ?>
    </div>

    <div class="module-card" style="cursor:default;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem;">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128668;</span>
                <h3 style="margin:0;">Annonces</h3>
            </div>
            <a href="/annonces" style="font-size:.78rem; color:var(--primary); text-decoration:none; white-space:nowrap;">Toutes &rarr;</a>
        </div>
        <?php if (empty($recentAnnonces)): ?>
            <p style="color:var(--muted); font-size:.82rem; font-style:italic;">Aucune annonce pour le moment.</p>
        <?php else: ?>
            <?php foreach ($recentAnnonces as $a):
                $tc = $tagColors[$a['tag']] ?? $tagColors['divers'];
            ?>
            <a href="/annonce?id=<?= $a['id'] ?>" class="annonce-home-item" style="padding:.45rem 0;">
                <div class="annonce-home-header">
                    <span class="annonce-tag" style="background:<?= $tc['bg'] ?>; color:<?= $tc['color'] ?>; font-size:.65rem;">
                        <?= htmlspecialchars($a['tag']) ?>
                    </span>
                    <?php if ($a['prix'] !== null): ?>
                        <span style="font-size:.78rem; color:var(--primary); font-weight:700; margin-left:auto;">
                            <?= number_format((float)$a['prix'], 2, ',', '&thinsp;') ?>&thinsp;&euro;
                        </span>
                    <?php endif; ?>
                </div>
                <h3 style="font-size:.82rem;"><?= htmlspecialchars($a['titre']) ?></h3>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="module-card" onclick="location.href='/produits'">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#127807;</span>
            <h3 style="margin:0;">Boutique</h3>
        </div>
    </div>

    <div class="module-card" onclick="location.href='/fichiers'">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128193;</span>
            <h3 style="margin:0;">Fichiers</h3>
        </div>
    </div>

    <div class="module-card" onclick="location.href='/fichiers-groupes'">
        <div style="display:flex; align-items:center; gap:.5rem;">
            <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#128101;</span>
            <h3 style="margin:0;">Fichiers de groupes</h3>
        </div>
    </div>

    <div class="module-card meteo-card" style="cursor:default;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem;">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <span class="mod-icon" style="font-size:1.4rem; margin:0;">&#127780;</span>
                <h3 style="margin:0;">M&eacute;t&eacute;o</h3>
            </div>
            <button id="meteo-btn-custom" onclick="meteoToggleForm()"
                style="font-size:.72rem; color:var(--primary); background:none; border:none; cursor:pointer; text-decoration:underline; padding:0;">
                Personnaliser
            </button>
        </div>

        <iframe id="meteo-iframe"
            width="100%" height="290" frameborder="0"
            src="https://meteofrance.com/widget/prevision/<?= htmlspecialchars($meteoCodeInsee) ?>0##3D6AA2"
            title="Pr&eacute;visions M&eacute;t&eacute;o-France"
            style="border-radius:8px; display:block;">
        </iframe>

        <div id="meteo-form" style="display:none; margin-top:.75rem;">
            <label style="font-size:.8rem; color:var(--muted); display:block; margin-bottom:.3rem;">
                Code postal
            </label>
            <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                <input id="meteo-cp" type="text" maxlength="5" placeholder="ex: 64300"
                    style="flex:1; min-width:90px; padding:.4rem .6rem; border:1px solid var(--border); border-radius:6px; font-size:.85rem;">
                <button onclick="meteoSearch()"
                    style="padding:.4rem .75rem; background:var(--primary); color:#fff; border:none; border-radius:6px; font-size:.82rem; cursor:pointer;">
                    Rechercher
                </button>
            </div>
            <div id="meteo-results" style="margin-top:.5rem;"></div>
            <div id="meteo-msg" style="font-size:.78rem; color:var(--muted); margin-top:.35rem;"></div>
        </div>
    </div>

    <script>
    function meteoToggleForm() {
        var f = document.getElementById('meteo-form');
        f.style.display = f.style.display === 'none' ? 'block' : 'none';
        document.getElementById('meteo-msg').textContent = '';
        document.getElementById('meteo-results').innerHTML = '';
    }

    function meteoSearch() {
        var cp = document.getElementById('meteo-cp').value.trim();
        if (!/^\d{5}$/.test(cp)) {
            document.getElementById('meteo-msg').textContent = 'Saisissez un code postal à 5 chiffres.';
            return;
        }
        document.getElementById('meteo-msg').textContent = 'Recherche…';
        document.getElementById('meteo-results').innerHTML = '';

        fetch('https://geo.api.gouv.fr/communes?codePostal=' + encodeURIComponent(cp) + '&fields=code,nom&format=json')
            .then(function(r) { return r.json(); })
            .then(function(communes) {
                document.getElementById('meteo-msg').textContent = '';
                if (!communes.length) {
                    document.getElementById('meteo-msg').textContent = 'Aucune commune trouvée pour ce code postal.';
                    return;
                }
                var html = '';
                communes.forEach(function(c) {
                    html += '<button onclick="meteoSave(\'' + c.code + '\', \'' + c.nom.replace(/'/g, "\\'") + '\')" '
                          + 'style="display:block; width:100%; text-align:left; padding:.35rem .6rem; margin-bottom:.3rem; '
                          + 'border:1px solid var(--border); border-radius:6px; background:var(--bg); cursor:pointer; font-size:.82rem;">'
                          + c.nom + '</button>';
                });
                document.getElementById('meteo-results').innerHTML = html;
            })
            .catch(function() {
                document.getElementById('meteo-msg').textContent = 'Erreur lors de la recherche. Réessayez.';
            });
    }

    function meteoSave(codeInsee, nomCommune) {
        document.getElementById('meteo-msg').textContent = 'Enregistrement…';
        fetch('/api/meteo-set', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code_insee: codeInsee })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                document.getElementById('meteo-msg').textContent = '';
                document.getElementById('meteo-form').style.display = 'none';
                document.getElementById('meteo-iframe').src =
                    'https://meteofrance.com/widget/prevision/' + codeInsee + '0##3D6AA2';
            } else {
                document.getElementById('meteo-msg').textContent = data.error || 'Erreur inconnue.';
            }
        })
        .catch(function() {
            document.getElementById('meteo-msg').textContent = 'Erreur réseau. Réessayez.';
        });
    }
    </script>

    <?php if ($user['role'] === 'admin'): ?>

    <div class="module-card" onclick="location.href='/admin/users'">
        <div class="mod-icon">&#128101;</div>
        <h3>Gestion utilisateurs</h3>
        <p>Liste, ajout, groupes, activation</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/pages'">
        <div class="mod-icon">&#128196;</div>
        <h3>Pages de contenu</h3>
        <p>&Eacute;diteur WYSIWYG des pages publiques</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/produits'">
        <div class="mod-icon">&#128230;</div>
        <h3>Produits</h3>
        <p>Gestion du catalogue, fiches, photos</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/commandes'">
        <div class="mod-icon">&#128203;</div>
        <h3>Commandes re&ccedil;ues</h3>
        <p>Suivi et livraison des commandes &eacute;leveurs</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/cotations'">
        <div class="mod-icon">&#128196;</div>
        <h3>Cotations</h3>
        <p>Gestion des PDF de cotations march&eacute;</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/annonces'">
        <div class="mod-icon">&#128668;</div>
        <h3>Annonces</h3>
        <p>Gestion des petites annonces &eacute;leveurs</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/fichiers-groupes'">
        <div class="mod-icon">&#128101;</div>
        <h3>Groupes de fichiers</h3>
        <p>Cr&eacute;er des groupes, g&eacute;rer les membres</p>
    </div>

    <div class="module-card" onclick="location.href='/admin/messages'" style="position:relative;">
        <div class="mod-icon">&#9993;</div>
        <h3>Messagerie</h3>
        <p>Envoyer des messages, consulter les r&eacute;ponses</p>
        <?php if ($adminUnreadReplies > 0): ?>
            <div style="margin-top:.4rem;">
                <span style="background:#ef4444; color:#fff; border-radius:999px; padding:.15rem .55rem; font-size:.72rem; font-weight:700;">
                    <?= $adminUnreadReplies ?> r&eacute;ponse<?= $adminUnreadReplies > 1 ? 's' : '' ?> non lue<?= $adminUnreadReplies > 1 ? 's' : '' ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
